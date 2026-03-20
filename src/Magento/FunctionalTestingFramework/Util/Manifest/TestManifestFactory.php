<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Manifest;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
class Test_Manifest_Factory
{
    /**
     * TestManifestFactory constructor.
     */
    private function __construct()
    {
        // private constructor
    }
    /**
     * Static function which takes path and config to return the appropriate manifest output type.
     *
     * @param string $runConfig
     * @param array  $suiteConfiguration
     * @return BaseTestManifest
     * @throws TestFrameworkException
     */
    public static function make_manifest($run_config, $suite_configuration, string $test_path = Test_Generator::DEFAULT_DIR): \Magento\Functional_Testing_Framework\Util\Manifest\Single_Run_Test_Manifest|\Magento\Functional_Testing_Framework\Util\Manifest\Parallel_By_Time_Test_Manifest|\Magento\Functional_Testing_Framework\Util\Manifest\Parallel_By_Group_Test_Manifest|\Magento\Functional_Testing_Framework\Util\Manifest\Default_Test_Manifest
    {
        $test_dir_full_path = File_Path_Formatter::format(TESTS_MODULE_PATH) . Test_Generator::GENERATED_DIR . DIRECTORY_SEPARATOR . $test_path;
        return match ($run_config) {
            'singleRun' => new Single_Run_Test_Manifest($suite_configuration, $test_dir_full_path),
            'parallelByTime' => new Parallel_By_Time_Test_Manifest($suite_configuration, $test_dir_full_path),
            'parallelByGroup' => new Parallel_By_Group_Test_Manifest($suite_configuration, $test_dir_full_path),
            default => new Default_Test_Manifest($suite_configuration, $test_dir_full_path),
        };
    }
}