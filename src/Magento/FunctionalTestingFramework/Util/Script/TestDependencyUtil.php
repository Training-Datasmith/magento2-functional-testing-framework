<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util\Script;

use Magento\FunctionalTestingFramework\Test\Handlers\TestObjectHandler;
use Magento\FunctionalTestingFramework\Filter\FilterList;
use Magento\FunctionalTestingFramework\Config\MftfApplicationConfig;

/**
 * TestDependencyUtil class that contains helper functions for static and upgrade scripts
 *
 * @package Magento\FunctionalTestingFramework\Util\Script
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TestDependencyUtil
{
    /**
     * Array of FullModuleName => [dependencies]
     */
    private ?array $allDependencies = null;

    /**
     * Transactional Array to keep track of what dependencies have already been extracted.
     */
    private ?array $alreadyExtractedDependencies = null;

    /**
     * Builds and returns array of FullModuleNae => composer name
     */
    public function buildModuleNameToComposerName(array $moduleNameToPath): array
    {
        $moduleNameToComposerName = [];
        foreach ($moduleNameToPath as $moduleName => $path) {
            $composerData = json_decode(file_get_contents($path . DIRECTORY_SEPARATOR . "composer.json"));
            $moduleNameToComposerName[$moduleName] = $composerData->name;
        }
        return $moduleNameToComposerName;
    }

    /**
     * Builds and returns flattened dependency list based on composer dependencies
     */
    public function buildComposerDependencyList(array $moduleNameToPath, array $moduleNameToComposerName): array
    {
        $flattenedDependencies = [];

        foreach ($moduleNameToPath as $moduleName => $pathToModule) {
            $composerData = json_decode(
                file_get_contents($pathToModule . DIRECTORY_SEPARATOR . "composer.json"),
                true
            );
            $this->allDependencies[$moduleName] = $composerData['require'];
        }
        foreach ($this->allDependencies as $moduleName => $dependencies) {
            $this->alreadyExtractedDependencies = [];
            $flattenedDependencies[$moduleName] = $this->extractSubDependencies($moduleName, $moduleNameToComposerName);
        }
        return $flattenedDependencies;
    }

    /**
     * Recursive function to fetch dependencies of given dependency, and its child dependencies
     */
    private function extractSubDependencies(string $subDependencyName, array $moduleNameToComposerName): array
    {
        $flattenedArray = [];

        if (in_array($subDependencyName, $this->alreadyExtractedDependencies)) {
            return $flattenedArray;
        }

        if (isset($this->allDependencies[$subDependencyName])) {
            $subDependencyArray = $this->allDependencies[$subDependencyName];
            $flattenedArray = array_merge($flattenedArray, $this->allDependencies[$subDependencyName]);

            // Keep track of dependencies that have already been used, prevents circular dependency problems
            $this->alreadyExtractedDependencies[] = $subDependencyName;
            foreach ($subDependencyArray as $composerDependencyName => $version) {
                $subDependencyFullName = array_search($composerDependencyName, $moduleNameToComposerName);
                $flattenedArray = array_merge(
                    $flattenedArray,
                    $this->extractSubDependencies($subDependencyFullName, $moduleNameToComposerName)
                );
            }
        }
        return $flattenedArray;
    }

    /**
     * Finds unique array composer dependencies of given testObjects
     */
    public function getModuleDependenciesFromReferences(
        array $allEntities,
        array $moduleComposerName,
        array $moduleNameToPath
    ): array {
        $filenames = [];
        foreach ($allEntities as $item) {
            // Should it append ALL filenames, including merges?
            $allFiles = explode(",", (string) $item->getFilename());
            foreach ($allFiles as $file) {
                $moduleName = $this->getModuleName($file, $moduleNameToPath);
                if (isset($moduleComposerName[$moduleName])) {
                    $composerModuleName = $moduleComposerName[$moduleName];
                    $filenames[$item->getName()][] = $composerModuleName;
                }
            }
        }
        return $filenames;
    }

    /**
     * Return module name for a file path
     */
    public function getModuleName(string $filePath, array $moduleNameToPath): ?string
    {
        $moduleName = null;
        foreach ($moduleNameToPath as $name => $path) {
            if (str_contains($filePath, $path. "/")) {
                $moduleName = $name;
                break;
            }
        }
        return $moduleName;
    }

    /**
     * Return array of merge test modules and file path with same test name.
     */
    public function mergeDependenciesForExtendingTests(
        array $testDependencies,
        array $filterList,
        array $extendedTestMapping = []
    ): array {
        $testObjects = TestObjectHandler::getInstance()->getAllObjects();
        $filters = MftfApplicationConfig::getConfig()->getFilterList()->getFilters();
        $filteredTestNames = (count($filterList)>0)?$this->getFilteredTestNames($testObjects, $filters):[];
        $temp_array = array_reverse(array_column($testDependencies, "test_name"), true);
        foreach ($extendedTestMapping as $value) {
            $key = array_search($value["parent_test_name"], $temp_array);
            if ($key !== false) {
                #if parent test found merge this to child, for doing so just replace test name with child.
                $testDependencies[$key]["test_name"] = $value["child_test_name"];
            }
        }
        $temp_array = [];
        foreach ($testDependencies as $testDependency) {
            $temp_array[$testDependency["test_name"]][] = $testDependency;
        }
        $testDependencies = [];
        foreach ($temp_array as $testDependencyArray) {
            if ((
                empty($filterList)) ||
                isset($filteredTestNames[$testDependencyArray[0]["test_name"]])
            ) {
                $testDependencies[] = [
                    "file_path" => array_column($testDependencyArray, 'file_path'),
                    "full_name" => $testDependencyArray[0]["full_name"],
                    "test_name" => $testDependencyArray[0]["test_name"],
                    "test_modules" => array_values(
                        array_unique(
                            call_user_func_array(
                                array_merge(...),
                                array_column($testDependencyArray, 'test_modules')
                            )
                        )
                    ),
                ];
            }
        }
        return $testDependencies;
    }

    /**
     * Return array of merge test modules and file path with same test name.
     */
    public function getFilteredTestNames(array $testObjects, array $filters) : array
    {
        foreach ($filters as $filter) {
            $filter->filter($testObjects);
        }
        return array_map(fn($testObjects) => $testObjects->getName(), $testObjects);
    }
}
