<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport\Protocol;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
/**
 * HTTP Curl Adapter.
 */
class Curl_Transport implements Curl_Interface
{
    /**
     * Parameters array.
     *
     * @var array
     */
    protected $config = [];
    /**
     * Curl handle.
     *
     * @var resource
     */
    protected $resource;
    /**
     * Allow parameters.
     *
     * @var array
     */
    protected $allowed_params = ['timeout' => CURLOPT_TIMEOUT, 'maxredirects' => CURLOPT_MAXREDIRS, 'proxy' => CURLOPT_PROXY, 'ssl_cert' => CURLOPT_SSLCERT, 'userpwd' => CURLOPT_USERPWD];
    /**
     * Array of CURL options.
     *
     * @var array
     */
    protected $options = [];
    /**
     * A list of successful HTTP responses that will not trigger an exception.
     *
     * @var int[] SUCCESSFUL_HTTP_CODES
     */
    public const SUCCESSFUL_HTTP_CODES = [200, 201, 202, 203, 204, 205];
    /**
     * Apply current configuration array to curl resource.
     *
     * @return $this
     */
    protected function apply_config(): static
    {
        // apply additional options to cURL
        foreach ($this->options as $option => $value) {
            curl_setopt($this->get_resource(), $option, $value);
        }
        if (empty($this->config)) {
            return $this;
        }
        foreach (array_keys($this->config) as $param) {
            if (array_key_exists($param, $this->allowed_params)) {
                curl_setopt($this->get_resource(), $this->allowed_params[$param], $this->config[$param]);
            }
        }
        return $this;
    }
    /**
     * Set array of additional cURL options.
     *
     * @return $this
     */
    public function set_options(array $options = []): static
    {
        $this->options = $options;
        return $this;
    }
    /**
     * Add additional option to cURL.
     *
     * @param integer                      $option
     * @param integer|string|boolean|array $value
     * @return $this
     */
    public function add_option($option, $value): static
    {
        $this->options[$option] = $value;
        return $this;
    }
    /**
     * Set the configuration array for the adapter.
     *
     * @return $this
     */
    public function set_config(array $config = []): static
    {
        $this->config = $config;
        return $this;
    }
    /**
     * Send request to the remote server.
     *
     * @param string       $url
     * @param array|string $body
     * @param string       $method
     * @param array        $headers
     * @throws TestFrameworkException
     */
    public function write($url, $body = [], $method = Curl_Interface::POST, $headers = []): void
    {
        $this->apply_config();
        $options = [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEFILE => '', CURLOPT_HTTPHEADER => is_object($headers) ? (array) $headers : $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false];
        switch ($method) {
            case Curl_Interface::POST:
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = is_object($body) ? (array) $body : $body;
                break;
            case Curl_Interface::PUT:
                $options[CURLOPT_CUSTOMREQUEST] = self::PUT;
                $options[CURLOPT_POSTFIELDS] = is_object($body) ? (array) $body : $body;
                break;
            case Curl_Interface::DELETE:
                $options[CURLOPT_CUSTOMREQUEST] = self::DELETE;
                break;
            case Curl_Interface::GET:
                $options[CURLOPT_HTTPGET] = true;
                break;
            default:
                throw new Test_Framework_Exception("Undefined curl method: {$method}");
        }
        curl_setopt_array($this->get_resource(), $options);
    }
    /**
     * Read response from server.
     *
     * @param string      $successRegex
     * @param string      $returnRegex
     * @return string
     * @throws TestFrameworkException
     */
    public function read(?string $success_regex = null, ?string $return_regex = null, ?string $return_index = null): string|true
    {
        $response = curl_exec($this->get_resource());
        if ($response === false) {
            throw new Test_Framework_Exception(curl_error($this->get_resource()));
        }
        $http_code = $this->get_info(CURLINFO_HTTP_CODE);
        if (!in_array($http_code, self::SUCCESSFUL_HTTP_CODES)) {
            throw new Test_Framework_Exception('Error HTTP response code: ' . $http_code . '    Response:' . $response);
        }
        return $response;
    }
    /**
     * Close the connection to the server.
     */
    public function close(): void
    {
        $this->resource = null;
    }
    /**
     * Returns a cURL handle on success.
     *
     * @return resource
     */
    protected function get_resource()
    {
        if ($this->resource === null) {
            $this->resource = curl_init();
        }
        return $this->resource;
    }
    /**
     * Get last error number.
     */
    public function get_errno(): int
    {
        return curl_errno($this->get_resource());
    }
    /**
     * Get string with last error for the current session.
     */
    public function get_error(): string
    {
        return curl_error($this->get_resource());
    }
    /**
     * Get information regarding a specific transfer.
     *
     * @param integer $opt CURLINFO option.
     * @return string|array
     */
    public function get_info($opt = 0): array|false
    {
        return curl_getinfo($this->get_resource(), $opt);
    }
    /**
     * Provide curl_multi_* requests support.
     */
    public function multi_request(array $urls, array $options = []): array
    {
        $handles = [];
        $result = [];
        $multi_handle = curl_multi_init();
        foreach ($urls as $key => $url) {
            $handles[$key] = curl_init();
            curl_setopt($handles[$key], CURLOPT_URL, $url);
            curl_setopt($handles[$key], CURLOPT_HEADER, 0);
            curl_setopt($handles[$key], CURLOPT_RETURNTRANSFER, 1);
            if (!empty($options)) {
                curl_setopt_array($handles[$key], $options);
            }
            curl_multi_add_handle($multi_handle, $handles[$key]);
        }
        $process = null;
        do {
            curl_multi_exec($multi_handle, $process);
            usleep(100);
        } while ($process > 0);
        foreach ($handles as $key => $handle) {
            $result[$key] = curl_multi_getcontent($handle);
            curl_multi_remove_handle($multi_handle, $handle);
        }
        if (version_compare(PHP_VERSION, '8.0') < 0) {
            // this function no longer has an effect in PHP 8.0, but it's required in earlier versions
            curl_multi_close($multi_handle);
        }
        return $result;
    }
    /**
     * Extract the response code from a response string.
     *
     * @param string $responseStr
     * @return integer
     */
    public static function extract_code($response_str): int|false
    {
        preg_match("|^HTTP/[\\d\\.x]+ (\\d+)|", $response_str, $m);
        if (isset($m[1])) {
            return (int) $m[1];
        }
        return false;
    }
}