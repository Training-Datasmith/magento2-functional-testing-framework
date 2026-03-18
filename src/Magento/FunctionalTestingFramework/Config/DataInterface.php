<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config;

/**
 * Interface DataInterface
 */
interface DataInterface
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
    public function setFileName($fileName);
}
