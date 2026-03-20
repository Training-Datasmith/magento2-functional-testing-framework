<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Xml_Parser;

/**
 * Generic Xml Parser.
 */
class Section_Parser implements Parser_Interface
{
    /**
     * SectionParser constructor.
     */
    public function __construct(
        /**
         * Object manager.
         *
         * @var \Magento\FunctionalTestingFramework\ObjectManager
         */
        protected \Magento\Functional_Testing_Framework\Object_Manager_Interface $object_manager,
        /**
         * Configuration data.
         */
        protected \Magento\Functional_Testing_Framework\Config\Data_Interface $config_data
    )
    {
    }
    /**
     * Get parsed xml data.
     *
     * @param string $type
     * @return array
     */
    public function get_data($type)
    {
        return $this->config_data->get($type);
    }
}