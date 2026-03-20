<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Config\Reader;

/**
 * Class Dom
 *
 * @internal
 */
// @codingStandardsIgnoreFile
class Dom extends \Magento\Functional_Testing_Framework\Config\Reader\Filesystem
{
    /**
     * Name of an attribute that stands for data type of node values
     */
    public const TYPE_ATTRIBUTE = 'xsi:type';
    /**
     * Dom constructor.
     * @param string $fileName
     * @param array $idAttributes
     * @param string $domDocumentClass
     * @param string $defaultScope
     */
    public function __construct(\Magento\Functional_Testing_Framework\Config\File_Resolver_Interface $file_resolver, \Magento\Functional_Testing_Framework\Object_Manager\Config\Mapper\Dom $converter, \Magento\Functional_Testing_Framework\Object_Manager\Config\Schema_Locator $schema_locator, \Magento\Functional_Testing_Framework\Config\Validation_State_Interface $validation_state, $file_name = 'di.xml', $id_attributes = ['/config/preference' => 'for', '/config/(type|virtualType)' => 'name', '/config/(type|virtualType)/arguments/argument' => 'name', '/config/(type|virtualType)/arguments/argument(/item)+' => 'name'], $dom_document_class = \Magento\Functional_Testing_Framework\Config\Dom::class, $default_scope = 'etc')
    {
        parent::__construct($file_resolver, $converter, $schema_locator, $validation_state, $file_name, $id_attributes, $dom_document_class, $default_scope);
    }
    /**
     * Create and return a config merger instance that takes into account types of arguments
     *
     * @param string $mergerClass
     * @param string $initialContents
     * @return \Magento\FunctionalTestingFramework\Config\Dom
     */
    protected function _create_config_merger($merger_class, $initial_contents)
    {
        return new $merger_class($initial_contents, $this->id_attributes, self::TYPE_ATTRIBUTE, $this->per_file_schema);
    }
}