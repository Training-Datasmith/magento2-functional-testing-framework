<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataTransport\Protocol;

/**
 * Curl protocol interface.
 */
interface CurlInterface
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
    public function addOption($option, $value);

    /**
     * Send request to the remote server.
     *
     * @param string       $url
     * @param array|string $body
     * @param string       $method
     * @param array        $headers
     * @return void
     */
    public function write($url, $body = [], $method = CurlInterface::POST, $headers = []);

    /**
     * Read response from server.
     *
     * @param string      $successRegex
     * @param string      $returnRegex
     * @return string|array
     */
    public function read(?string $successRegex = null, ?string  $returnRegex = null, ?string  $returnIndex = null);

    /**
     * Close the connection to the server.
     *
     * @return void
     */
    public function close();
}
