<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Class Reader
 * Module declaration reader. Reads scenario.xml declaration files from module /etc directories.
 */
class Reader extends \Magento\Functional_Testing_Framework\Config\Reader\Filesystem
{
    /**
     * List of name attributes for merge.
     *
     * @var array
     */
    protected $id_attributes = ['/scenarios/scenario' => 'name', '/scenarios/scenario/methods/method' => 'name', '/scenarios/scenario/methods/method/steps/step' => 'name'];
    /**
     * Reader constructor.
     * @param string                   $fileName
     * @param array                    $idAttributes
     * @param string                   $domDocumentClass
     * @param string                   $defaultScope
     */
    public function __construct(File_Resolver_Interface $file_resolver, Converter_Interface $converter, Schema_Locator_Interface $schema_locator, Validation_State_Interface $validation_state, $file_name = 'scenario.xml', $id_attributes = [], $dom_document_class = Magento\Functional_Testing_Framework\Config\Dom::class, $default_scope = 'etc')
    {
        $this->file_resolver = $file_resolver;
        $this->converter = $converter;
        $this->file_name = $file_name;
        $this->id_attributes = array_replace($this->id_attributes, $id_attributes);
        $this->schema_file = $schema_locator->get_schema();
        $is_validated = $validation_state->is_validated();
        $this->per_file_schema = $schema_locator->get_per_file_schema() && $is_validated ? $schema_locator->get_per_file_schema() : null;
        $this->dom_document_class = $dom_document_class;
        $this->default_scope = $default_scope;
    }
}