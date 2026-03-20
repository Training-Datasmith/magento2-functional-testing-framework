<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Console;

/**
 * Contains a list of Console commands
 * @api
 */
interface Command_List_Interface
{
    /**
     * Gets list of command instances
     *
     * @return \Symfony\Component\Console\Command\Command[]
     */
    public function get_commands();
}