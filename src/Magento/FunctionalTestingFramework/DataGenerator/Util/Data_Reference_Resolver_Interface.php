<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Util;

interface Data_Reference_Resolver_Interface
{
    public const REFERENCE_REGEX_PATTERN = "/(?<reference>{{[\\w]+\\..+}})/";
    /**
     * @return mixed
     */
    public function get_data_reference(string $data, string $original_data_entity);
    /**
     * @return mixed
     */
    public function get_data_uniqueness(string $data, string $original_data_entity);
}