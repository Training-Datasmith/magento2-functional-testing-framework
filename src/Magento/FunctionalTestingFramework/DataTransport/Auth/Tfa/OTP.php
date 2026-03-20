<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport\Auth\Tfa;

use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use OTPHP\TOTP;
/**
 * Class OTP
 */
class OTP
{
    public const OTP_SHARED_SECRET_PATH = 'magento/tfa/OTP_SHARED_SECRET';
    /**
     * TOTP object
     *
     * @var TOTP[]
     */
    private static array $totps = [];
    /**
     * Return OTP for custom secret stored in `magento/tfa/OTP_SHARED_SECRET`
     *
     * @return string
     * @throws TestFrameworkException
     */
    public static function get_otp(?string $path = null)
    {
        if ($path === null) {
            $path = self::OTP_SHARED_SECRET_PATH;
        }
        return self::create($path)->now();
    }
    /**
     * Create TOTP object
     *
     * @return TOTP
     * @throws TestFrameworkException
     */
    private static function create(string $path)
    {
        if (!isset(self::$totps[$path])) {
            try {
                // Get shared secret from Credential storage
                $encrypted_secret = Credential_Store::get_instance()->get_secret($path);
                $secret = Credential_Store::get_instance()->decrypt_secret_value($encrypted_secret);
            } catch (Test_Framework_Exception $e) {
                throw new Test_Framework_Exception('Unable to get OTP' . PHP_EOL . $e->get_message());
            }
            self::$totps[$path] = TOTP::create($secret, TOTP::DEFAULT_PERIOD, TOTP::DEFAULT_DIGEST, TOTP::DEFAULT_DIGITS, TOTP::DEFAULT_EPOCH, new Clock());
            self::$totps[$path]->set_issuer('MFTF');
            self::$totps[$path]->set_label('MFTF Testing');
        }
        return self::$totps[$path];
    }
}