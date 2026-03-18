<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\StaticCheck;

use Exception;
use Magento\FunctionalTestingFramework\Util\Script\ScriptUtil;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class PauseActionUsageCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 */
class PauseActionUsageCheck implements StaticCheckInterface
{
    public const ERROR_LOG_FILENAME = 'mftf-pause-action-usage-checks';
    public const ERROR_LOG_MESSAGE = 'MFTF Pause Action Usage Check';

    /**
     * Array containing all errors found after running the execute() function.
     */
    private array $errors = [];

    /**
     * String representing the output summary found after running the execute() function.
     */
    private ?string $output = null;

    /**
     * ScriptUtil instance
     */
    private ?\Magento\FunctionalTestingFramework\Util\Script\ScriptUtil $scriptUtil = null;

    /**
     * Test xml files to scan
     *
     * @var Finder|array
     */
    private $testXmlFiles = [];

    /**
     * Action group xml files to scan
     *
     * @var Finder|array
     */
    private $actionGroupXmlFiles = [];

    /**
     * Suite xml files to scan
     *
     * @var Finder|array
     */
    private $suiteXmlFiles = [];

    /**
     * Root suite xml files to scan
     *
     * @var Finder|array
     */
    private $rootSuiteXmlFiles = [];

    /**
     * Checks usage of pause action in action groups, tests and suites and prints out error to file.
     *
     * @throws Exception
     */
    public function execute(InputInterface $input): void
    {
        $this->scriptUtil = new ScriptUtil();
        $modulePaths = [];
        $includeRootPath = true;
        $path = $input->getOption('path');
        if ($path) {
            if (!realpath($path)) {
                throw new \InvalidArgumentException('Invalid --path option: ' . $path);
            }
            $modulePaths[] = realpath($path);
            $includeRootPath = false;
        } else {
            $modulePaths = $this->scriptUtil->getAllModulePaths();
        }

        $this->testXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'Test');
        $this->actionGroupXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'ActionGroup');
        $this->suiteXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'Suite');
        if ($includeRootPath) {
            $this->rootSuiteXmlFiles = $this->scriptUtil->getRootSuiteXmlFiles();
        }
        $this->errors = [];
        $this->errors += $this->validatePauseActionUsageInActionGroups($this->actionGroupXmlFiles);
        $this->errors += $this->validatePauseActionUsageInTests($this->testXmlFiles);
        $this->errors += $this->validatePauseActionUsageInSuites($this->suiteXmlFiles);
        $this->errors += $this->validatePauseActionUsageInSuites($this->rootSuiteXmlFiles);

        $this->output = $this->scriptUtil->printErrorsToFile(
            $this->errors,
            StaticChecksList::getErrorFilesPath() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt',
            self::ERROR_LOG_MESSAGE
        );
    }

    /**
     * Finds usages of pause action in action group files
     * @param array $actionGroupXmlFiles
     */
    private function validatePauseActionUsageInActionGroups($actionGroupXmlFiles): array
    {
        $actionGroupErrors = [];
        foreach ($actionGroupXmlFiles as $filePath) {
            $domDocument = new \DOMDocument();
            $domDocument->load($filePath);
            $actionGroup = $domDocument->getElementsByTagName('actionGroup')->item(0);
            $violatingStepKeys = $this->findViolatingPauseStepKeys($actionGroup);
            $actionGroupErrors = array_merge($actionGroupErrors, $this->setErrorOutput($violatingStepKeys, $filePath));
        }
        return $actionGroupErrors;
    }

    /**
     * Finds usages of pause action in test files
     * @param array $testXmlFiles
     */
    private function validatePauseActionUsageInTests($testXmlFiles): array
    {
        $testErrors = [];
        foreach ($testXmlFiles as $filePath) {
            $domDocument = new \DOMDocument();
            $domDocument->load($filePath);
            $test = $domDocument->getElementsByTagName('test')->item(0);
            $violatingStepKeys = $this->findViolatingPauseStepKeys($test);
            $testErrors = array_merge($testErrors, $this->setErrorOutput($violatingStepKeys, $filePath));
        }
        return $testErrors;
    }

    /**
     * Finds usages of pause action in suite files
     * @param array $suiteXmlFiles
     */
    private function validatePauseActionUsageInSuites($suiteXmlFiles): array
    {
        $suiteErrors = [];
        foreach ($suiteXmlFiles as $filePath) {
            $domDocument = new \DOMDocument();
            $domDocument->load($filePath);
            $suite = $domDocument->getElementsByTagName('suite')->item(0);
            $violatingStepKeys = $this->findViolatingPauseStepKeys($suite);
            $suiteErrors = array_merge($suiteErrors, $this->setErrorOutput($violatingStepKeys, $filePath));
        }
        return $suiteErrors;
    }

    /**
     * Finds violating pause action step keys
     * @param \DomNode $entity
     */
    private function findViolatingPauseStepKeys($entity): array
    {
        $violatingStepKeys = [];
        $entityName = $entity->getAttribute('name');
        $references = $entity->getElementsByTagName('pause');

        foreach ($references as $reference) {
            $pauseStepKey = $reference->getAttribute('stepKey');
            $violatingStepKeys[$entityName][] = $pauseStepKey;
        }
        return $violatingStepKeys;
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
     * Return string of a short human readable result of the check. For example: "No errors found."
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Build and return error output for pause action usages
     *
     * @param array       $violatingReferences
     * @param SplFileInfo $path
     * @return array{non-falsy-string}[]
     */
    private function setErrorOutput($violatingReferences, $path): array
    {
        $testErrors = [];

        $filePath = StaticChecksList::getFilePath($path->getRealPath());

        if (!empty($violatingReferences)) {
            // Build error output
            $errorOutput = "\nFile \"{$filePath}\"";
            $errorOutput .= "\ncontains pause action(s):\n\t\t";
            foreach ($violatingReferences as $entityName => $stepKey) {
                $errorOutput .= "\n\t {$entityName} has pause action at stepKey(s): " . implode(', ', $stepKey);
            }
            $testErrors[$filePath][] = $errorOutput;
        }
        return $testErrors;
    }
}
