<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

/**
 * Contains a list of Static Check Scripts
 * @api
 */
interface Static_Check_List_Interface
{
    /**
     * Gets list of static check script instances
     *
     * @return StaticCheckInterface[]
     */
    public function get_static_checks();
}