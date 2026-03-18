<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataTransport;

use Magento\FunctionalTestingFramework\DataTransport\Protocol\CurlInterface;
use Magento\FunctionalTestingFramework\DataTransport\Protocol\CurlTransport;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\MftfGlobals;

/**
 * Curl executor for requests to Frontend.
 */
class FrontendFormExecutor implements CurlInterface
{
    /**
     * Curl transport protocol.
     */
    private readonly \Magento\FunctionalTestingFramework\DataTransport\Protocol\CurlTransport $transport;

    /**
     * Form key.
     */
    private ?string $formKey = null;

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
    public function __construct(/**
     * Customer email used for authentication.
     */
        private $customerEmail, /**
     * Customer password used for authentication.
     */
        private $customerPassword
    ) {
        $this->transport = new CurlTransport();
        $this->authorize();
    }

    /**
     * Authorize customer on frontend.
     *
     * @throws TestFrameworkException
     */
    private function authorize(): void
    {
        $url = MftfGlobals::getBaseUrl() . 'customer/account/login/';
        $this->transport->write($url, [], CurlInterface::GET);
        $this->read();

        $url = MftfGlobals::getBaseUrl() . 'customer/account/loginPost/';
        $data = [
            'login[username]' => $this->customerEmail,
            'login[password]' => $this->customerPassword,
            'form_key' => $this->formKey,
        ];
        $this->transport->write($url, $data, CurlInterface::POST, ['Set-Cookie:' . $this->cookies]);
        $response = $this->read();
        if (strpos($response, 'customer/account/login')) {
            throw new TestFrameworkException($this->customerEmail . ', cannot be logged in by curl handler!');
        }
    }

    /**
     * Set Form Key from response.
     */
    private function setFormKey(): void
    {
        $str = substr($this->response, strpos($this->response, 'form_key'));
        preg_match('/value="(.*)" \/>/', $str, $matches);
        if (!empty($matches[1])) {
            $this->formKey = $matches[1];
        }
    }

    /**
     * Set Cookies from response.
     *
     * @return void
     */
    protected function setCookies()
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
    public function write($url, $data = [], $method = CurlInterface::POST, $headers = []): void
    {
        if (isset($data['customer_email'])) {
            unset($data['customer_email']);
        }
        if (isset($data['customer_password'])) {
            unset($data['customer_password']);
        }
        $apiUrl = MftfGlobals::getBaseUrl() . $url;
        if ($this->formKey) {
            $data['form_key'] = $this->formKey;
        } else {
            throw new TestFrameworkException(
                sprintf('Form key is absent! Url: "%s" Response: "%s"', $apiUrl, $this->response)
            );
        }
        $headers = ['Set-Cookie:' . $this->cookies];
        $this->transport->write($apiUrl, str_replace('null', '', http_build_query($data)), $method, $headers);
    }

    /**
     * Read response from server.
     *
     * @param string      $successRegex
     * @param string      $returnRegex
     * @return string|array
     * @throws TestFrameworkException
     */
    public function read(?string $successRegex = null, ?string $returnRegex = null, ?string $returnIndex = null)
    {
        $this->response = $this->transport->read();
        $this->setCookies();
        $this->setFormKey();

        if (!empty($successRegex)) {
            preg_match($successRegex, $this->response, $successMatches);
            if (empty($successMatches)) {
                throw new TestFrameworkException("Entity creation was not successful! Response: $this->response");
            }
        }

        if (!empty($returnRegex)) {
            preg_match($returnRegex, $this->response, $returnMatches);
            if (!empty($returnMatches)) {
                return $returnMatches[$returnIndex] ?? $returnMatches[0];
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
}
