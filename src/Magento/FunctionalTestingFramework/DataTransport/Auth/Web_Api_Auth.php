<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport\Auth;

use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store;
use Magento\Functional_Testing_Framework\Data_Transport\Auth\Tfa\OTP;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Interface;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Transport;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Mftf_Globals;
/**
 * Class WebApiAuth
 */
class Web_Api_Auth
{
    public const PATH_ADMIN_AUTH = 'V1/integration/admin/token';
    /** Rest request headers
     *
     * @var string[]
     */
    private static array $headers = ['Accept: application/json', 'Content-Type: application/json'];
    /**
     * Tokens for admin users
     *
     * @var string[]
     */
    private static array $admin_auth_tokens = [];
    /**
     * Timestamps of when admin user tokens were created.  They need to be refreshed every ~4 hours
     *
     * @var int[]
     */
    private static array $admin_auth_token_timestamps = [];
    /**
     * Return the API token for an admin user
     * Use MAGENTO_ADMIN_USERNAME and MAGENTO_ADMIN_PASSWORD when $username and/or $password is/are omitted
     *
     * @param string $username
     * @param string $password
     * @return string
     * @throws FastFailException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public static function get_admin_token(?string $username = null, ?string $password = null)
    {
        $login = $username ?? getenv('MAGENTO_ADMIN_USERNAME');
        try {
            $encrypted_secret = Credential_Store::get_instance()->get_secret('magento/MAGENTO_ADMIN_PASSWORD');
            $secret = Credential_Store::get_instance()->decrypt_secret_value($encrypted_secret);
            $password ??= $secret;
        } catch (Test_Framework_Exception $e) {
            $message = 'Password not found in credentials file';
            throw new Fast_Fail_Exception($message . $e->get_message(), $e->get_context());
        }
        if (!$login || !$password) {
            $message = 'Cannot retrieve API token without credentials. Please fill out .env.';
            $context = ['MAGENTO_BASE_URL' => getenv('MAGENTO_BASE_URL'), 'MAGENTO_BACKEND_BASE_URL' => getenv('MAGENTO_BACKEND_BASE_URL'), 'MAGENTO_ADMIN_USERNAME' => getenv('MAGENTO_ADMIN_USERNAME'), 'MAGENTO_ADMIN_PASSWORD' => $secret];
            throw new Fast_Fail_Exception($message, $context);
        }
        if (self::has_existing_token($login)) {
            return self::$admin_auth_tokens[$login];
        }
        try {
            $auth_url = Mftf_Globals::get_web_api_base_url() . self::PATH_ADMIN_AUTH;
            $data = ['username' => $login, 'password' => $password];
            if (Tfa::is_enabled()) {
                $auth_url = Mftf_Globals::get_web_api_base_url() . Tfa::get_provider_web_api_auth_endpoint('google');
                $data['otp'] = OTP::get_otp();
            }
            $transport = new Curl_Transport();
            $transport->write($auth_url, json_encode($data, JSON_PRETTY_PRINT), Curl_Interface::POST, self::$headers);
        } catch (Test_Framework_Exception $e) {
            $message = "Cannot retrieve API token with credentials. Please check configurations in .env.\n";
            throw new Fast_Fail_Exception($message . $e->get_message(), $e->get_context());
        }
        try {
            $response = $transport->read();
            $transport->close();
            $token = json_decode($response);
            if ($token !== null) {
                self::$admin_auth_tokens[$login] = $token;
                self::$admin_auth_token_timestamps[$login] = time();
                return $token;
            }
            $err_message = "Invalid response: {$response}";
        } catch (Test_Framework_Exception $e) {
            $transport->close();
            $err_message = $e->get_message();
        }
        $message = 'Cannot retrieve API token with credentials.';
        try {
            // No exception will ever throw from here
            $message .= Tfa::is_enabled() ? ' and 2FA settings:' : ':' . PHP_EOL;
        } catch (Test_Framework_Exception) {
        }
        $message .= $err_message;
        $context = ['url' => $auth_url];
        throw new Fast_Fail_Exception($message, $context);
    }
    /**
     * Is there an existing WebAPI admin token for this login?
     *
     * @return boolean
     */
    private static function has_existing_token(string $login)
    {
        if (!isset(self::$admin_auth_tokens[$login])) {
            return false;
        }
        $token_lifetime = getenv('MAGENTO_ADMIN_WEBAPI_TOKEN_LIFETIME');
        $is_token_expired = $token_lifetime && time() - self::$admin_auth_token_timestamps[$login] > $token_lifetime;
        return !$is_token_expired;
    }
}