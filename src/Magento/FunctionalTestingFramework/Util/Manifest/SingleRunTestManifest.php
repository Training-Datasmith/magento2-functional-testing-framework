<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Manifest;

class Single_Run_Test_Manifest extends Default_Test_Manifest
{
    public const SINGLE_RUN_CONFIG = 'singleRun';
    /**
     * SingleRunTestManifest constructor.
     *
     * @param array  $suiteConfiguration
     * @param string $testPath
     */
    public function __construct($suite_configuration, $test_path)
    {
        parent::__construct($suite_configuration, $test_path);
        $this->run_type_config = self::SINGLE_RUN_CONFIG;
    }
    /**
     * Function which generates the actual manifest once the relevant tests have been added to the array.
     */
    public function generate(): void
    {
        $file_resource = fopen($this->manifest_path, 'a');
        $line = $this->relative_dir_path . DIRECTORY_SEPARATOR;
        fwrite($file_resource, $line . PHP_EOL);
        $this->generate_suite_entries($file_resource);
        fclose($file_resource);
    }
}