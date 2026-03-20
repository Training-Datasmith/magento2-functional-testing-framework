<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Xml_Parser;

/**
 * Interface for retrieving parser data.
 */
interface Parser_Interface
{
    /**
     * Get parsed xml data.
     *
     * @param string $type
     * @return array
     */
    public function get_data($type);
}