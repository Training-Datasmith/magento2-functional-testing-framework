<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage;

abstract class Base_Storage
{
    public const ENCRYPTION_ALGO = 'AES-256-CBC';
    /**
     * Initial vector for open_ssl encryption
     *
     * @var string
     */
    protected static $iv;
    /**
     * Key for open_ssl encryption/decryption
     *
     * @var string
     */
    protected static $encoded_key;
    /**
     * Accessed key/value secret data pairs
     *
     * @var array
     */
    protected static $cached_secret_data = [];
    /**
     * BaseStorage constructor
     */
    public function __construct()
    {
        if (null === self::$encoded_key) {
            self::$encoded_key = base64_encode(openssl_random_pseudo_bytes(16));
            self::$iv = substr(hash('sha256', self::$encoded_key), 0, 16);
        }
    }
    /**
     * Returns the encrypted value based on corresponding key
     *
     * @param string $key
     * @return string|null
     */
    public function get_encrypted_value($key)
    {
        if (!array_key_exists($key, self::$cached_secret_data)) {
            return null;
        }
        return self::$cached_secret_data[$key] ?? null;
    }
    /**
     * Takes a value encrypted at runtime and decrypts it using the object's initial vector
     * return the decrypted string on success or false on failure
     *
     * @param string $value
     * @return string|false The decrypted string on success or false on failure
     */
    public static function get_decrypted_value($value)
    {
        return openssl_decrypt($value, self::ENCRYPTION_ALGO, self::$encoded_key, 0, self::$iv);
    }
    /**
     * Takes a string that contains encrypted data at runtime and decrypts each value
     * return false if no decryption happens or a failure occurs
     *
     * @param string $string
     * @return string|false The decrypted string on success or false on failure
     */
    public static function get_all_decrypted_values_in_string($string)
    {
        $decrypted = false;
        foreach (self::$cached_secret_data as $secret_value) {
            if (str_contains($string, (string) $secret_value)) {
                $decrypted_value = self::get_decrypted_value($secret_value);
                if ($decrypted_value === false) {
                    return false;
                }
                if (!$decrypted) {
                    $decrypted = true;
                }
                $string = str_replace($secret_value, $decrypted_value, $string);
            }
        }
        return $decrypted ? $string : false;
    }
}