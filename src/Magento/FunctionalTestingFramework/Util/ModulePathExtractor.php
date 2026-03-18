<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util;

/**
 * Class ModulePathExtractor, resolve module reference based on path
 */
class ModulePathExtractor
{
    const SPLIT_DELIMITER = '_';

    /**
     * Test module paths
     *
     * @var array
     */
    private $testModulePaths = [];

    /**
     * ModulePathExtractor constructor
     */
    public function __construct()
    {
        $verbosePath = true;
        if (empty($this->testModulePaths)) {
            $this->testModulePaths = ModuleResolver::getInstance()->getModulesPath($verbosePath);
        }
    }

    /**
     * Extracts module name from the path given
     *
     * @param string $path
     * @return string
     */
    public function extractModuleName($path)
    {
        $key = $this->extractKeyByPath($path);
        if (empty($key)) {
            return "NO MODULE DETECTED";
        }
        $parts = $this->splitKeyForParts($key);
        return $parts[1] ?? "NO MODULE DETECTED";
    }

    /**
     * Extracts vendor name for module from the path given
     *
     * @param string $path
     * @return string
     */
    public function getExtensionPath($path)
    {
        $key = $this->extractKeyByPath($path);
        if (empty($key)) {
            return "NO VENDOR DETECTED";
        }
        $parts = $this->splitKeyForParts($key);
        return $parts[0] ?? "NO VENDOR DETECTED";
    }

    /**
     * Split key by SPLIT_DELIMITER and return parts array
     *
     * @param string $key
     */
    private function splitKeyForParts($key): array
    {
        $parts = explode(self::SPLIT_DELIMITER, $key);
        return count($parts) === 2 ? $parts : [];
    }

    /**
     * Extract module name key by path
     *
     * @param string $path
     * @return string
     */
    private function extractKeyByPath($path)
    {
        $shortenedPath = dirname($path, 2);
        // Ignore this path if we cannot go to parent directory two levels up
        if (empty($shortenedPath) || $shortenedPath === '.') {
            return '';
        }

        foreach ($this->testModulePaths as $key => $value) {
            if (str_starts_with($path, (string) $value)) {
                return $key;
            }
        }
        return '';
    }
}
