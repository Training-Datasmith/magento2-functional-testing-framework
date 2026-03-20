<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
class File_Storage extends Base_Storage
{
    /**
     * Key/value secret data pairs parsed from file
     *
     * @var array
     */
    private $secret_data = [];
    /**
     * Initialize secret data value which represents encrypted credentials
     *
     * @throws TestFrameworkException
     */
    public function initialize(): void
    {
        if (!$this->secret_data) {
            $creds = $this->read_in_credentials_file();
            $this->secret_data = $this->encrypt_cred_file_contents($creds);
        }
    }
    /**
     * Returns the value of a secret based on corresponding key
     *
     * @param string $key
     * @throws TestFrameworkException
     */
    public function get_encrypted_value($key): ?string
    {
        $this->initialize();
        // Check if secret is in cached array
        if (null !== $value = parent::get_encrypted_value($key)) {
            return $value;
        }
        // log here for verbose config
        if (Mftf_Application_Config::get_config()->verbose_enabled()) {
            Logging_Util::get_instance()->get_logger(File_Storage::class)->debug("retrieving secret for key name {$key} from file");
        }
        // Retrieve from file storage
        if (array_key_exists($key, $this->secret_data) && null !== $value = $this->secret_data[$key]) {
            parent::$cached_secret_data[$key] = $value;
        }
        return $value;
    }
    /**
     * Private function which reads in secret key/values from .credentials file and stores in memory as key/value pair
     *
     * @return array
     * @throws TestFrameworkException
     */
    private function read_in_credentials_file()
    {
        $creds_file_path = str_replace('.credentials.example', '.credentials', File_Path_Formatter::format(TESTS_BP) . '.credentials.example');
        if (!file_exists($creds_file_path)) {
            throw new Test_Framework_Exception('Credential file is not used: .credentials file not found in ' . TESTS_BP);
        }
        return file($creds_file_path, FILE_IGNORE_NEW_LINES);
    }
    /**
     * Function which takes the contents of the credentials file and encrypts the entries
     *
     * @param array $credContents
     * @throws TestFrameworkException
     */
    private function encrypt_cred_file_contents($cred_contents): array
    {
        $encrypted_creds = [];
        foreach ($cred_contents as $cred_value) {
            if (str_starts_with((string) $cred_value, '#')) {
                continue;
            }
            if (empty($cred_value)) {
                continue;
            }
            if (!str_contains((string) $cred_value, '=')) {
                throw new Test_Framework_Exception($cred_value . ' not configured correctly in .credentials file');
            }
            [$key, $value] = explode('=', (string) $cred_value, 2);
            if (!empty($value)) {
                $encrypted_creds[$key] = openssl_encrypt($value, parent::ENCRYPTION_ALGO, parent::$encoded_key, 0, parent::$iv);
            }
        }
        return $encrypted_creds;
    }
}