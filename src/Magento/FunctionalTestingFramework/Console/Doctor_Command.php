<?php

/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Codeception\Configuration;
use Codeception\Suite_Manager;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Transport\Auth\Web_Api_Auth;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Module\Magento_Web_Driver;
use Magento\Functional_Testing_Framework\Module\Magento_Web_Driver_Doctor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
class Doctor_Command extends Command
{
    public const CODECEPTION_AUTOLOAD_FILE = PROJECT_ROOT . '/vendor/codeception/codeception/autoload.php';
    public const MFTF_CODECEPTION_CONFIG_FILE = ENV_FILE_PATH . 'codeception.yml';
    public const SUITE = 'functional';
    /**
     * Console output style
     */
    private ?\Symfony\Component\Console\Style\Symfony_Style $io_style = null;
    /**
     * Exception Context
     *
     * @var array
     */
    private $context = [];
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('doctor')->set_description('This command checks environment readiness for generating and running MFTF tests.');
    }
    /**
     * Executes the current command.
     *
     * @throws TestFrameworkException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        // For output style
        $this->io_style = new Symfony_Style($input, $output);
        $cmd_status = true;
        // Config application
        $verbose = $output->is_verbose();
        Mftf_Application_Config::create(false, Mftf_Application_Config::GENERATION_PHASE, $verbose, Mftf_Application_Config::LEVEL_DEVELOPER, false);
        // Check authentication to Magento Admin
        $status = $this->check_authentication_to_magento_admin();
        $cmd_status = $cmd_status && !$status ? false : $cmd_status;
        // Check connection to Selenium
        $status = $this->check_context_on_step(Magento_Web_Driver_Doctor::EXCEPTION_CONTEXT_SELENIUM, 'Connecting to Selenium Server');
        $cmd_status = $cmd_status && !$status ? false : $cmd_status;
        // Check opening Magento Admin in web browser
        $status = $this->check_context_on_step(Magento_Web_Driver_Doctor::EXCEPTION_CONTEXT_ADMIN, 'Loading Admin page');
        $cmd_status = $cmd_status && !$status ? false : $cmd_status;
        // Check opening Magento Storefront in web browser
        $status = $this->check_context_on_step(Magento_Web_Driver_Doctor::EXCEPTION_CONTEXT_STOREFRONT, 'Loading Storefront page');
        $cmd_status = $cmd_status && !$status ? false : $cmd_status;
        // Check access to Magento CLI
        $status = $this->check_context_on_step(Magento_Web_Driver_Doctor::EXCEPTION_CONTEXT_CLI, 'Running Magento CLI');
        $cmd_status = $cmd_status && !$status ? false : $cmd_status;
        return $cmd_status ? 0 : 1;
    }
    /**
     * Check admin account authentication
     *
     * @return boolean
     */
    private function check_authentication_to_magento_admin()
    {
        $result = false;
        try {
            $this->io_style->text('Requesting API token for admin user through cURL ...');
            Web_Api_Auth::get_admin_token();
            $this->io_style->success('Successful');
            $result = true;
        } catch (Test_Framework_Exception $e) {
            if (getenv('MAGENTO_BACKEND_BASE_URL')) {
                $url_var = 'MAGENTO_BACKEND_BASE_URL';
            } else {
                $url_var = 'MAGENTO_BASE_URL';
            }
            $this->io_style->error($e->get_message() . "\nPlease verify if " . $url_var . ', ' . 'MAGENTO_ADMIN_USERNAME and MAGENTO_ADMIN_PASSWORD in .env are valid.');
        }
        return $result;
    }
    /**
     * Check exception context after runMagentoWebDriverDoctor
     *
     * @throws TestFrameworkException
     */
    private function check_context_on_step(string $exception_type, string $message): bool
    {
        $this->io_style->text($message . ' ...');
        $this->run_magento_web_driver_doctor();
        if (isset($this->context[$exception_type])) {
            $this->io_style->error($this->context[$exception_type]);
            return false;
        }
        $this->io_style->success('Successful');
        return true;
    }
    /**
     * Run diagnose through MagentoWebDriverDoctor
     *
     * @throws TestFrameworkException
     */
    private function run_magento_web_driver_doctor(): void
    {
        if (!empty($this->context)) {
            return;
        }
        $magento_web_driver = '\\' . Magento_Web_Driver::class;
        $magento_web_driver_doctor = '\\' . Magento_Web_Driver_Doctor::class;
        require_once realpath(self::CODECEPTION_AUTOLOAD_FILE);
        $config = Configuration::config(realpath(self::MFTF_CODECEPTION_CONFIG_FILE));
        $settings = Configuration::suite_settings(self::SUITE, $config);
        // Enable MagentoWebDriverDoctor
        $settings['modules']['enabled'][] = $magento_web_driver_doctor;
        $settings['modules']['config'][$magento_web_driver_doctor] = $settings['modules']['config'][$magento_web_driver];
        // Disable MagentoWebDriver to avoid conflicts
        foreach ($settings['modules']['enabled'] as $index => $module) {
            if ($module === $magento_web_driver) {
                unset($settings['modules']['enabled'][$index]);
                break;
            }
        }
        unset($settings['modules']['config'][$magento_web_driver]);
        $dispatcher = new Event_Dispatcher();
        $suite_manager = new Suite_Manager($dispatcher, self::SUITE, $settings, []);
        try {
            $suite_manager->initialize();
            $this->context = ['Successful'];
        } catch (Test_Framework_Exception $e) {
            $this->context = $e->get_context();
        }
    }
}