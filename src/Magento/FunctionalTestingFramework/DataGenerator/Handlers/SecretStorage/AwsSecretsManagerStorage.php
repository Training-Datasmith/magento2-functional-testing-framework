<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers\Secret_Storage;

use Aws\Exception\Aws_Exception;
use Aws\Result;
use Aws\Secrets_Manager\Secrets_Manager_Client;
use Exception;
use InvalidArgumentException;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
class Aws_Secrets_Manager_Storage extends Base_Storage
{
    /**
     * Mftf project path
     */
    public const MFTF_PATH = 'mftf';
    /**
     * AWS Secrets Manager partial ARN
     */
    public const AWS_SM_PARTIAL_ARN = 'arn:aws:secretsmanager:';
    /**
     * AWS Secrets Manager version
     *
     * Last tested version '2017-10-17'
     */
    public const LATEST_VERSION = 'latest';
    /**
     * SecretsManagerClient client
     *
     * @var SecretsManagerClient
     */
    private $client;
    /**
     * AWS account region
     *
     * @var string
     */
    private $region;
    /**
     * AwsSecretsManagerStorage constructor
     *
     * @param string $region
     * @param string $profile
     * @param string $awsAccountId
     * @throws TestFrameworkException
     * @throws InvalidArgumentException
     */
    public function __construct(
        $region,
        $profile = null,
        /**
         * AWS account id
         */
        private $aws_account_id = null
    )
    {
        parent::__construct();
        $this->create_aws_secrets_manager_client($region, $profile);
        $this->region = $region;
    }
    /**
     * Returns the value of a secret based on corresponding key
     *
     * @param string $key
     * @return string|null
     * @throws Exception
     */
    public function get_encrypted_value($key)
    {
        // Check if secret is in cached array
        if (null !== $value = parent::get_encrypted_value($key)) {
            return $value;
        }
        if (Mftf_Application_Config::get_config()->verbose_enabled()) {
            Logging_Util::get_instance()->get_logger(Aws_Secrets_Manager_Storage::class)->debug("Retrieving value for key name {$key} from AWS Secrets Manager");
        }
        $re_value = null;
        try {
            // Split vendor/key to construct secret id
            [$vendor, $key] = explode('/', trim($key, '/'), 2);
            // If AWS account id is specified, create and use full ARN, otherwise use partial ARN as secret id
            $secret_id = '';
            if (!empty($this->aws_account_id)) {
                $secret_id = self::AWS_SM_PARTIAL_ARN . $this->region . ':' . $this->aws_account_id . ':secret:';
            }
            $secret_id .= self::MFTF_PATH . '/' . $vendor . '/' . $key;
            // Read value by id from AWS Secrets Manager, and parse the result
            $value = $this->parse_aws_secret_result($this->client->get_secret_value(['SecretId' => $secret_id]), $key);
            // Encrypt value for return
            $re_value = openssl_encrypt($value, parent::ENCRYPTION_ALGO, parent::$encoded_key, 0, parent::$iv);
            parent::$cached_secret_data[$key] = $re_value;
        } catch (Aws_Exception $e) {
            $err_message = "\nAWS Exception:\n" . $e->get_aws_error_message() . "\nUnable to read value for key {$key} from AWS Secrets Manager\n";
            // Print error message in console
            print_r($err_message);
            // Add error message in mftf log if verbose is enable
            if (Mftf_Application_Config::get_config()->verbose_enabled()) {
                Logging_Util::get_instance()->get_logger(Aws_Secrets_Manager_Storage::class)->debug($err_message);
            }
            // Save to exception context for Allure report
            Credential_Store::get_instance()->set_exception_contexts(Credential_Store::ARRAY_KEY_FOR_AWS_SECRETS_MANAGER, $err_message);
        } catch (\Exception $e) {
            $err_message = "\nException:\n" . $e->get_message() . "\nUnable to read value for key {$key} from AWS Secrets Manager\n";
            // Print error message in console
            print_r($err_message);
            // Add error message in mftf log if verbose is enable
            if (Mftf_Application_Config::get_config()->verbose_enabled()) {
                Logging_Util::get_instance()->get_logger(Aws_Secrets_Manager_Storage::class)->debug($err_message);
            }
            // Save to exception context for Allure report
            Credential_Store::get_instance()->set_exception_contexts(Credential_Store::ARRAY_KEY_FOR_AWS_SECRETS_MANAGER, $err_message);
        }
        return $re_value;
    }
    /**
     * Parse AWS result object and return secret for key
     *
     * @param Result $awsResult
     * @return string
     * @throws TestFrameworkException
     */
    private function parse_aws_secret_result(array $aws_result, string $key)
    {
        // Return secret from the associated KMS CMK
        if (isset($aws_result['SecretString'])) {
            $raw_secret = $aws_result['SecretString'];
        } else {
            throw new Test_Framework_Exception("'SecretString' field is not set in AWS Result. Error parsing result from AWS Secrets Manager");
        }
        // Secrets are saved as JSON structures of key/value pairs if using AWS Secrets Manager console, and
        // Secrets are saved as plain text if using AWS CLI. We need to handle both cases.
        $secret = json_decode($raw_secret, true);
        if (isset($secret[$key])) {
            return $secret[$key];
        }
        if (is_string($raw_secret)) {
            return $raw_secret;
        }
        throw new Test_Framework_Exception("{$key} not found or value is not string . Error parsing result from AWS Secrets Manager");
    }
    /**
     * Create Aws Secrets Manager client
     *
     * @param string $region
     * @param string $profile
     * @throws TestFrameworkException
     * @throws InvalidArgumentException
     */
    private function create_aws_secrets_manager_client($region, $profile): void
    {
        if (null !== $this->client) {
            return;
        }
        $options = ['region' => $region, 'version' => self::LATEST_VERSION];
        if (!empty($profile)) {
            $options['profile'] = $profile;
        }
        // Create AWS Secrets Manager client
        $this->client = new Secrets_Manager_Client($options);
        if ($this->client === null) {
            throw new Test_Framework_Exception('Unable to create AWS Secrets Manager client');
        }
    }
}