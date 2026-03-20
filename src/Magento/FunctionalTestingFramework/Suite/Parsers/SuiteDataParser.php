<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Suite\Parsers;

use Magento\Functional_Testing_Framework\Config\Data_Interface;
class Suite_Data_Parser
{
    /**
     * TestDataParser constructor.
     */
    public function __construct(
        /**
         * Suite data interface for parser.
         */
        private readonly Data_Interface $suite_data
    )
    {
    }
    /**
     * Returns an array of data based on *Test.xml files
     *
     * @return array
     */
    public function read_suite_data()
    {
        return $this->suite_data->get();
    }
}