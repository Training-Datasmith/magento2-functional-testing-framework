<?php

/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Helper;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
/**
 * Class HelperContainer
 */
class Helper_Container extends \Codeception\Module
{
    /**
     * @var Helper[]
     */
    private array $helpers = [];
    /**
     * Create custom helper class.
     *
     * @throws \Exception
     */
    public function create(string $helper_class): Helper
    {
        if (get_parent_class($helper_class) !== Helper::class) {
            throw new \Exception('Helper class must extend ' . Helper::class);
        }
        if (!isset($this->helpers[$helper_class])) {
            $this->helpers[$helper_class] = $this->module_container->create($helper_class);
        }
        return $this->helpers[$helper_class];
    }
    /**
     * Returns helper object by it's class name.
     *
     * @throws TestFrameworkException
     */
    public function get(string $class_name): Helper
    {
        if ($this->has($class_name)) {
            return $this->helpers[$class_name];
        }
        throw new Test_Framework_Exception('Custom helper ' . $class_name . 'not found.');
    }
    /**
     * Verifies that helper object exist.
     */
    public function has(string $class_name): bool
    {
        return array_key_exists($class_name, $this->helpers);
    }
}