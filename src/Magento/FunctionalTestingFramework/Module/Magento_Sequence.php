<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
// @codingStandardsIgnoreFile
namespace Magento\Functional_Testing_Framework\Module;

use Codeception\Exception\Module_Exception;
use Magento\Functional_Testing_Framework\Codeception\Module\Sequence;
/**
 * MagentoSequence module.
 *
 */
class Magento_Sequence extends Sequence
{
    protected array $config = ['prefix' => ''];
}
if (!function_exists('msq') && !function_exists('msqs')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Util' . DIRECTORY_SEPARATOR . 'msq.php';
} else {
    throw new Module_Exception(\Magento\Functional_Testing_Framework\Module\Magento_Sequence::class, "function 'msq' and 'msqs' already defined");
}