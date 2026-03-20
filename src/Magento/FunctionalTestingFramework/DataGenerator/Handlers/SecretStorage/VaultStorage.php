<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage;

use Guzzle_Http\Client as GuzzleClient;
use Laminas\Diactoros\Request_Factory;
use Laminas\Diactoros\Stream_Factory;
use Laminas\Diactoros\Uri;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Vault\Client;
class Vault_Storage extends Base_Storage
{
    /**
     * Mftf project path
     */
    public const MFTF_PATH = '/mftf';
    /**
     * Vault kv version 2 data
     */
    public const KV2_DATA = 'data';
    /**
     * Default vault token file
     */
    public const TOKEN_FILE = '.vault-token';
    /**
     * Default vault config file
     */
    public const CONFIG_FILE = '.vault';
    /**
     * Environment variable name for vault config path
     */
    public const CONFIG_PATH_ENV_VAR = 'VAULT_CONFIG_PATH';
    /**
     * Regex to grab token helper script
     */
    public const TOKEN_HELPER_REGEX_GROUP_NAME = 'GROUP_NAME';
    public const TOKEN_HELPER_REGEX = "~\\s*token_helper\\s*=(?<" . self::TOKEN_HELPER_REGEX_GROUP_NAME . '>.+)$~';
    /**
     * Vault client
     *
     * @var Client
     */
    private $client;
    /**
     * Vault token
     *
     * @var string
     */
    private $token;
    /**
     * Vault secret base path
     *
     * @var string
     */
    private $secret_base_path;
    /**
     * VaultStorage constructor
     *
     * @param string $baseUrl
     * @param string $secretBasePath
     * @throws TestFrameworkException
     */
    public function __construct($base_url, $secret_base_path)
    {
        parent::__construct();
        if (null === $this->client) {
            // client configuration and override http errors settings
            $this->client = new Client(new Uri($base_url), new Guzzle_Client(['timeout' => 15, 'base_uri' => $base_url, 'http_errors' => false]), new Request_Factory(), new Stream_Factory());
            $this->secret_base_path = $secret_base_path;
        }
        $this->read_vault_token_from_file_system();
        if (!$this->authenticated()) {
            throw new Test_Framework_Exception('Credential vault is not used: cannot authenticate');
        }
    }
    /**
     * Returns the value of a secret based on corresponding key
     *
     * @param string $key
     * @return string|null
     */
    public function get_encrypted_value($key)
    {
        // Check if secret is in cached array
        if (null !== $value = parent::get_encrypted_value($key)) {
            return $value;
        }
        if (Mftf_Application_Config::get_config()->verbose_enabled()) {
            Logging_Util::get_instance()->get_logger(Vault_Storage::class)->debug("Retrieving secret for key name {$key} from vault");
        }
        $re_value = null;
        try {
            // Split vendor/key to construct secret path
            [$vendor, $key] = explode('/', trim($key, '/'), 2);
            $url = $this->secret_base_path . (empty(self::KV2_DATA) ? '' : '/' . self::KV2_DATA) . self::MFTF_PATH . '/' . $vendor . '/' . $key;
            // Read value by key from vault
            $value = $this->client->read($url)->get_data()[self::KV2_DATA][$key];
            // Encrypt value for return
            $re_value = openssl_encrypt($value, parent::ENCRYPTION_ALGO, parent::$encoded_key, 0, parent::$iv);
            parent::$cached_secret_data[$key] = $re_value;
        } catch (\Exception $e) {
            $err_message = "\nUnable to read secret for key name {$key} from vault." . $e->get_message();
            // Print error message in console
            print_r($err_message);
            // Save to exception context for Allure report
            Credential_Store::get_instance()->set_exception_contexts('vault', $err_message);
            // Add error message in mftf log if verbose is enable
            if (Mftf_Application_Config::get_config()->verbose_enabled()) {
                Logging_Util::get_instance()->get_logger(Vault_Storage::class)->debug($err_message);
            }
        }
        return $re_value;
    }
    /**
     * Check if vault token is valid
     */
    private function authenticated(): bool
    {
        try {
            // Authenticating using token auth backend
            $authenticated = $this->client->set_authentication_strategy(new Vault_Token_Auth_Strategy($this->token))->authenticate();
            if ($authenticated) {
                return true;
            }
        } catch (\Exception $e) {
            // Print error message in console
            print_r($e->get_message());
            // Save to exception context for Allure report
            Credential_Store::get_instance()->set_exception_contexts(Credential_Store::ARRAY_KEY_FOR_VAULT, $e->get_message());
        }
        return false;
    }
    /**
     * Read vault token from file system
     *
     * @throws TestFrameworkException
     */
    private function read_vault_token_from_file_system(): void
    {
        // Find user home directory
        $home_dir = getenv('HOME');
        if ($home_dir === false) {
            throw new Test_Framework_Exception("HOME environment variable is not set. It's required when using vault.");
        }
        $home_dir = realpath($home_dir) . DIRECTORY_SEPARATOR;
        // Read .vault-token file if it is found in default location
        $vault_token_file = $home_dir . self::TOKEN_FILE;
        if (file_exists($vault_token_file)) {
            $token = file_get_contents($vault_token_file);
            if ($token !== false) {
                $this->token = $token;
                return;
            }
        }
        // Otherwise search vault config file for custom token helper script
        $vault_config_path = getenv(self::CONFIG_PATH_ENV_VAR);
        if ($vault_config_path === false) {
            $vault_config_file = $home_dir . self::CONFIG_FILE;
        } else {
            $vault_config_file = realpath($vault_config_path) . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
        }
        // Get custom token helper script file from .vault config file
        if (file_exists($vault_config_file)) {
            $cmd = $this->get_token_helper_script(file($vault_config_file, FILE_IGNORE_NEW_LINES));
            if (!empty($cmd)) {
                $this->token = $this->exec_vault_token_helper($cmd . ' get');
                return;
            }
        }
        throw new Test_Framework_Exception('Unable to read .vault-token file. Please authenticate to vault through vault CLI first.');
    }
    /**
     * Get vault token helper script by parsing lines in vault config file
     *
     * @param array $lines
     */
    private function get_token_helper_script($lines): string
    {
        $token_helper = '';
        foreach ($lines as $line) {
            preg_match(self::TOKEN_HELPER_REGEX, (string) $line, $matches);
            if (isset($matches[self::TOKEN_HELPER_REGEX_GROUP_NAME])) {
                $token_helper = trim(trim(trim($matches[self::TOKEN_HELPER_REGEX_GROUP_NAME]), '"'));
            }
        }
        return $token_helper;
    }
    /**
     * Execute vault token helper script and return the token it contains
     *
     * @throws TestFrameworkException
     */
    private function exec_vault_token_helper(string $cmd): string
    {
        exec($cmd, $out, $status);
        if ($status === 0 && isset($out[0]) && !empty($out[0])) {
            return $out[0];
        }
        throw new Test_Framework_Exception('Error running custom vault token helper script. Please make sure vault CLI works in your environment.');
    }
}