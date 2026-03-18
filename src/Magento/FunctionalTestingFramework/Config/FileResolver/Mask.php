<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config\FileResolver;

use Magento\FunctionalTestingFramework\Config\FileResolverInterface;
use Magento\FunctionalTestingFramework\Util\Iterator\File;
use Magento\FunctionalTestingFramework\Util\ModuleResolver;

/**
 * Class Mask
 * @package Magento\FunctionalTestingFramework\Config\FileResolver
 */
class Mask implements FileResolverInterface
{
    /**
     * Resolves module paths based on enabled modules of target Magento instance.
     */
    protected ?\Magento\FunctionalTestingFramework\Util\ModuleResolver $moduleResolver;

    /**
     * Constructor
     */
    public function __construct(?ModuleResolver $moduleResolver = null)
    {
        if ($moduleResolver) {
            $this->moduleResolver = $moduleResolver;
        } else {
            $this->moduleResolver = ModuleResolver::getInstance();
        }
    }

    /**
     * Retrieve the list of configuration files with given name that relate to specified scope
     *
     * @param string $filename
     * @param string $scope
     * @return array|\Iterator,\Countable
     */
    public function get($filename, $scope): \Magento\FunctionalTestingFramework\Util\Iterator\File
    {
        $paths = $this->getFileCollection($filename, $scope);

        return new File($paths);
    }

    /**
     * Get scope of paths.
     *
     * @param string $filename
     * @return array
     */
    protected function getFileCollection($filename, string $scope)
    {
        $paths = [];
        $modulesPath = $this->moduleResolver->getModulesPath();

        foreach ($modulesPath as $modulePath) {
            $path = $modulePath . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR;
            if (is_readable($path)) {
                $directoryIterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $path,
                        \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
                    )
                );
                $regexpIterator = new \RegexIterator($directoryIterator, $filename);
                /** @var \SplFileInfo $file */
                foreach ($regexpIterator as $file) {
                    if ($file->isFile() && $file->isReadable()) {
                        $paths[] = $file->getRealPath();
                    }
                }
            }
        }

        return $this->moduleResolver->sortFilesByModuleSequence($paths);
    }
}
