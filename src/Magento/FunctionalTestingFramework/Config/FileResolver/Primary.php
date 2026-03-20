<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\File_Resolver;

use Magento\Functional_Testing_Framework\Config\File_Resolver_Interface;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Iterator\File;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
/**
 * Provides the list of global configuration files.
 *
 * @internal
 */
class Primary implements File_Resolver_Interface
{
    /**
     * Retrieve the configuration files with given name that relate to configuration
     *
     * @param string $filename
     * @param string $scope
     * @return array
     */
    public function get($filename, $scope): array|\Magento\Functional_Testing_Framework\Util\Iterator\File
    {
        if (!$filename) {
            return [];
        }
        $scope = str_replace('\\', DIRECTORY_SEPARATOR, $scope);
        return new File($this->get_file_paths($filename, $scope));
    }
    /**
     * Get list of configuration files
     *
     * @param string $filename
     * @param string $scope
     */
    private function get_file_paths($filename, string|array $scope): array
    {
        $paths = [];
        foreach ($this->get_path_patterns($filename, $scope) as $pattern) {
            $paths = array_merge($paths, glob($pattern));
        }
        return array_combine($paths, $paths);
    }
    /**
     * Retrieve patterns for glob function
     *
     * @throws TestFrameworkException
     */
    private function get_path_patterns(string $filename, string $scope): array
    {
        if (str_starts_with($scope, FW_BP)) {
            $patterns = [$scope . DIRECTORY_SEPARATOR . $filename, $scope . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $filename];
        } else {
            $default_path = dirname(__DIR__, 4);
            $default_path = str_replace('\\', DIRECTORY_SEPARATOR, $default_path);
            $patterns = [$default_path . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR . $filename, $default_path . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $filename, File_Path_Formatter::format(FW_BP) . $scope . DIRECTORY_SEPARATOR . $filename, File_Path_Formatter::format(FW_BP) . $scope . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $filename];
        }
        return str_replace(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $patterns);
    }
}