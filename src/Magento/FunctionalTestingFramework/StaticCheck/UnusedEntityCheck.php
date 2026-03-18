<?php

declare(strict_types=1);
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\StaticCheck;

use DOMElement;
use Exception;
use Magento\FunctionalTestingFramework\Util\Script\ScriptUtil;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class UnusedEntityCheck
 *
 * @package Magento\FunctionalTestingFramework\StaticCheck
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UnusedEntityCheck implements StaticCheckInterface
{
    public const ERROR_LOG_FILENAME = 'mftf-unused-entity-usage-checks';
    public const ENTITY_REGEX_PATTERN = "/\{(\{)*([\w.]+)(\})*\}/";
    public const SELECTOR_REGEX_PATTERN = '/selector=["\']([^\'"]*)/';
    public const ERROR_LOG_MESSAGE = 'MFTF Unused Entity Usage Check';
    public const SECTION_REGEX_PATTERN = "/\w*Section\b/";
    public const REQUIRED_ENTITY = '/<requiredEntity(.*?)>(.+?)<\/requiredEntity>/';
    public const ENTITY_SEPERATED_BY_DOT_REFERENCE = '/([\w]+)(\.)+([\w]+)/';

    /**
     * ScriptUtil instance
     */
    private ?\Magento\FunctionalTestingFramework\Util\Script\ScriptUtil $scriptUtil = null;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var string
     */
    private $output = '';

    /**
     * Checks test dependencies, determined by references in tests versus the dependencies listed in the Magento module
     *
     * @throws Exception
     */
    public function execute(InputInterface $input): void
    {
        $this->errors  = $this->unusedEntities();
        $this->output = $this->scriptUtil->printErrorsToFile(
            $this->errors,
            StaticChecksList::getErrorFilesPath() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt',
            self::ERROR_LOG_MESSAGE
        );
    }

    /**
     * Centralized method to get unused Entities
     * @return array
     */
    public function unusedEntities()
    {
        $this->scriptUtil = new ScriptUtil();
        $domDocument = new \DOMDocument();
        $modulePaths = $this->scriptUtil->getAllModulePaths();
        $testXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'Test');
        $actionGroupXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'ActionGroup');
        $dataXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'Data');
        $pageXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'Page');
        $sectionXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'Section');
        $suiteXmlFiles = $this->scriptUtil->getModuleXmlFilesByScope($modulePaths, 'Suite');
        foreach ($dataXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $entityResult = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('entity'),
                ['type' => 'name']
            );
            foreach ($entityResult as $entitiesResultData) {
                $dataNames[$entitiesResultData[key($entitiesResultData)]] = [
                    'dataFilePath' => $filePath->getRealPath(),
                ];
            }
        }
        foreach ($actionGroupXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $actionGroupName = $domDocument->getElementsByTagName('actionGroup')->item(0)->getAttribute('name');
            if (!empty($domDocument->getElementsByTagName('actionGroup')->item(0)->getAttribute('deprecated'))) {
                continue;
            }
            $allActionGroupFileNames[$actionGroupName ] =
            $filePath->getRealPath();
        }

        foreach ($sectionXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $sectionName = $domDocument->getElementsByTagName('section')->item(0)->getAttribute('name');
            $sectionFileNames[$sectionName] = $filePath->getRealPath();
        }
        foreach ($pageXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $pageName = $domDocument->getElementsByTagName('page')->item(0)->getAttribute('name');
            $pageFiles[$pageName] = $filePath->getRealPath();
        }
        $actionGroupReferences  = $this->unusedActionEntity(
            $domDocument,
            $actionGroupXmlFiles,
            $testXmlFiles,
            $allActionGroupFileNames,
            $suiteXmlFiles
        );
        $entityReferences = $this->unusedData(
            $domDocument,
            $actionGroupXmlFiles,
            $testXmlFiles,
            $dataNames,
            $dataXmlFiles
        );
        $pagesReference = $this->unusedPageEntity(
            $domDocument,
            $actionGroupXmlFiles,
            $testXmlFiles,
            $pageFiles,
            $suiteXmlFiles
        );
        $sectionReference = $this->unusedSectionEntity(
            $domDocument,
            $actionGroupXmlFiles,
            $testXmlFiles,
            $pageXmlFiles,
            $sectionFileNames,
            $suiteXmlFiles
        );
        return  $this->setErrorOutput(
            array_merge(
                array_values($actionGroupReferences),
                array_values($entityReferences),
                array_values($pagesReference),
                array_values($sectionReference)
            )
        );
    }

    /**
     * Setting error message
     *
     * @throws Exception
     */
    private function setErrorOutput(array $unusedFilePath): array
    {
        $testErrors = [];
        foreach ($unusedFilePath as $files) {
            $contents = file_get_contents($files);
            $file = fopen($files, 'a');
            if (!str_contains($contents, '<!--@Ignore(Unused_Entity_Check)-->')) {
                fwrite($file, '<!--@Ignore(Unused_Entity_Check)-->');
            }
        }
        return $testErrors;
    }

    /**
     * Retrieves Unused Action Group Entities
     *
     * @param  DOMDocument $domDocument
     * @param  ScriptUtil  $actionGroupXmlFiles
     * @param  ScriptUtil  $testXmlFiles
     * @param  ScriptUtil  $allActionGroupFileNames
     * @param  ScriptUtil  $suiteXmlFiles
     * @throws Exception
     */
    public function unusedActionEntity(
        $domDocument,
        $actionGroupXmlFiles,
        $testXmlFiles,
        array $allActionGroupFileNames,
        $suiteXmlFiles
    ): array {
        foreach ($suiteXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $referencesSuite = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('actionGroup'),
                'ref'
            );
            foreach ($referencesSuite as $referencesResultSuite) {
                if (isset($allActionGroupFileNames[$referencesResultSuite])) {
                    unset($allActionGroupFileNames[$referencesResultSuite]);
                }
            }
        }

        foreach ($actionGroupXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $actionGroup = $domDocument->getElementsByTagName('actionGroup')->item(0);
            $references = $actionGroup->getAttribute('extends');
            if (in_array($references, array_keys($allActionGroupFileNames))) {
                unset($allActionGroupFileNames[$references]);
            }
        }
        foreach ($testXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $testReferences = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('actionGroup'),
                'ref'
            );
            foreach ($testReferences as $testReferencesResult) {
                if (isset($allActionGroupFileNames[$testReferencesResult])) {
                    unset($allActionGroupFileNames[$testReferencesResult]);
                }
            }
        }
        return $allActionGroupFileNames;
    }

    /**
     * Retrieves Unused Page Entities
     *
     * @param  DOMDocument $domDocument
     * @return array
     * @throws Exception
     */
    public function unusedPageEntity($domDocument, $actionGroupXmlFiles, $testXmlFiles, $pageNames, $suiteXmlFiles)
    {

        foreach ($suiteXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $contents = file_get_contents($filePath);
            $pagesReferencesInSuites = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('amOnPage'),
                'url'
            );
            foreach ($pagesReferencesInSuites as $pagesReferencesInSuitesResult) {
                $explodepagesReferencesResult = explode(
                    '.',
                    trim((string) $pagesReferencesInSuitesResult, '{}')
                );
                unset($pageNames[$explodepagesReferencesResult[0]]);
            }
            $pageNames = $this->entityReferencePatternCheck($domDocument, $pageNames, $contents, false, []);
        }
        foreach ($actionGroupXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $contents = file_get_contents($filePath);
            $pagesReferences = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('amOnPage'),
                'url'
            );
            foreach ($pagesReferences as $pagesReferencesResult) {
                $explodepagesReferencesResult = explode(
                    '.',
                    trim((string) $pagesReferencesResult, '{}')
                );
                unset($pageNames[$explodepagesReferencesResult[0]]);
            }
            $pageNames = $this->entityReferencePatternCheck($domDocument, $pageNames, $contents, false, []);
        }

        foreach ($testXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $contents = file_get_contents($filePath);
            $testReferences = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('amOnPage'),
                'url'
            );
            foreach ($testReferences as $pagesReferencesResult) {
                $explodepagesReferencesResult = explode(
                    '.',
                    trim((string) $pagesReferencesResult, '{}')
                );
                unset($pageNames[$explodepagesReferencesResult[0]]);
            }
            $pageNames = $this->entityReferencePatternCheck($domDocument, $pageNames, $contents, false, []);
        }
        return $pageNames;
    }

    /**
     * Common Pattern Check Method
     *
     * @param  DOMDocument $domDocument
     * @param  string      $contents
     * @throws Exception
     */
    private function entityReferencePatternCheck($domDocument, array $fileNames, string|bool $contents, bool $data, $removeDataFilePath): array
    {
        $sectionArgumentValueReference = $this->getAttributesFromDOMNodeList(
            $domDocument->getElementsByTagName('argument'),
            'value'
        );
        $sectionDefaultValueArgumentReference = $this->getAttributesFromDOMNodeList(
            $domDocument->getElementsByTagName('argument'),
            'defaultValue'
        );
        $sectionArgumentValue = array_merge($sectionArgumentValueReference, $sectionDefaultValueArgumentReference);
        foreach ($sectionArgumentValue as $sectionArgumentValueResult) {
            $explodedReference = str_contains((string) $sectionArgumentValueResult, '$')
                ? explode('.', trim((string) $sectionArgumentValueResult, '$'))
                : explode('.', trim((string) $sectionArgumentValueResult, '{}'));
            if (in_array($explodedReference[0], array_keys($fileNames))) {
                $removeDataFilePath[] = $fileNames[$explodedReference[0]]['dataFilePath'] ?? [];
                unset($fileNames[$explodedReference[0]]);
            }
        }
        preg_match_all(self::ENTITY_REGEX_PATTERN, $contents, $bracketReferencesData);
        preg_match_all(
            self::ENTITY_SEPERATED_BY_DOT_REFERENCE,
            $contents,
            $entitySeperatedByDotReferenceActionGroup
        );
        $entityReferenceDataResultActionGroup = array_merge(
            array_unique($bracketReferencesData[0]),
            array_unique($entitySeperatedByDotReferenceActionGroup[0])
        );

        foreach (array_unique($entityReferenceDataResultActionGroup) as $bracketReferencesResults) {
            $bracketReferencesDataResultOutput = explode('.', trim($bracketReferencesResults, '{}'));
            if (in_array($bracketReferencesDataResultOutput[0], array_keys($fileNames))) {
                $removeDataFilePath[] = $fileNames[$bracketReferencesDataResultOutput[0]]['dataFilePath'] ?? [];
                unset($fileNames[$bracketReferencesDataResultOutput[0]]);
            }
        }

        return ($data === true) ? ['dataFilePath' => $removeDataFilePath ,'fileNames' => $fileNames ] : $fileNames ;
    }

    /**
     * Retrieves Unused Section Entities
     *
     * @param  DOMDocument $domDocument
     * @param  ScriptUtil  $actionGroupXmlFiles
     * @param  ScriptUtil  $testXmlFiles
     * @param  ScriptUtil  $pageXmlFiles
     * @param  array       $sectionFileNames
     * @param  ScriptUtil  $suiteXmlFiles
     * @return array
     * @throws Exception
     */
    public function unusedSectionEntity(
        $domDocument,
        $actionGroupXmlFiles,
        $testXmlFiles,
        $pageXmlFiles,
        $sectionFileNames,
        $suiteXmlFiles
    ) {
        foreach ($suiteXmlFiles as $filePath) {
            $contents = file_get_contents($filePath);
            $domDocument->load($filePath);
            preg_match_all(
                self::SELECTOR_REGEX_PATTERN,
                $contents,
                $selectorReferences
            );

            if (isset($selectorReferences[1])) {
                foreach (array_unique($selectorReferences[1]) as $selectorReferencesResult) {
                    $trimSelector = explode('.', trim($selectorReferencesResult, '{{}}'));
                    if (isset($sectionFileNames[$trimSelector[0]])) {
                        unset($sectionFileNames[$trimSelector[0]]);
                    }
                }
            }
            $sectionFileNames = $this->entityReferencePatternCheck(
                $domDocument,
                $sectionFileNames,
                $contents,
                false,
                []
            );
        }
        foreach ($actionGroupXmlFiles as $filePath) {
            $contents = file_get_contents($filePath);
            $domDocument->load($filePath);
            preg_match_all(
                self::SELECTOR_REGEX_PATTERN,
                $contents,
                $selectorReferences
            );

            if (isset($selectorReferences[1])) {
                foreach (array_unique($selectorReferences[1]) as $selectorReferencesResult) {
                    $trimSelector = explode('.', trim($selectorReferencesResult, '{{}}'));
                    if (isset($sectionFileNames[$trimSelector[0]])) {
                        unset($sectionFileNames[$trimSelector[0]]);
                    }
                }
            }
            $sectionFileNames = $this->entityReferencePatternCheck(
                $domDocument,
                $sectionFileNames,
                $contents,
                false,
                []
            );
        }
        return $this->getUnusedSectionEntitiesReferenceInActionGroupAndTestFiles(
            $testXmlFiles,
            $pageXmlFiles,
            $domDocument,
            $sectionFileNames
        );
    }

    /**
     * Get unused section entities reference in Action group and Test files
     * @param  ScriptUtil  $testXmlFiles
     * @param  ScriptUtil  $pageXmlFiles
     * @param  DOMDocument $domDocument
     * @param  array       $sectionFileNames
     * @return array
     * @throws Exception
     */
    private function getUnusedSectionEntitiesReferenceInActionGroupAndTestFiles(
        $testXmlFiles,
        $pageXmlFiles,
        $domDocument,
        $sectionFileNames
    ) {
        foreach ($testXmlFiles as $filePath) {
            $contents = file_get_contents($filePath);
            $domDocument->load($filePath);
            preg_match_all(
                self::SELECTOR_REGEX_PATTERN,
                $contents,
                $selectorReferences
            );
            if (isset($selectorReferences[1])) {
                foreach (array_unique($selectorReferences[1]) as $selectorReferencesResult) {
                    $trimSelector = explode('.', trim($selectorReferencesResult, '{{}}'));
                    if (isset($sectionFileNames[$trimSelector[0]])) {
                        unset($sectionFileNames[$trimSelector[0]]);
                    }
                }
            }
            $sectionFileNames = $this->entityReferencePatternCheck(
                $domDocument,
                $sectionFileNames,
                $contents,
                false,
                []
            );
        }

        foreach ($pageXmlFiles as $filePath) {
            $contents = file_get_contents($filePath);
            preg_match_all(
                self::SELECTOR_REGEX_PATTERN,
                $contents,
                $selectorReferences
            );
            if (isset($selectorReferences[1])) {
                foreach (array_unique($selectorReferences[1]) as $selectorReferencesResult) {
                    $trimSelector = explode('.', trim($selectorReferencesResult, '{{}}'));
                    if (isset($sectionFileNames[$trimSelector[0]])) {
                        unset($sectionFileNames[$trimSelector[0]]);
                    }
                }
            }
            $sectionFileNames = $this->entityReferencePatternCheck(
                $domDocument,
                $sectionFileNames,
                $contents,
                false,
                []
            );
        }
        return  $sectionFileNames;
    }
    /**
     * Return Unused Data entities
     *
     * @param  DOMDocument $domDocument
     */
    public function unusedData(
        $domDocument,
        $actionGroupXmlFiles,
        $testXmlFiles,
        array $dataNames,
        $dataXmlFiles
    ): array {
        $removeDataFilePath = [];
        foreach ($dataXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $contents = file_get_contents($filePath);
            preg_match_all(self::REQUIRED_ENTITY, $contents, $requiredEntityReference);
            foreach ($requiredEntityReference[2] as $requiredEntityReferenceResult) {
                if (isset($dataNames[$requiredEntityReferenceResult])) {
                    $removeDataFilePath[] =
                    $dataNames[$requiredEntityReferenceResult]['dataFilePath'];
                    unset($dataNames[$requiredEntityReferenceResult]);
                }
            }
        }
        foreach ($actionGroupXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $contents = file_get_contents($filePath);
            $getUnusedFilePath = $this->entityReferencePatternCheck(
                $domDocument,
                $dataNames,
                $contents,
                true,
                $removeDataFilePath
            );
            $dataNames = $getUnusedFilePath['fileNames'];
            $removeDataFilePath = $getUnusedFilePath['dataFilePath'];
        }
        foreach ($testXmlFiles as $filePath) {
            $domDocument->load($filePath);
            $contents = file_get_contents($filePath);
            $getUnusedFilePath = $this->entityReferencePatternCheck(
                $domDocument,
                $dataNames,
                $contents,
                true,
                $removeDataFilePath
            );
            $dataNames = $getUnusedFilePath['fileNames'];
            $removeDataFilePath = $getUnusedFilePath['dataFilePath'];
            $createdDataReferences = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('createData'),
                'entity'
            );
            $updatedDataReferences = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('updateData'),
                'entity'
            );
            $getDataReferences = $this->getAttributesFromDOMNodeList(
                $domDocument->getElementsByTagName('getData'),
                'entity'
            );
            $dataReferences = array_unique(
                array_merge(
                    $createdDataReferences,
                    $updatedDataReferences,
                    $getDataReferences
                )
            );
            foreach ($dataReferences as $dataReferencesResult) {
                if (isset($dataNames[$dataReferencesResult])) {
                    $removeDataFilePath[] = $dataNames[$dataReferencesResult]['dataFilePath'];
                    unset($dataNames[$dataReferencesResult]);
                }
            }
        }
        $dataFilePathResult = $this->unsetFilePath($dataNames, $removeDataFilePath);
        return array_unique($dataFilePathResult);
    }

    /**
     * Remove used entities file path from unused entities array
     */
    private function unsetFilePath(array $dataNames, $removeDataFilePath): array
    {
        $dataFilePathResult = [];
        foreach ($dataNames as $key => $dataNamesResult) {
            if (in_array($dataNamesResult['dataFilePath'], $removeDataFilePath)) {
                unset($dataNames[$key]);
                continue;
            }
            $dataFilePathResult[] = $dataNames[$key]['dataFilePath'];
        }
        return  array_unique($dataFilePathResult);
    }

    /**
     * Return attribute value for each node in DOMNodeList as an array
     *
     * @param  DOMNodeList $nodes
     * @param  string      $attributeName
     */
    private function getAttributesFromDOMNodeList($nodes, array|string $attributeName): array
    {
        $attributes = [];
        foreach ($nodes as $node) {
            if (is_string($attributeName)) {
                $attributeValue = $node->getAttribute($attributeName);
            } else {
                $attributeValue = [$node->getAttribute(key($attributeName)) =>
                    $node->getAttribute($attributeName[key($attributeName)])];
            }
            if (!empty($attributeValue)) {
                $attributes[] = $attributeValue;
            }
        }
        return $attributes;
    }

    /**
     * Extract actionGroup DomElement from xml file
     */
    public function getActionGroupDomElement(string $contents): DOMElement
    {
        $domDocument = new \DOMDocument();
        $domDocument->loadXML($contents);
        return $domDocument->getElementsByTagName('actionGroup')[0];
    }

    /**
     * Return array containing all errors found after running the execute() function
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Return output
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }
}
