<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util;

use Magento\Functional_Testing_Framework\Util\Path\Url_Formatter;
/**
 * Class ConfigSanitizerUtil
 */
class Config_Sanitizer_Util
{
    /**
     * Sanitizes the given Webdriver Config's url and selenium env params, can be selective based on second argument.
     * @param String[] $params
     * @return array
     */
    public static function sanitize_web_driver_config(array $config, $params = ['url', 'selenium'])
    {
        self::validate_config_based_vars($config);
        if (in_array('url', $params)) {
            $config['url'] = Url_Formatter::format($config['url']);
        }
        if (in_array('selenium', $params)) {
            return self::sanitize_selenium_envs($config);
        }
        return $config;
    }
    /**
     * Sets Selenium params if they are left as defaults.
     */
    private static function sanitize_selenium_envs(array $config): array
    {
        if ($config['protocol'] === '%SELENIUM_PROTOCOL%') {
            $config['protocol'] = 'http';
        }
        if ($config['host'] === '%SELENIUM_HOST%') {
            $config['host'] = '127.0.0.1';
        }
        if ($config['port'] === '%SELENIUM_PORT%') {
            $config['port'] = '4444';
        }
        if ($config['path'] === '%SELENIUM_PATH%') {
            $config['path'] = '/wd/hub';
        }
        return $config;
    }
    /**
     * Method which validates env vars have been properly read into the config. Method implemented as part of
     * bug MQE-567
     */
    private static function validate_config_based_vars(array $config): void
    {
        $config_strings = array_filter($config, fn($value) => is_string($value));
        foreach ($config_strings as $config_value) {
            $var = trim((string) $config_value, '%');
            if (array_key_exists($var, $_ENV)) {
                trigger_error("Issue with setting configuration for test runs. Please make sure '{$var}' is " . 'not duplicated as a system level variable', E_USER_ERROR);
            }
        }
    }
}