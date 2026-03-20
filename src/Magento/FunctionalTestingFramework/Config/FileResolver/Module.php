<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\File_Resolver;

use Magento\Functional_Testing_Framework\Config\File_Resolver_Interface;
use Magento\Functional_Testing_Framework\Util\Iterator\File;
use Magento\Functional_Testing_Framework\Util\Module_Resolver;
/**
 * Provides the list of configuration files collected through modules test folders.
 */
class Module implements File_Resolver_Interface
{
    /**
     * Resolves module paths based on enabled modules of target Magento instance.
     *
     * @var ModuleResolver
     */
    protected $module_resolver;
    /**
     * Module constructor.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct()
    {
        $this->module_resolver = Module_Resolver::get_instance();
    }
    /**
     * Retrieve the list of configuration files with given name that relate to specified scope.
     *
     * @param string $filename
     * @param string $scope
     * @return array|\Iterator,\Countable
     */
    public function get($filename, $scope): \Magento\Functional_Testing_Framework\Util\Iterator\File
    {
        return new File($this->get_paths($filename, $scope));
    }
    /**
     * Function which takes a string representing filename and a scope represnting directory scope to glob for matched
     * patterns against. Returns the file matching the patterns given by the module resolver.
     */
    protected function get_paths(string $filename, string $scope): array
    {
        $modules_path = $this->module_resolver->get_modules_path();
        $paths = [];
        foreach ($modules_path as $module_path) {
            $path = $module_path . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR . $filename;
            $paths = array_merge($paths, glob($path));
        }
        return $paths;
    }
}