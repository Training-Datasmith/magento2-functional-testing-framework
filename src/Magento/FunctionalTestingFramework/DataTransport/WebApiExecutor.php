<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataTransport;

use Magento\FunctionalTestingFramework\Exceptions\FastFailException;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\MftfGlobals;
use Magento\FunctionalTestingFramework\DataTransport\Protocol\CurlInterface;
use Magento\FunctionalTestingFramework\DataTransport\Protocol\CurlTransport;
use Magento\FunctionalTestingFramework\DataTransport\Auth\WebApiAuth;

/**
 * Curl executor for Magento Web Api requests.
 */
class WebApiExecutor implements CurlInterface
{
    /**
     * Curl transport protocol
     */
    private readonly \Magento\FunctionalTestingFramework\DataTransport\Protocol\CurlTransport $transport;

    /**
     * Rest request headers
     *
     * @var string[]
     */
    private array $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    /**
     * WebApiExecutor Constructor
     *
     * @param string $storeCode
     * @throws FastFailException
     */
    public function __construct(/**
     * Store code in API request
     */
    private readonly ?string $storeCode = null)
    {
        $this->transport = new CurlTransport();
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
        $this->headers = array_merge(
            ['Authorization: Bearer ' . WebApiAuth::getAdminToken()],
            $this->headers
        );
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
    public function write($url, $data = [], $method = CurlInterface::POST, $headers = []): void
    {
        $this->transport->write(
            $this->getFormattedUrl($url),
            json_encode($data, JSON_PRETTY_PRINT),
            $method,
            array_unique(array_merge($headers, $this->headers))
        );
    }

    /**
     * Read response from server.
     *
     * @param string      $successRegex
     * @param string      $returnRegex
     * @return string
     * @throws TestFrameworkException
     */
    public function read(?string $successRegex = null, ?string $returnRegex = null, ?string  $returnIndex = null)
    {
        return $this->transport->read();
    }

    /**
     * Add additional option to cURL.
     *
     * @param  integer                      $option CURLOPT_* constants.
     * @param  integer|string|boolean|array $value
     */
    public function addOption($option, $value): void
    {
        $this->transport->addOption($option, $value);
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
    protected function getFormattedUrl($resource): string
    {
        $urlResult = MftfGlobals::getWebApiBaseUrl();
        if ($this->storeCode !== null) {
            $urlResult .= $this->storeCode . '/';
        }
        return $urlResult . trim($resource, '/');
    }
}
