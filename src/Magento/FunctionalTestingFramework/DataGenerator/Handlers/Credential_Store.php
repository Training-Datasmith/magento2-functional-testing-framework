<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers;

use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage\Aws_Secrets_Manager_Storage;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage\Base_Storage;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage\File_Storage;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage\Vault_Storage;
use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\Url_Formatter;
class Credential_Store
{
    public const ARRAY_KEY_FOR_VAULT = 'vault';
    public const ARRAY_KEY_FOR_FILE = 'file';
    public const ARRAY_KEY_FOR_AWS_SECRETS_MANAGER = 'aws';
    public const CREDENTIAL_STORAGE_INFO = 'You need to configure at least one of these options: ' . '.credentials file, HashiCorp Vault or AWS Secrets Manager correctly';
    /**
     * Credential storage array
     *
     * @var BaseStorage[]
     */
    private array $cred_storage = [];
    /**
     * Boolean to indicate if credential storage have been initialized
     */
    private bool $initialized;
    /**
     * Singleton instance
     */
    private static ?\Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store $INSTANCE = null;
    /**
     * Exception contexts
     */
    private readonly \Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector $exception_contexts;
    /**
     * Static singleton getter for CredentialStore Instance
     *
     * @return CredentialStore
     */
    public static function get_instance()
    {
        if (self::$INSTANCE === null) {
            self::$INSTANCE = new Credential_Store();
        }
        return self::$INSTANCE;
    }
    /**
     * CredentialStore constructor
     */
    private function __construct()
    {
        $this->initialized = false;
        $this->exception_contexts = new Exception_Collector();
    }
    /**
     * Get encrypted value by key
     *
     * @param string $key
     * @return string|null
     * @throws TestFrameworkException
     */
    public function get_secret($key)
    {
        // Initialize credential storage if it's not been done
        $this->initialize_credential_storage();
        // Get secret data from storage according to the order they are stored which follows this precedence:
        // FileStorage > VaultStorage > AwsSecretsManagerStorage
        foreach ($this->cred_storage as $storage) {
            $value = $storage->get_encrypted_value($key);
            if (null !== $value) {
                return $value;
            }
        }
        $exception_contexts = $this->get_exception_contexts();
        $this->reset_exception_context();
        throw new Test_Framework_Exception("{$key} not found. " . self::CREDENTIAL_STORAGE_INFO . ' and ensure key, value exists to use _CREDS in tests.' . $exception_contexts);
    }
    /**
     * Return decrypted input value
     *
     * @param string $value
     * @return string|false The decrypted string on success or false on failure
     * @throws TestFrameworkException
     */
    public function decrypt_secret_value($value)
    {
        // Initialize credential storage if it's not been done
        $this->initialize_credential_storage();
        // Decrypt secret value
        return Base_Storage::get_decrypted_value($value);
    }
    /**
     * Return decrypted values for all occurrences from input string
     *
     * @param string $string
     * @return string|false The decrypted string on success or false on failure
     * @throws TestFrameworkException
     */
    public function decrypt_all_secrets_in_string($string)
    {
        // Initialize credential storage if it's not been done
        $this->initialize_credential_storage();
        // Decrypt all secret values in string
        return Base_Storage::get_all_decrypted_values_in_string($string);
    }
    /**
     * Setter for exception contexts
     *
     * @param string $type
     * @param string $context
     */
    public function set_exception_contexts($type, $context): void
    {
        $type_array = [self::ARRAY_KEY_FOR_FILE, self::ARRAY_KEY_FOR_VAULT, self::ARRAY_KEY_FOR_AWS_SECRETS_MANAGER];
        if (in_array($type, $type_array) && !empty($context)) {
            $this->exception_contexts->add_error($type, $context);
        }
    }
    /**
     * Return collected exception contexts
     */
    private function get_exception_contexts(): string
    {
        // Gather all exceptions collected
        $exception_message = "\n";
        foreach ($this->exception_contexts->get_errors() as $type => $exceptions) {
            $exception_message .= "\nException from ";
            if ($type === self::ARRAY_KEY_FOR_FILE) {
                $exception_message .= "File Storage: \n";
            }
            if ($type === self::ARRAY_KEY_FOR_VAULT) {
                $exception_message .= "Vault Storage: \n";
            }
            if ($type === self::ARRAY_KEY_FOR_AWS_SECRETS_MANAGER) {
                $exception_message .= "AWS Secrets Manager Storage: \n";
            }
            if (is_array($exceptions)) {
                $exception_message .= implode("\n", $exceptions) . "\n";
            } else {
                $exception_message .= $exceptions . "\n";
            }
        }
        return $exception_message;
    }
    /**
     * Reset exception contexts to empty array
     */
    private function reset_exception_context(): void
    {
        $this->exception_contexts->reset();
    }
    /**
     * Initialize all available credential storage
     *
     * @throws TestFrameworkException
     */
    private function initialize_credential_storage(): void
    {
        if (!$this->initialized) {
            // Initialize credential storage by defined order of precedence as the following
            $this->initialize_file_storage();
            $this->initialize_vault_storage();
            $this->initialize_aws_secrets_manager_storage();
            $this->initialized = true;
        }
        if (empty($this->cred_storage)) {
            throw new Test_Framework_Exception('Invalid Credential Storage. ' . self::CREDENTIAL_STORAGE_INFO . '.' . $this->get_exception_contexts());
        }
        $this->reset_exception_context();
    }
    /**
     * Initialize file storage
     */
    private function initialize_file_storage(): void
    {
        // Initialize file storage
        try {
            $file_storage = new File_Storage();
            $file_storage->initialize();
            $this->cred_storage[self::ARRAY_KEY_FOR_FILE] = $file_storage;
        } catch (Test_Framework_Exception $e) {
            // Print error message in console
            print_r($e->get_message());
            // Save to exception context for Allure report
            $this->set_exception_contexts(self::ARRAY_KEY_FOR_FILE, $e->get_message());
        }
    }
    /**
     * Initialize Vault storage
     */
    private function initialize_vault_storage(): void
    {
        // Initialize vault storage
        $cv_address = getenv('CREDENTIAL_VAULT_ADDRESS');
        $cv_secret_path = getenv('CREDENTIAL_VAULT_SECRET_BASE_PATH');
        if ($cv_address !== false && $cv_secret_path !== false) {
            try {
                $this->cred_storage[self::ARRAY_KEY_FOR_VAULT] = new Vault_Storage(Url_Formatter::format($cv_address, false), '/' . trim($cv_secret_path, '/'));
            } catch (Test_Framework_Exception $e) {
                // Print error message in console
                print_r($e->get_message());
                // Save to exception context for Allure report
                $this->set_exception_contexts(self::ARRAY_KEY_FOR_VAULT, $e->get_message());
            }
        }
    }
    /**
     * Initialize AWS Secrets Manager storage
     */
    private function initialize_aws_secrets_manager_storage(): void
    {
        // Initialize AWS Secrets Manager storage
        $aws_region = getenv('CREDENTIAL_AWS_SECRETS_MANAGER_REGION');
        $aws_profile = getenv('CREDENTIAL_AWS_SECRETS_MANAGER_PROFILE');
        $aws_id = getenv('CREDENTIAL_AWS_ACCOUNT_ID');
        if (!empty($aws_region)) {
            if (empty($aws_profile)) {
                $aws_profile = null;
            }
            if (empty($aws_id)) {
                $aws_id = null;
            }
            try {
                $this->cred_storage[self::ARRAY_KEY_FOR_AWS_SECRETS_MANAGER] = new Aws_Secrets_Manager_Storage($aws_region, $aws_profile, $aws_id);
            } catch (Test_Framework_Exception $e) {
                // Print error message in console
                print_r($e->get_message());
                // Save to exception context for Allure report
                $this->set_exception_contexts(self::ARRAY_KEY_FOR_AWS_SECRETS_MANAGER, $e->get_message());
            }
        }
    }
}