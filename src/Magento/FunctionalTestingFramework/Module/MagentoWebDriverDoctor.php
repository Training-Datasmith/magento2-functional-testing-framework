<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Module;

use Facebook\Web_Driver\Remote\Remote_Web_Driver;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
/**
 * MagentoWebDriverDoctor module extends MagentoWebDriver module and is a light weighted module to diagnose webdriver
 * initialization and other setup issues. It uses in memory version of MagentoWebDriver's configuration file.
 */
class Magento_Web_Driver_Doctor extends Magento_Web_Driver
{
    public const MAGENTO_CLI_COMMAND = 'info:currency:list';
    public const EXCEPTION_CONTEXT_SELENIUM = 'selenium';
    public const EXCEPTION_CONTEXT_ADMIN = 'admin';
    public const EXCEPTION_CONTEXT_STOREFRONT = 'store';
    public const EXCEPTION_CONTEXT_CLI = 'cli';
    /**
     * Remote Web Driver
     *
     * @var RemoteWebDriver
     */
    private $remote_web_driver;
    /**
     * Go through parent initialization routines and in addition diagnose potential environment issues
     *
     * @throws TestFrameworkException
     */
    public function _initialize(): void
    {
        parent::_initialize();
        $context = [];
        try {
            $this->connect_to_selenium_server();
        } catch (Test_Framework_Exception $e) {
            $context[self::EXCEPTION_CONTEXT_SELENIUM] = $e->get_message();
        }
        try {
            $admin_url = rtrim(getenv('MAGENTO_BACKEND_BASE_URL'), '/') ?: rtrim(getenv('MAGENTO_BASE_URL'), '/') . '/' . getenv('MAGENTO_BACKEND_NAME') . '/admin';
            $this->load_page_at_url($admin_url);
        } catch (\Exception $e) {
            $context[self::EXCEPTION_CONTEXT_ADMIN] = $e->get_message();
        }
        try {
            $store_url = getenv('MAGENTO_BASE_URL');
            $this->load_page_at_url($store_url);
        } catch (\Exception $e) {
            $context[self::EXCEPTION_CONTEXT_STOREFRONT] = $e->get_message();
        }
        try {
            $this->run_magento_cli();
        } catch (\Exception $e) {
            $context[self::EXCEPTION_CONTEXT_CLI] = $e->get_message();
        }
        if (null !== $this->remote_web_driver) {
            $this->remote_web_driver->close();
        }
        if (!empty($context)) {
            throw new Test_Framework_Exception('Exception occurred in MagentoWebDriverDoctor', $context);
        }
    }
    /**
     * Check connecting to running selenium server
     *
     * @throws TestFrameworkException
     */
    private function connect_to_selenium_server(): void
    {
        try {
            $this->remote_web_driver = Remote_Web_Driver::create($this->wd_host, $this->capabilities, $this->connection_timeout_in_ms, $this->request_timeout_in_ms, $this->config['http_proxy'], $this->config['http_proxy_port']);
            if (null !== $this->remote_web_driver) {
                return;
            }
        } catch (\Exception) {
        }
        throw new Test_Framework_Exception("Failed to connect Selenium WebDriver at: {$this->wd_host}.\n" . 'Please make sure that Selenium Server is running.');
    }
    /**
     * Validate loading a web page at url in the browser controlled by selenium
     *
     * @param string $url
     * @throws TestFrameworkException
     */
    private function load_page_at_url(string|array|bool $url): void
    {
        try {
            if (null !== $this->remote_web_driver) {
                // Open the web page at url first
                $this->remote_web_driver->get($url);
                // Execute Javascript to retrieve HTTP response code
                $script = '' . 'var xhr = new XMLHttpRequest();' . "xhr.open('GET', '" . $url . "', false);" . 'xhr.send(null); ' . 'return xhr.status';
                $status = $this->remote_web_driver->execute_script($script);
                if ($status === 200) {
                    return;
                }
            }
        } catch (\Exception) {
        }
        throw new Test_Framework_Exception("Failed to load page at url: {$url}\n" . 'Please check Selenium Browser session have access to Magento instance.');
    }
    /**
     * Check running Magento CLI command
     *
     * @throws TestFrameworkException
     */
    private function run_magento_cli(): void
    {
        try {
            $regex = '~^.*[\r\n]+.*(?<name>Currency).*(?<code>Code).*~';
            $output = parent::magento_cli(self::MAGENTO_CLI_COMMAND);
            preg_match($regex, $output, $matches);
            if (isset($matches['name']) && isset($matches['code'])) {
                return;
            }
        } catch (\Exception) {
        }
        throw new Test_Framework_Exception("Failed to run Magento CLI command\n" . 'Please reference Magento DevDoc to setup command.php and .htaccess files.');
    }
}