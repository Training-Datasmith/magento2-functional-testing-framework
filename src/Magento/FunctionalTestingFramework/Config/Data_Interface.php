<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Interface DataInterface
 */
interface Data_Interface
{
    /**
     * Merge config data to the object
     *
     * @return void
     */
    public function merge(array $config);
    // @codingStandardsIgnoreStart
    /**
     * Get config value by key
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed|null
     */
    public function get(mixed $key = null, mixed $default = null);
    // @codingStandardsIgnoreEnd
    /**
     * Load config data
     *
     * @return void
     */
    public function load(?string $scope = null);
    /**
     * Set name of the config file
     *
     * @param string $fileName
     * @return self
     */
    public function set_file_name($file_name);
}