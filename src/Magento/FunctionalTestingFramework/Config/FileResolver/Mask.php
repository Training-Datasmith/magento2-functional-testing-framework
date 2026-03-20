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
 * Class Mask
 * @package Magento\FunctionalTestingFramework\Config\FileResolver
 */
class Mask implements File_Resolver_Interface
{
    /**
     * Resolves module paths based on enabled modules of target Magento instance.
     */
    protected ?\Magento\Functional_Testing_Framework\Util\Module_Resolver $module_resolver;
    /**
     * Constructor
     */
    public function __construct(?Module_Resolver $module_resolver = null)
    {
        if ($module_resolver) {
            $this->module_resolver = $module_resolver;
        } else {
            $this->module_resolver = Module_Resolver::get_instance();
        }
    }
    /**
     * Retrieve the list of configuration files with given name that relate to specified scope
     *
     * @param string $filename
     * @param string $scope
     * @return array|\Iterator,\Countable
     */
    public function get($filename, $scope): \Magento\Functional_Testing_Framework\Util\Iterator\File
    {
        $paths = $this->get_file_collection($filename, $scope);
        return new File($paths);
    }
    /**
     * Get scope of paths.
     *
     * @param string $filename
     * @return array
     */
    protected function get_file_collection($filename, string $scope)
    {
        $paths = [];
        $modules_path = $this->module_resolver->get_modules_path();
        foreach ($modules_path as $module_path) {
            $path = $module_path . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR;
            if (is_readable($path)) {
                $directory_iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($path, \Filesystem_Iterator::SKIP_DOTS | \Filesystem_Iterator::FOLLOW_SYMLINKS));
                $regexp_iterator = new \Regex_Iterator($directory_iterator, $filename);
                /** @var \SplFileInfo $file */
                foreach ($regexp_iterator as $file) {
                    if ($file->is_file() && $file->is_readable()) {
                        $paths[] = $file->get_real_path();
                    }
                }
            }
        }
        return $this->module_resolver->sort_files_by_module_sequence($paths);
    }
}