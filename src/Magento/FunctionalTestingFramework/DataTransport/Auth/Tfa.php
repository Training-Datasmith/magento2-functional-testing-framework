<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport\Auth;

use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Interface;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Transport;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Mftf_Globals;
/**
 * Class Tfa (i.e. 2FA)
 */
class Tfa
{
    public const WEB_API_AUTH_GOOGLE = 'V1/tfa/provider/google/authenticate';
    public const ADMIN_FORM_AUTH_GOOGLE = 'tfa/google/authpost/?isAjax=true';
    public const TFA_SCHEMA = 'schema?services=twoFactorAuthAdminTokenServiceV1';
    /**
     * If 2FA is enabled
     *
     * @var boolean|null
     */
    private static $tfa_enabled;
    /** Rest request headers
     *
     * @var string[]
     */
    private static array $headers = ['Accept: application/json', 'Content-Type: application/json'];
    /**
     * 2FA provider web API authentication endpoints
     *
     * @var string[]
     */
    private static array $provider_web_api_auth_endpoints = ['google' => self::WEB_API_AUTH_GOOGLE];
    /**
     * 2FA provider admin form authentication endpoints
     *
     * @var string[]
     */
    private static array $provider_admin_form_auth_endpoints = ['google' => self::ADMIN_FORM_AUTH_GOOGLE];
    /**
     * Check if 2FA is enabled for Magento instance under test
     *
     * @return boolean
     * @throws TestFrameworkException
     */
    public static function is_enabled()
    {
        if (self::$tfa_enabled !== null) {
            return self::$tfa_enabled;
        }
        $schema_url = Mftf_Globals::get_web_api_base_url() . self::TFA_SCHEMA;
        $transport = new Curl_Transport();
        try {
            $transport->write($schema_url, [], Curl_Interface::GET, self::$headers);
            $response = $transport->read();
            $transport->close();
            $schema = json_decode($response, true);
            if (isset($schema['definitions'], $schema['paths'])) {
                return true;
            }
        } catch (Test_Framework_Exception) {
            $transport->close();
        }
        return false;
    }
    /**
     * Return provider's 2FA web API authentication endpoint
     *
     * @param string $name
     * @return string|null
     */
    public static function get_provider_web_api_auth_endpoint($name)
    {
        // Currently only support Google Authenticator
        return self::$provider_web_api_auth_endpoints[$name] ?? null;
    }
    /**
     * Return 2FA provider's admin form authentication endpoint
     *
     * @param string $name
     * @return string|null
     */
    public static function get_provider_admin_form_endpoint($name)
    {
        // Currently only support Google Authenticator
        return self::$provider_admin_form_auth_endpoints[$name] ?? null;
    }
}