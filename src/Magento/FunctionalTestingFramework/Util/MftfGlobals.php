<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\Path\UrlFormatter;

/**
 * MFTF Globals
 */
class MftfGlobals
{
    /**
     * Magento Base URL
     *
     * @var string null
     */
    private static ?string $baseUrl = null;

    /**
     * Magento Backend Base URL
     *
     * @var string null
     */
    private static ?string $backendBaseUrl = null;

    /**
     * Magento Web API Base URL
     *
     * @var string null
     */
    private static ?string $webApiBaseUrl = null;

    /**
     * Returns Magento Base URL
     *
     * @throws TestFrameworkException
     */
    public static function getBaseUrl(bool $withTrailingSeparator = true): string
    {
        if (!self::$baseUrl) {
            try {
                $url = getenv('MAGENTO_BASE_URL');
                if ($url) {
                    self::$baseUrl = UrlFormatter::format($url, false);
                }
            } catch (TestFrameworkException) {
            }
        }

        if (self::$baseUrl) {
            return UrlFormatter::format(self::$baseUrl, $withTrailingSeparator);
        }

        throw new TestFrameworkException(
            'Unable to retrieve Magento Base URL. Please check .env and set:'
            . PHP_EOL
            . '"MAGENTO_BASE_URL"'
        );
    }

    /**
     * Return Magento Backend Base URL
     *
     * @throws TestFrameworkException
     */
    public static function getBackendBaseUrl(bool $withTrailingSeparator = true): string
    {
        if (!self::$backendBaseUrl) {
            try {
                $backendName = getenv('MAGENTO_BACKEND_NAME');
                $bUrl = getenv('MAGENTO_BACKEND_BASE_URL');
                if ($bUrl && $backendName) {
                    self::$backendBaseUrl = UrlFormatter::format(
                        UrlFormatter::format($bUrl) . $backendName,
                        false
                    );
                } else {
                    $baseUrl = getenv('MAGENTO_BASE_URL');
                    if ($baseUrl && $backendName) {
                        self::$backendBaseUrl = UrlFormatter::format(
                            UrlFormatter::format($baseUrl) . $backendName,
                            false
                        );
                    }
                }
            } catch (TestFrameworkException) {
            }
        }

        if (self::$backendBaseUrl) {
            return UrlFormatter::format(self::$backendBaseUrl, $withTrailingSeparator);
        }

        throw new TestFrameworkException(
            'Unable to retrieve Magento Backend Base URL. Please check .env and set either:'
            . PHP_EOL
            . '"MAGENTO_BASE_URL" and "MAGENTO_BACKEND_NAME"'
            . PHP_EOL
            . 'or'
            . PHP_EOL
            . '"MAGENTO_BACKEND_BASE_URL"'
        );
    }

    /**
     * Return Web API Base URL
     *
     * @throws TestFrameworkException
     */
    public static function getWebApiBaseUrl(bool $withTrailingSeparator = true): string
    {
        if (!self::$webApiBaseUrl) {
            try {
                $webapiHost = getenv('MAGENTO_RESTAPI_SERVER_HOST');
                $webapiPort = getenv('MAGENTO_RESTAPI_SERVER_PORT');
                $webapiProtocol = getenv('MAGENTO_RESTAPI_SERVER_PROTOCOL');

                if ($webapiHost && $webapiProtocol) {
                    $baseUrl = UrlFormatter::format(
                        sprintf('%s://%s', $webapiProtocol, $webapiHost),
                        false
                    );
                } elseif ($webapiHost) {
                    $baseUrl = UrlFormatter::format($webapiHost, false);
                }

                if (!isset($baseUrl)) {
                    $baseUrl = MftfGlobals::getBaseUrl(false);
                }

                if ($webapiPort) {
                    $baseUrl .= ':' . $webapiPort;
                }

                self::$webApiBaseUrl = $baseUrl . '/rest';
            } catch (TestFrameworkException) {
            }
        }
        if (self::$webApiBaseUrl) {
            return UrlFormatter::format(self::$webApiBaseUrl, $withTrailingSeparator);
        }
        throw new TestFrameworkException(
            'Unable to retrieve Magento Web API Base URL. Please check .env and set either:'
            . PHP_EOL
            . '"MAGENTO_BASE_URL"'
            . PHP_EOL
            . 'or'
            . PHP_EOL
            . '"MAGENTO_RESTAPI_SERVER_HOST"'
        );
    }
}
