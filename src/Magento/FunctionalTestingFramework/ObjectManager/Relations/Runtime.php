<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\ObjectManager\Relations;

/**
 * Class Runtime
 */
class Runtime implements \Magento\FunctionalTestingFramework\ObjectManager\RelationsInterface
{
    /**
     * Class reader.
     */
    protected \Magento\FunctionalTestingFramework\Code\Reader\ClassReader $classReader;

    /**
     * Default behavior
     *
     * @var array
     */
    protected $default = [];

    /**
     * Runtime constructor.
     */
    public function __construct(?\Magento\FunctionalTestingFramework\Code\Reader\ClassReader $classReader = null)
    {
        $this->classReader = $classReader ? : new \Magento\FunctionalTestingFramework\Code\Reader\ClassReader();
    }

    /**
     * Check whether requested type is available for read
     *
     * @param string $type
     */
    public function has($type): bool
    {
        return class_exists($type) || interface_exists($type);
    }

    /**
     * Retrieve list of parents
     *
     * @param string $type
     * @return array
     */
    public function getParents($type)
    {
        if (!class_exists($type)) {
            return $this->default;
        }
        return $this->classReader->getParents($type) ? : $this->default;
    }
}
