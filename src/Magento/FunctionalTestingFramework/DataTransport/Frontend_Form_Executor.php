<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport;

use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Interface;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Transport;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Mftf_Globals;
/**
 * Curl executor for requests to Frontend.
 */
class Frontend_Form_Executor implements Curl_Interface
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
     * Cookies data.
     */
    private string $cookies = '';
    /**
     * FrontendFormExecutor constructor.
     *
     * @param string $customerEmail
     * @param string $customerPassword
     *
     * @throws TestFrameworkException
     */
    public function __construct(
        /**
         * Customer email used for authentication.
         */
        private $customer_email,
        /**
         * Customer password used for authentication.
         */
        private $customer_password
    )
    {
        $this->transport = new Curl_Transport();
        $this->authorize();
    }
    /**
     * Authorize customer on frontend.
     *
     * @throws TestFrameworkException
     */
    private function authorize(): void
    {
        $url = Mftf_Globals::get_base_url() . 'customer/account/login/';
        $this->transport->write($url, [], Curl_Interface::GET);
        $this->read();
        $url = Mftf_Globals::get_base_url() . 'customer/account/loginPost/';
        $data = ['login[username]' => $this->customer_email, 'login[password]' => $this->customer_password, 'form_key' => $this->form_key];
        $this->transport->write($url, $data, Curl_Interface::POST, ['Set-Cookie:' . $this->cookies]);
        $response = $this->read();
        if (strpos($response, 'customer/account/login')) {
            throw new Test_Framework_Exception($this->customer_email . ', cannot be logged in by curl handler!');
        }
    }
    /**
     * Set Form Key from response.
     */
    private function set_form_key(): void
    {
        $str = substr($this->response, strpos($this->response, 'form_key'));
        preg_match('/value="(.*)" \/>/', $str, $matches);
        if (!empty($matches[1])) {
            $this->form_key = $matches[1];
        }
    }
    /**
     * Set Cookies from response.
     *
     * @return void
     */
    protected function set_cookies()
    {
        preg_match_all('|Set-Cookie: (.*);|U', $this->response, $matches);
        if (!empty($matches[1])) {
            $this->cookies = implode('; ', $matches[1]);
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
        if (isset($data['customer_email'])) {
            unset($data['customer_email']);
        }
        if (isset($data['customer_password'])) {
            unset($data['customer_password']);
        }
        $api_url = Mftf_Globals::get_base_url() . $url;
        if ($this->form_key) {
            $data['form_key'] = $this->form_key;
        } else {
            throw new Test_Framework_Exception(sprintf('Form key is absent! Url: "%s" Response: "%s"', $api_url, $this->response));
        }
        $headers = ['Set-Cookie:' . $this->cookies];
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
        $this->set_cookies();
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