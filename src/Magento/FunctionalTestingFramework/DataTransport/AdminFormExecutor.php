<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport;

use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store;
use Magento\Functional_Testing_Framework\Data_Transport\Auth\Tfa;
use Magento\Functional_Testing_Framework\Data_Transport\Auth\Tfa\OTP;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Interface;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Transport;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Mftf_Globals;
/**
 * Curl executor for requests to Admin.
 */
class Admin_Form_Executor implements Curl_Interface
{
    /**
     * Curl transport protocol.
     */
    private readonly \Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Transport $transport;
    /**
     * Form key.
     */
    private ?string $form_key = null;
    /**
     * Response data.
     *
     * @var string
     */
    private $response;
    /**
     * Constructor.
     * @param boolean $removeBackend
     *
     * @constructor
     * @throws TestFrameworkException
     */
    public function __construct(
        /**
         * Flag describes whether the request is to Magento Base URL, removes backend_name from api url
         */
        private $remove_backend
    )
    {
        $this->transport = new Curl_Transport();
        $this->authorize();
    }
    /**
     * Authorize admin on backend.
     *
     * @throws TestFrameworkException
     */
    private function authorize(): void
    {
        // Perform GET to backend url so form_key is set
        $this->transport->write(Mftf_Globals::get_backend_base_url(), [], Curl_Interface::GET);
        $this->read();
        // Authenticate admin user
        $auth_url = Mftf_Globals::get_backend_base_url() . 'admin/auth/login/';
        $encrypted_secret = Credential_Store::get_instance()->get_secret('magento/MAGENTO_ADMIN_PASSWORD');
        $secret = Credential_Store::get_instance()->decrypt_secret_value($encrypted_secret);
        $data = ['login[username]' => getenv('MAGENTO_ADMIN_USERNAME'), 'login[password]' => $secret, 'form_key' => $this->form_key];
        $this->transport->write($auth_url, $data, Curl_Interface::POST);
        $response = $this->read();
        if (strpos($response, 'login-form')) {
            throw new Test_Framework_Exception('Admin user authentication failed!');
        }
        // Get OTP
        if (Tfa::is_enabled()) {
            $auth_url = Mftf_Globals::get_backend_base_url() . Tfa::get_provider_admin_form_endpoint('google');
            $data = ['tfa_code' => OTP::get_otp(), 'form_key' => $this->form_key];
            $this->transport->write($auth_url, $data, Curl_Interface::POST);
            $response = json_decode($this->read());
            if (!$response->success) {
                throw new Test_Framework_Exception('Admin user 2FA authentication failed!');
            }
        }
    }
    /**
     * Set Form Key from response.
     */
    private function set_form_key(): void
    {
        preg_match('!var FORM_KEY = \'(\w+)\';!', $this->response, $matches);
        if (!empty($matches[1])) {
            $this->form_key = $matches[1];
        }
    }
    /**
     * Send request to the remote server.
     *
     * @param string $url
     * @param array  $data
     * @param string $method
     * @param array  $headers
     * @throws TestFrameworkException
     */
    public function write($url, $data = [], $method = Curl_Interface::POST, $headers = []): void
    {
        $url = ltrim($url, '/');
        $api_url = Mftf_Globals::get_backend_base_url() . $url;
        if ($this->remove_backend) {
            //TODO
            //Cannot find usage. Do we need this?
        }
        if ($this->form_key) {
            $data['form_key'] = $this->form_key;
        } else {
            throw new Test_Framework_Exception(sprintf('Form key is absent! Url: "%s" Response: "%s"', $api_url, $this->response));
        }
        $this->transport->write($api_url, str_replace('null', '', http_build_query($data)), $method, $headers);
    }
    /**
     * Read response from server.
     *
     * @param string      $successRegex
     * @param string      $returnRegex
     * @return string|array
     * @throws TestFrameworkException
     */
    public function read(?string $success_regex = null, ?string $return_regex = null, ?string $return_index = null)
    {
        $this->response = $this->transport->read();
        $this->set_form_key();
        if (!empty($success_regex)) {
            preg_match($success_regex, $this->response, $success_matches);
            if (empty($success_matches)) {
                throw new Test_Framework_Exception("Entity creation was not successful! Response: {$this->response}");
            }
        }
        if (!empty($return_regex)) {
            preg_match($return_regex, $this->response, $return_matches);
            if (!empty($return_matches)) {
                return $return_matches[$return_index] ?? $return_matches[0];
            }
        }
        return $this->response;
    }
    /**
     * Add additional option to cURL.
     *
     * @param integer                      $option CURLOPT_* constants.
     * @param integer|string|boolean|array $value
     */
    public function add_option($option, $value): void
    {
        $this->transport->add_option($option, $value);
    }
    /**
     * Close the connection to the server.
     */
    public function close(): void
    {
        $this->transport->close();
    }
}