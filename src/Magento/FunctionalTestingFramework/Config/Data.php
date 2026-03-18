<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config;

/**
 * Class Data
 */
class Data implements \Magento\FunctionalTestingFramework\Config\DataInterface
{
    /**
     * Config data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Constructor
     */
    public function __construct(/**
     * Configuration reader model
     */
        protected \Magento\FunctionalTestingFramework\Config\ReaderInterface $reader
    ) {
        $this->load();
    }

    /**
     * Merge config data to the object
     */
    public function merge(array $config): void
    {
        $this->data = array_replace_recursive($this->data, $config);
    }

    // @codingStandardsIgnoreStart
    /**
     * Get config value by key
     *
     * @param string $path
     *
     * @param null|mixed $default
     * @return array|mixed|null
     */
    public function get(mixed $path = null, mixed $default = null)
    {
        if ($path === null) {
            return $this->data;
        }
        $keys = explode('/', $path);
        $data = $this->data;
        foreach ($keys as $key) {
            if (is_array($data) && array_key_exists($key, $data)) {
                $data = $data[$key];
            } else {
                return $default;
            }
        }
        return $data;
    }
    // @codingStandardsIgnoreEnd
    /**
     * Set name of the config file
     *
     * @param string $fileName
     */
    public function setFileName($fileName): static
    {
        if ($fileName !== null) {
            $this->reader->setFileName($fileName);
        }
        return $this;
    }

    /**
     * Load config data
     */
    public function load(?string $scope = null): void
    {
        $this->merge(
            $this->reader->read($scope)
        );
    }
}
