<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config\FileResolver;

use Magento\FunctionalTestingFramework\Config\FileResolverInterface;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\Iterator\File;
use Magento\FunctionalTestingFramework\Util\Path\FilePathFormatter;

/**
 * Provides the list of global configuration files.
 *
 * @internal
 */
class Primary implements FileResolverInterface
{
    /**
     * Retrieve the configuration files with given name that relate to configuration
     *
     * @param string $filename
     * @param string $scope
     * @return array
     */
    public function get($filename, $scope): array|\Magento\FunctionalTestingFramework\Util\Iterator\File
    {
        if (!$filename) {
            return [];
        }
        $scope = str_replace('\\', DIRECTORY_SEPARATOR, $scope);
        return new File($this->getFilePaths($filename, $scope));
    }

    /**
     * Get list of configuration files
     *
     * @param string $filename
     * @param string $scope
     */
    private function getFilePaths($filename, string|array $scope): array
    {
        $paths = [];
        foreach ($this->getPathPatterns($filename, $scope) as $pattern) {
            $paths = array_merge($paths, glob($pattern));
        }
        return array_combine($paths, $paths);
    }

    /**
     * Retrieve patterns for glob function
     *
     * @throws TestFrameworkException
     */
    private function getPathPatterns(string $filename, string $scope): array
    {
        if (str_starts_with($scope, FW_BP)) {
            $patterns = [
                $scope . DIRECTORY_SEPARATOR . $filename,
                $scope . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $filename,
            ];
        } else {
            $defaultPath = dirname(__DIR__, 4);
            $defaultPath = str_replace('\\', DIRECTORY_SEPARATOR, $defaultPath);
            $patterns = [
                $defaultPath . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR . $filename,
                $defaultPath . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR
                . $filename,
                FilePathFormatter::format(FW_BP) . $scope . DIRECTORY_SEPARATOR . $filename,
                FilePathFormatter::format(FW_BP)  . $scope . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR
                . $filename,
            ];
        }
        return str_replace(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $patterns);
    }
}
