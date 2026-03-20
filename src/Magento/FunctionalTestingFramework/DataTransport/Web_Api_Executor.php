<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport;

use Magento\Functional_Testing_Framework\Data_Transport\Auth\Web_Api_Auth;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Interface;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Transport;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Mftf_Globals;
/**
 * Curl executor for Magento Web Api requests.
 */
class Web_Api_Executor implements Curl_Interface
{
    /**
     * Curl transport protocol
     */
    private readonly \Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Transport $transport;
    /**
     * Rest request headers
     *
     * @var string[]
     */
    private array $headers = ['Accept: application/json', 'Content-Type: application/json'];
    /**
     * WebApiExecutor Constructor
     *
     * @param string $storeCode
     * @throws FastFailException
     */
    public function __construct(
        /**
         * Store code in API request
         */
        private readonly ?string $store_code = null
    )
    {
        $this->transport = new Curl_Transport();
        $this->authorize();
    }
    /**
     * Acquire and store the authorization token needed for REST requests
     *
     * @return void
     * @throws FastFailException
     */
    protected function authorize()
    {
        $this->headers = array_merge(['Authorization: Bearer ' . Web_Api_Auth::get_admin_token()], $this->headers);
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
        $this->transport->write($this->get_formatted_url($url), json_encode($data, JSON_PRETTY_PRINT), $method, array_unique(array_merge($headers, $this->headers)));
    }
    /**
     * Read response from server.
     *
     * @param string      $successRegex
     * @param string      $returnRegex
     * @return string
     * @throws TestFrameworkException
     */
    public function read(?string $success_regex = null, ?string $return_regex = null, ?string $return_index = null)
    {
        return $this->transport->read();
    }
    /**
     * Add additional option to cURL.
     *
     * @param  integer                      $option CURLOPT_* constants.
     * @param  integer|string|boolean|array $value
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
    /**
     * Builds and returns URL for request, appending storeCode if needed
     *
     * @param string $resource
     * @throws TestFrameworkException
     */
    protected function get_formatted_url($resource): string
    {
        $url_result = Mftf_Globals::get_web_api_base_url();
        if ($this->store_code !== null) {
            $url_result .= $this->store_code . '/';
        }
        return $url_result . trim($resource, '/');
    }
}