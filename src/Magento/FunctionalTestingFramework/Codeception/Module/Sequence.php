<?php

/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Codeception\Module;

use Codeception\Module;
use Codeception\Test_Interface;
/**
 * Class Sequence
 * Implemented here as a replacement for codeception/module-sequence due to PHP 8.4 deprecation errors.
 * This class can be removed when PHP 8.4 compatibility is updated in codeception/module-sequence.
 */
class Sequence extends Module
{
    /**
     * @var array<int|string,string>
     */
    public static array $hash = [];
    // phpcs:ignore
    /**
     * @var array<int|string,string>
     */
    public static array $suite_hash = [];
    // phpcs:ignore
    public static string $prefix = '';
    // phpcs:ignore
    /**
     * @var array<string, string>
     */
    protected array $config = ['prefix' => '{id}_'];
    // phpcs:ignore
    /**
     * Initialise method
     */
    public function _initialize(): void
    {
        static::$prefix = $this->config['prefix'];
    }
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * after method
     */
    public function _after(Test_Interface $test): void
    {
        self::$hash = [];
    }
    /**
     * after suite method
     */
    public function _after_suite(): void
    {
        self::$suite_hash = [];
    }
}