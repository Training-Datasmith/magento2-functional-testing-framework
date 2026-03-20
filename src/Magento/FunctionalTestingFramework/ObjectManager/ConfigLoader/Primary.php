<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Config_Loader;

/**
 * Class Primary
 * Primary DI configuration loader
 *
 * @internal
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
// @codingStandardsIgnoreFile
class Primary
{
    /**
     * Framework mode
     *
     * @var string
     */
    protected $app_mode = 'developer';
    /**
     * Load primary DI configuration
     *
     * @return array
     */
    public function load()
    {
        $reader = new \Magento\Functional_Testing_Framework\Object_Manager\Config\Reader\Dom(new \Magento\Functional_Testing_Framework\Config\File_Resolver\Primary(), new \Magento\Functional_Testing_Framework\Object_Manager\Config\Mapper\Dom($this->create_argument_interpreter()), new \Magento\Functional_Testing_Framework\Object_Manager\Config\Schema_Locator(), new \Magento\Functional_Testing_Framework\Config\Validation_State($this->app_mode));
        return $reader->read();
    }
    /**
     * Return newly created instance on an argument interpreter, suitable for processing DI arguments
     *
     * @return \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
     */
    protected function create_argument_interpreter(): \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Composite
    {
        $boolean_utils = new \Magento\Functional_Testing_Framework\Stdlib\Boolean_Utils();
        $const_interpreter = new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Constant();
        $result = new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Composite(['boolean' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Boolean($boolean_utils), 'string' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\String_Utils($boolean_utils), 'number' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Number(), 'null' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Null_Type(), 'object' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Data_Object($boolean_utils), 'const' => $const_interpreter, 'init_parameter' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Argument($const_interpreter)], \Magento\Functional_Testing_Framework\Object_Manager\Config\Reader\Dom::TYPE_ATTRIBUTE);
        // Add interpreters that reference the composite
        $result->add_interpreter('array', new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Array_Type($result));
        return $result;
    }
}