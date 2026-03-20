<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Upgrade;

use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Upgrade script interface
 */
interface Upgrade_Interface
{
    /**
     * Executes upgrade script, returns output.
     * @return string
     */
    public function execute(Input_Interface $input, Output_Interface $output);
}