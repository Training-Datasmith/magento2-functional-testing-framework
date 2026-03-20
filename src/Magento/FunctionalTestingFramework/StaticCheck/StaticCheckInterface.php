<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Symfony\Component\Console\Input\Input_Interface;
/**
 * Static check script interface
 */
interface Static_Check_Interface
{
    /**
     * Executes static check script, returns output.
     * @return void
     */
    public function execute(Input_Interface $input);
    /**
     * Return array containing all errors found after running the execute() function.
     * @return array
     */
    public function get_errors();
    /**
     * Return string of a short human readable result of the check. For example: "No Dependency errors found."
     * @return string
     */
    public function get_output();
}