<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\File_Resolver;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Iterator\File;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
class Root extends Mask
{
    public const ROOT_SUITE_DIR = 'tests/_suite';
    /**
     * Retrieve the list of configuration files with given name that relate to specified scope at the root level as well
     * as any extension based suite configuration.
     *
     * @param string $filename
     * @param string $scope
     * @return array|\Iterator,\Countable
     * @throws TestFrameworkException
     */
    public function get($filename, $scope): \Magento\Functional_Testing_Framework\Util\Iterator\File
    {
        // First pick up the root level test suite dir
        $paths = glob(File_Path_Formatter::format(TESTS_BP) . self::ROOT_SUITE_DIR . DIRECTORY_SEPARATOR . '*.xml');
        // include root suite dir when running standalone version
        $alt_path = File_Path_Formatter::format(MAGENTO_BP) . 'dev/tests/acceptance';
        if (realpath($alt_path) && $alt_path !== TESTS_BP) {
            $paths = array_merge($paths, glob(File_Path_Formatter::format($alt_path) . self::ROOT_SUITE_DIR . DIRECTORY_SEPARATOR . '*.xml'));
        }
        // Then merge this path into the module based paths
        // Since we are sharing this code with Module based resolution we will unnecessarily glob against modules in the
        // dev/tests dir tree, however as we plan to migrate to app/code this will be a temporary unneeded check.
        $paths = array_merge($paths, $this->get_file_collection($filename, $scope));
        // create and return the iterator for these file paths
        $iterator = new File($paths);
        return $iterator;
    }
}