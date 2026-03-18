<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface;

/**
 * Proxy class for \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
 */
class Proxy implements \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
{
    /**
     * Proxied instance
     *
     * @var \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
     */
    protected $subject;

    /**
     * Proxy constructor
     *
     * @param string                                                     $instanceName
     * @param boolean $isShared
     */
    public function __construct(
        /**
         * Object Manager instance
         */
        protected \Magento\FunctionalTestingFramework\ObjectManagerInterface $objectManager,
        /**
         * Proxied instance name
         */
        protected $instanceName = \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface::class,
        /**
         * Instance shareability flag
         */
        protected $isShared = true
    ) {
    }

    /**
     * Definition of field which should be serialized.
     *
     * @return array
     */
    public function __sleep()
    {
        return ['subject', 'isShared'];
    }

    /**
     * Retrieve ObjectManager from global scope
     * @return void
     */
    public function __wakeup()
    {
        $this->objectManager = \Magento\FunctionalTestingFramework\ObjectManager::getInstance();
    }

    /**
     * Clone proxied instance
     */
    public function __clone()
    {
        $this->subject = clone $this->getSubject();
    }

    /**
     * Get proxied instance
     *
     * @return \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
     */
    protected function getSubject()
    {
        if (!$this->subject) {
            $this->subject = true === $this->isShared
                ? $this->objectManager->get($this->instanceName)
                : $this->objectManager->create($this->instanceName);
        }
        return $this->subject;
    }

    /**
     * {@inheritdoc}
     * @return mixed
     */
    public function evaluate(array $data)
    {
        return $this->getSubject()->evaluate($data);
    }
}
