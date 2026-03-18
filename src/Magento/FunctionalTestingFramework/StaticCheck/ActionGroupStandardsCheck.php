<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\StaticCheck;

use Magento\FunctionalTestingFramework\Test\Handlers\ActionGroupObjectHandler;
use Magento\FunctionalTestingFramework\Test\Objects\ActionGroupObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Finder\Finder;
use Exception;
use Magento\FunctionalTestingFramework\Util\Script\ScriptUtil;
use Symfony\Component\Finder\SplFileInfo;
use DOMElement;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;

/**
 * Class ActionGroupArgumentsCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 */
class ActionGroupStandardsCheck implements StaticCheckInterface
{
    const ACTIONGROUP_NAME_REGEX_PATTERN = '/<actionGroup name=["\']([^\'"]*)/';
    const ERROR_LOG_FILENAME = 'mftf-standards-checks';
    const ERROR_LOG_MESSAGE = 'MFTF Action Group Unused Arguments Check';
    const STEP_KEY_REGEX_PATTERN = '/stepKey=["\']([^\'"]*)/';

    /**
     * Array containing all errors found after running the execute() function.
     * @var array
     */
    private $errors = [];

    /**
     * String representing the output summary found after running the execute() function.
     */
    private ?string $output = null;

    /**
     * ScriptUtil instance
     */
    private ?\Magento\FunctionalTestingFramework\Util\Script\ScriptUtil $scriptUtil = null;

    /**
     * Checks unused arguments in action groups and prints out error to file.
     *
     * @throws Exception
     */
    public function execute(InputInterface $input): void
    {
        $this->scriptUtil = new ScriptUtil();
        $allModules = $this->scriptUtil->getAllModulePaths();

        $actionGroupXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope(
            $allModules,
            DIRECTORY_SEPARATOR . 'ActionGroup' . DIRECTORY_SEPARATOR
        );

        $this->errors = $this->findErrorsInFileSet($actionGroupXmlFiles);

        $this->output = $this->scriptUtil->printErrorsToFile(
            $this->errors,
            StaticChecksList::getErrorFilesPath() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt',
            self::ERROR_LOG_MESSAGE
        );
    }

    /**
     * Return array containing all errors found after running the execute() function.
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Return string of a short human readable result of the check. For example: "No unused arguments found."
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Finds all unused arguments in given set of actionGroup files
     * @param Finder $files
     * @return array $testErrors
     */
    private function findErrorsInFileSet($files): array
    {
        $actionGroupErrors = [];
        /** @var SplFileInfo $filePath */
        foreach ($files as $filePath) {
            $actionGroupReferencesDataArray = [];
            $actionGroupToArguments = [];
            $contents = $filePath->getContents();
            preg_match_all(
                self::STEP_KEY_REGEX_PATTERN,
                (string) preg_replace('/<!--(.|\s)*?-->/', '', $contents),
                $actionGroupReferences
            );
            foreach ($actionGroupReferences[0] as $actionGroupReferencesData) {
                $actionGroupReferencesDataArray[] = trim(
                    str_replace(['stepKey', '='], [""], $actionGroupReferencesData)
                ).'"';
            }
            $duplicateStepKeys = array_unique(
                array_diff_assoc(
                    $actionGroupReferencesDataArray,
                    array_unique(
                        $actionGroupReferencesDataArray
                    )
                )
            );
            unset($actionGroupReferencesDataArray);
            if (count($duplicateStepKeys) > 0) {
                throw new TestFrameworkException('Action group has duplicate step keys '
                  .implode(",", array_unique($duplicateStepKeys))." File Path ".$filePath);
            }
            /** @var DOMElement $actionGroup */
            $actionGroup = $this->getActionGroupDomElement($contents);
            $arguments = $this->extractActionGroupArguments($actionGroup);
            $unusedArguments = $this->findUnusedArguments($arguments, $contents);
            if (!empty($unusedArguments)) {
                $actionGroupToArguments[$actionGroup->getAttribute('name')] = $unusedArguments;
                $actionGroupErrors += $this->setErrorOutput($actionGroupToArguments, $filePath);
            }
        }
        return $actionGroupErrors;
    }

    /**
     * Extract actionGroup DomElement from xml file
     * @param string $contents
     * @return \DOMElement
     */
    public function getActionGroupDomElement($contents)
    {
        $domDocument = new \DOMDocument();
        $domDocument->loadXML($contents);
        return $domDocument->getElementsByTagName('actionGroup')[0];
    }

    /**
     * Get list of action group arguments declared in an action group
     * @param \DOMElement $actionGroup
     * @return array $arguments
     */
    public function extractActionGroupArguments($actionGroup): array
    {
        $arguments = [];
        $argumentsNodes = $actionGroup->getElementsByTagName('arguments');
        if ($argumentsNodes->length > 0) {
            $argumentNodes = $argumentsNodes[0]->getElementsByTagName('argument');
            foreach ($argumentNodes as $argumentNode) {
                $arguments[] = $argumentNode->getAttribute('name');
            }
        }
        return $arguments;
    }

    /**
     * Returns unused arguments in an action group
     * @param array  $arguments
     * @param string $contents
     */
    public function findUnusedArguments($arguments, $contents): array
    {
        $unusedArguments = [];
        preg_match(self::ACTIONGROUP_NAME_REGEX_PATTERN, $contents, $actionGroupName);
        $validActionGroup = false;
        try {
            $actionGroup = ActionGroupObjectHandler::getInstance()->getObject($actionGroupName[1]);
            if ($actionGroup) {
                $validActionGroup = true;
            }
        } catch (Exception) {
        }

        if (!$validActionGroup) {
            return $unusedArguments;
        }

        foreach ($arguments as $argument) {
            //pattern to match all argument references
            $patterns = [
                '(\{{2}' . $argument . '(\.[a-zA-Z0-9_\[\]\(\).,\'\/ ]+)?}{2})',
                '([(,\s\'$$]' . $argument . '(\.[a-zA-Z0-9_$\[\]]+)?[),\s\'])'
            ];
            // matches entity references
            if (preg_match($patterns[0], $contents)) {
                continue;
            }
            //matches parametrized references
            if (preg_match($patterns[1], $contents)) {
                continue;
            }
            //for extending action groups, exclude arguments that are also defined in parent action group
            if ($this->isParentActionGroupArgument($argument, $actionGroup)) {
                continue;
            }
            $unusedArguments[] = $argument;
        }
        return $unusedArguments;
    }

    /**
     * Checks if the argument is also defined in the parent for extending action groups.
     * @param string            $argument
     * @param ActionGroupObject $actionGroup
     */
    private function isParentActionGroupArgument($argument, $actionGroup): bool
    {
        $parentActionGroupName = $actionGroup->getParentName();
        if ($parentActionGroupName !== null) {
            $parentActionGroup = ActionGroupObjectHandler::getInstance()->getObject($parentActionGroupName);
            $parentArguments = $parentActionGroup->getArguments();
            foreach ($parentArguments as $parentArgument) {
                if ($argument === $parentArgument->getName()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Builds and returns error output for violating references
     *
     * @param SplFileInfo $path
     * @return array{non-falsy-string}[]
     */
    private function setErrorOutput(array $actionGroupToArguments, $path): array
    {
        $actionGroupErrors = [];
        if (!empty($actionGroupToArguments)) {
            // Build error output
            $errorOutput = "\nFile \"{$path->getRealPath()}\"";
            $errorOutput .= "\ncontains action group(s) with unused arguments.\n\t\t";
            foreach ($actionGroupToArguments as $actionGroup => $arguments) {
                $errorOutput .= "\n\t {$actionGroup} has unused argument(s): " . implode(", ", $arguments);
            }
            $actionGroupErrors[$path->getRealPath()][] = $errorOutput;
        }
        return $actionGroupErrors;
    }
}
