<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config\FileResolver;

use Magento\FunctionalTestingFramework\Util\Iterator\File;
use Magento\FunctionalTestingFramework\Config\FileResolverInterface;
use Magento\FunctionalTestingFramework\Util\ModuleResolver;

/**
 * Provides the list of configuration files collected through modules test folders.
 */
class Module implements FileResolverInterface
{
    /**
     * Resolves module paths based on enabled modules of target Magento instance.
     *
     * @var ModuleResolver
     */
    protected $moduleResolver;

    /**
     * Module constructor.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct()
    {
        $this->moduleResolver = ModuleResolver::getInstance();
    }

    /**
     * Retrieve the list of configuration files with given name that relate to specified scope.
     *
     * @param string $filename
     * @param string $scope
     * @return array|\Iterator,\Countable
     */
    public function get($filename, $scope): \Magento\FunctionalTestingFramework\Util\Iterator\File
    {
        return new File($this->getPaths($filename, $scope));
    }

    /**
     * Function which takes a string representing filename and a scope represnting directory scope to glob for matched
     * patterns against. Returns the file matching the patterns given by the module resolver.
     */
    protected function getPaths(string $filename, string $scope): array
    {
        $modulesPath = $this->moduleResolver->getModulesPath();
        $paths = [];
        foreach ($modulesPath as $modulePath) {
            $path = $modulePath . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR . $filename;
            $paths = array_merge($paths, glob($path));
        }

        return $paths;
    }
}
