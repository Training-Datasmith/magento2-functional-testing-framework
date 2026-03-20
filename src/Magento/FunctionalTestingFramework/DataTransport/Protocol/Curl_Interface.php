<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Transport\Protocol;

/**
 * Curl protocol interface.
 */
interface Curl_Interface
{
    /**
     * HTTP request methods.
     */
    public const GET = 'GET';
    public const PUT = 'PUT';
    public const POST = 'POST';
    public const DELETE = 'DELETE';
    /**
     * Add additional option to cURL.
     *
     * @param integer                      $option
     * @param integer|string|boolean|array $value
     * @return $this
     */
    public function add_option($option, $value);
    /**
     * Send request to the remote server.
     *
     * @param string       $url
     * @param array|string $body
     * @param string       $method
     * @param array        $headers
     * @return void
     */
    public function write($url, $body = [], $method = Curl_Interface::POST, $headers = []);
    /**
     * Read response from server.
     *
     * @param string      $successRegex
     * @param string      $returnRegex
     * @return string|array
     */
    public function read(?string $success_regex = null, ?string $return_regex = null, ?string $return_index = null);
    /**
     * Close the connection to the server.
     *
     * @return void
     */
    public function close();
}