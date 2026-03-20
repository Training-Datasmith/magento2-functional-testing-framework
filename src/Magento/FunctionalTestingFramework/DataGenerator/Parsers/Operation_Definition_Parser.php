<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Parsers;

use Magento\Functional_Testing_Framework\Config\Data_Interface;
/**
 * Class OperationDefinitionParser
 */
class Operation_Definition_Parser
{
    /**
     * MetadataParser constructor.
     */
    public function __construct(
        /**
         * Meta Data.
         */
        private readonly Data_Interface $metadata
    )
    {
    }
    /**
     * Returns an array containing all data read from operations.xml files.
     *
     * @return array
     */
    public function read_operation_metadata()
    {
        return $this->metadata->get();
    }
}