<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport;

/**
 * Curl executor for Magento Web Api requests that do not require authorization.
 */
class Web_Api_No_Auth_Executor extends Web_Api_Executor
{
    /**
     * No authorization is needed and just return.
     *
     * @return void
     */
    protected function authorize()
    {
        //NOP
    }
}