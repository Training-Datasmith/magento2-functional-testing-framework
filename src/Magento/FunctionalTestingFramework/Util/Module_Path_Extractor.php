<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util;

/**
 * Class ModulePathExtractor, resolve module reference based on path
 */
class Module_Path_Extractor
{
    public const SPLIT_DELIMITER = '_';
    /**
     * Test module paths
     *
     * @var array
     */
    private $test_module_paths = [];
    /**
     * ModulePathExtractor constructor
     */
    public function __construct()
    {
        $verbose_path = true;
        if (empty($this->test_module_paths)) {
            $this->test_module_paths = Module_Resolver::get_instance()->get_modules_path($verbose_path);
        }
    }
    /**
     * Extracts module name from the path given
     *
     * @param string $path
     * @return string
     */
    public function extract_module_name($path)
    {
        $key = $this->extract_key_by_path($path);
        if (empty($key)) {
            return 'NO MODULE DETECTED';
        }
        $parts = $this->split_key_for_parts($key);
        return $parts[1] ?? 'NO MODULE DETECTED';
    }
    /**
     * Extracts vendor name for module from the path given
     *
     * @param string $path
     * @return string
     */
    public function get_extension_path($path)
    {
        $key = $this->extract_key_by_path($path);
        if (empty($key)) {
            return 'NO VENDOR DETECTED';
        }
        $parts = $this->split_key_for_parts($key);
        return $parts[0] ?? 'NO VENDOR DETECTED';
    }
    /**
     * Split key by SPLIT_DELIMITER and return parts array
     *
     * @param string $key
     */
    private function split_key_for_parts($key): array
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
    private function extract_key_by_path($path)
    {
        $shortened_path = dirname($path, 2);
        // Ignore this path if we cannot go to parent directory two levels up
        if (empty($shortened_path) || $shortened_path === '.') {
            return '';
        }
        foreach ($this->test_module_paths as $key => $value) {
            if (str_starts_with($path, (string) $value)) {
                return $key;
            }
        }
        return '';
    }
}