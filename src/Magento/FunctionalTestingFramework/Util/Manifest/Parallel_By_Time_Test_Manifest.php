<?php

declare (strict_types=1);
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Manifest;

class Parallel_By_Time_Test_Manifest extends Base_Parallel_Test_Manifest
{
    public const PARALLEL_CONFIG = 'parallelByTime';
    /**
     * GroupBasedParallelTestManifest constructor.
     *
     * @param array  $suiteConfiguration
     * @param string $testPath
     */
    public function __construct($suite_configuration, $test_path)
    {
        parent::__construct($suite_configuration, self::PARALLEL_CONFIG, $test_path);
    }
    /**
     * Function which generates test groups based on arg passed. The function builds groups using the args as an upper
     * limit.
     *
     * @param integer $time
     */
    public function create_test_groups($time): void
    {
        $this->test_groups = $this->parallel_group_sorter->get_tests_grouped_by_size($this->get_suite_config(), $this->test_name_to_size, $time);
        $this->suite_configuration = $this->parallel_group_sorter->get_resulting_suite_config();
    }
}