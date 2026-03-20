<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface;

/**
 * Proxy class for \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
 */
class Proxy implements \Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface
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
        protected \Magento\Functional_Testing_Framework\Object_Manager_Interface $object_manager,
        /**
         * Proxied instance name
         */
        protected $instance_name = \Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface::class,
        /**
         * Instance shareability flag
         */
        protected $is_shared = true
    )
    {
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
        $this->object_manager = \Magento\Functional_Testing_Framework\Object_Manager::get_instance();
    }
    /**
     * Clone proxied instance
     */
    public function __clone()
    {
        $this->subject = clone $this->get_subject();
    }
    /**
     * Get proxied instance
     *
     * @return \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
     */
    protected function get_subject()
    {
        if (!$this->subject) {
            $this->subject = true === $this->is_shared ? $this->object_manager->get($this->instance_name) : $this->object_manager->create($this->instance_name);
        }
        return $this->subject;
    }
    /**
     * {@inheritdoc}
     * @return mixed
     */
    public function evaluate(array $data)
    {
        return $this->get_subject()->evaluate($data);
    }
}