<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\ObjectManager\Config\Reader;

/**
 * Factory class for \Magento\FunctionalTestingFramework\ObjectManager\Config\Reader\Dom
 */
class DomFactory
{
    /**
     * Factory constructor
     *
     * @param string                                                     $instanceName
     */
    public function __construct(
        /**
         * Object Manager instance
         */
        protected \Magento\FunctionalTestingFramework\ObjectManagerInterface $objectManager,
        /**
         * Instance name to create
         */
        protected $instanceName = \Magento\FunctionalTestingFramework\ObjectManager\Config\Reader\Dom::class
    )
    {
    }

    /**
     * Create class instance with specified parameters
     *
     * @return \Magento\FunctionalTestingFramework\ObjectManager\Config\Reader\Dom
     */
    public function create(array $data = [])
    {
        return $this->objectManager->create($this->instanceName, $data);
    }
}
