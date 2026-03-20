<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
use Magento\Functional_Testing_Framework\Module\Magento_Sequence;
if (!function_exists('msq')) {
    /**
     * Return unique sequence within test.
     *
     * @return string
     */
    function msq(?string $id = null)
    {
        if ($id and isset(Magento_Sequence::$hash[$id])) {
            return Magento_Sequence::$hash[$id];
        }
        $prefix = Magento_Sequence::$prefix;
        $sequence = $prefix . uniqid();
        if ($id) {
            Magento_Sequence::$hash[$id] = $sequence;
        }
        return $sequence;
    }
}
if (!function_exists('msqs')) {
    /**
     * Return unique sequence within suite.
     *
     * @return string
     */
    function msqs(?string $id = null)
    {
        if ($id and isset(Magento_Sequence::$suite_hash[$id])) {
            return Magento_Sequence::$suite_hash[$id];
        }
        $prefix = Magento_Sequence::$prefix;
        $sequence = $prefix . uniqid();
        if ($id) {
            Magento_Sequence::$suite_hash[$id] = $sequence;
        }
        return $sequence;
    }
}