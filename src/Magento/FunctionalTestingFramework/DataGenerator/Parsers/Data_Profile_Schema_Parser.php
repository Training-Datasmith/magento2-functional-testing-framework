<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Parsers;

use Magento\Functional_Testing_Framework\Config\Data_Interface;
/**
 * Class DataProfileSchemaParser
 */
class Data_Profile_Schema_Parser
{
    /**
     * DataProfileSchemaParser constructor.
     */
    public function __construct(
        /**
         * Data Profiles.
         */
        private readonly Data_Interface $data_profiles
    )
    {
    }
    /**
     * Function to return data as array from data.xml files
     *
     * @return array
     */
    public function read_data_profiles()
    {
        return $this->data_profiles->get();
    }
}