<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\Url_Formatter;
/**
 * MFTF Globals
 */
class Mftf_Globals
{
    /**
     * Magento Base URL
     *
     * @var string null
     */
    private static ?string $base_url = null;
    /**
     * Magento Backend Base URL
     *
     * @var string null
     */
    private static ?string $backend_base_url = null;
    /**
     * Magento Web API Base URL
     *
     * @var string null
     */
    private static ?string $web_api_base_url = null;
    /**
     * Returns Magento Base URL
     *
     * @throws TestFrameworkException
     */
    public static function get_base_url(bool $with_trailing_separator = true): string
    {
        if (!self::$base_url) {
            try {
                $url = getenv('MAGENTO_BASE_URL');
                if ($url) {
                    self::$base_url = Url_Formatter::format($url, false);
                }
            } catch (Test_Framework_Exception) {
            }
        }
        if (self::$base_url) {
            return Url_Formatter::format(self::$base_url, $with_trailing_separator);
        }
        throw new Test_Framework_Exception('Unable to retrieve Magento Base URL. Please check .env and set:' . PHP_EOL . '"MAGENTO_BASE_URL"');
    }
    /**
     * Return Magento Backend Base URL
     *
     * @throws TestFrameworkException
     */
    public static function get_backend_base_url(bool $with_trailing_separator = true): string
    {
        if (!self::$backend_base_url) {
            try {
                $backend_name = getenv('MAGENTO_BACKEND_NAME');
                $b_url = getenv('MAGENTO_BACKEND_BASE_URL');
                if ($b_url && $backend_name) {
                    self::$backend_base_url = Url_Formatter::format(Url_Formatter::format($b_url) . $backend_name, false);
                } else {
                    $base_url = getenv('MAGENTO_BASE_URL');
                    if ($base_url && $backend_name) {
                        self::$backend_base_url = Url_Formatter::format(Url_Formatter::format($base_url) . $backend_name, false);
                    }
                }
            } catch (Test_Framework_Exception) {
            }
        }
        if (self::$backend_base_url) {
            return Url_Formatter::format(self::$backend_base_url, $with_trailing_separator);
        }
        throw new Test_Framework_Exception('Unable to retrieve Magento Backend Base URL. Please check .env and set either:' . PHP_EOL . '"MAGENTO_BASE_URL" and "MAGENTO_BACKEND_NAME"' . PHP_EOL . 'or' . PHP_EOL . '"MAGENTO_BACKEND_BASE_URL"');
    }
    /**
     * Return Web API Base URL
     *
     * @throws TestFrameworkException
     */
    public static function get_web_api_base_url(bool $with_trailing_separator = true): string
    {
        if (!self::$web_api_base_url) {
            try {
                $webapi_host = getenv('MAGENTO_RESTAPI_SERVER_HOST');
                $webapi_port = getenv('MAGENTO_RESTAPI_SERVER_PORT');
                $webapi_protocol = getenv('MAGENTO_RESTAPI_SERVER_PROTOCOL');
                if ($webapi_host && $webapi_protocol) {
                    $base_url = Url_Formatter::format(sprintf('%s://%s', $webapi_protocol, $webapi_host), false);
                } elseif ($webapi_host) {
                    $base_url = Url_Formatter::format($webapi_host, false);
                }
                if (!isset($base_url)) {
                    $base_url = Mftf_Globals::get_base_url(false);
                }
                if ($webapi_port) {
                    $base_url .= ':' . $webapi_port;
                }
                self::$web_api_base_url = $base_url . '/rest';
            } catch (Test_Framework_Exception) {
            }
        }
        if (self::$web_api_base_url) {
            return Url_Formatter::format(self::$web_api_base_url, $with_trailing_separator);
        }
        throw new Test_Framework_Exception('Unable to retrieve Magento Web API Base URL. Please check .env and set either:' . PHP_EOL . '"MAGENTO_BASE_URL"' . PHP_EOL . 'or' . PHP_EOL . '"MAGENTO_RESTAPI_SERVER_HOST"');
    }
}