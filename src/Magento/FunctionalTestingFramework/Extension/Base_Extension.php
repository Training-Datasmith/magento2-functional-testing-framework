<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Extension;

use Codeception\Events;
use Codeception\Exception\Module_Require_Exception;
use Codeception\Extension;
use Codeception\Module\Web_Driver;
/**
 * Class BaseExtension
 */
class Base_Extension extends Extension
{
    /**
     * Codeception Events Mapping to methods
     *
     * @var array
     */
    public static $events = [Events::TEST_BEFORE => 'beforeTest', Events::STEP_BEFORE => 'beforeStep'];
    /**
     * The current URI of the active page
     *
     * @var string
     */
    private $uri;
    /**
     * Codeception event listener function - initialize uri before test
     *
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function before_test(\Codeception\Event\Test_Event $e): void
    {
        $this->uri = null;
    }
    /**
     * Codeception event listener function - check for page uri change before step
     *
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function before_step(\Codeception\Event\Step_Event $e): void
    {
        $this->page_changed();
    }
    /**
     * WebDriver instance for execution
     *
     * @return WebDriver
     * @throws ModuleRequireException
     */
    public function get_driver()
    {
        return $this->get_module($this->config['driver']);
    }
    /**
     * Gets the active page URI from the start of the most recent step
     *
     * @return string
     */
    public function get_uri()
    {
        return $this->uri;
    }
    /**
     * Check if page uri has changed
     *
     * @return boolean
     */
    protected function page_changed()
    {
        try {
            if ($this->get_driver() === null) {
                return false;
            }
            $current_uri = $this->get_driver()->_get_current_uri();
            if ($this->uri !== $current_uri) {
                $this->uri = $current_uri;
                return true;
            }
        } catch (\Exception) {
            // just fall through and return false
        }
        return false;
    }
}