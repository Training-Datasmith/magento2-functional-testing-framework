<?php

declare (strict_types=1);
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Manifest;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
class Parallel_By_Group_Test_Manifest extends Base_Parallel_Test_Manifest
{
    public const PARALLEL_CONFIG = 'parallelByGroup';
    /**
     * ParallelByGroupTestManifest constructor.
     *
     * @param array  $suiteConfiguration
     * @param string $testPath
     */
    public function __construct($suite_configuration, $test_path)
    {
        parent::__construct($suite_configuration, self::PARALLEL_CONFIG, $test_path);
    }
    /**
     * Function which generates test groups based on arg passed.
     *
     * @param integer $totalGroups
     * @throws TestFrameworkException
     */
    public function create_test_groups($total_groups): void
    {
        $this->test_groups = $this->parallel_group_sorter->get_tests_grouped_by_fixed_group_count($this->get_suite_config(), $this->test_name_to_size, $total_groups);
        $this->suite_configuration = $this->parallel_group_sorter->get_resulting_suite_config();
    }
}