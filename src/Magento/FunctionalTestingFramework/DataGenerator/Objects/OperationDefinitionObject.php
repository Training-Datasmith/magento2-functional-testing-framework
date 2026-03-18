<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataGenerator\Objects;

use Magento\FunctionalTestingFramework\Util\Logger\LoggingUtil;

/**
 * Class OperationDefinitionObject
 * @SuppressWarnings(PHPMD)
 */
class OperationDefinitionObject
{
    public const HTTP_CONTENT_TYPE_HEADER = 'Content-Type';

    /**
     * Api request url.
     *
     * @var string
     */
    private $apiUrl;

    /**
     * Resource specific URI for the request
     */
    private readonly string $apiUri;

    /**
     * Content type of body
     *
     * @var string
     */
    private $contentType;

    /**
     * OperationDefinitionObject constructor.
     * @param string      $name
     * @param string      $operation
     * @param string      $dataType
     * @param string      $apiMethod
     * @param string      $apiUri
     * @param string      $auth
     * @param array       $headers
     * @param array       $params
     * @param array $operationMetadata
     * @param string      $contentType
     * @param boolean     $removeBackend
     * @param string      $successRegex
     * @param string      $returnRegex
     * @param string      $returnIndex
     * @param string|null $deprecated
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        /**
         * Data Definitions Name
         */
        private $name,
        /**
         * Operation which the data defintion describes
         */
        private $operation,
        /**
         * Data type for which the data defintiion is used
         */
        private $dataType,
        /**
         * Api method such as ('POST', 'PUT', 'GET', DELETE', etc.)
         */
        private $apiMethod,
        $apiUri,
        /**
         * Authorization path for retrieving a token
         */
        private $auth,
        /**
         * Relevant headers for the request
         */
        private $headers,
        /**
         * Relevant params for the request (e.g. query, path)
         */
        private $params,
        /**
         * The metadata describing the data fields and values themselves
         */
        private $operationMetadata,
        $contentType,
        /**
         * Determines if operation should remove backend_name from URL.
         */
        private $removeBackend,
        /**
         * Regex to check for request success.
         */
        private $successRegex = null,
        /**
         * Regex to grab return value from response.
         */
        private $returnRegex = null,
        /**
         * Index of element to be returned from "returnRegex" matches.
         */
        private $returnIndex = null,
        /**
         * Deprecated message.
         */
        private $deprecated = null
    ) {
        $this->apiUri = trim($apiUri ?? '', '/');
        $this->apiUrl = null;

        if (!empty($contentType)) {
            $this->contentType = $contentType;
        } else {
            $this->contentType = 'application/x-www-form-urlencoded';
        }

        // add content type as a header
        $this->headers[] = self::HTTP_CONTENT_TYPE_HEADER . ': ' . $this->contentType;
    }

    /**
     * Getter for the deprecated attr of the section
     *
     * @return string
     */
    public function getDeprecated()
    {
        return $this->deprecated;
    }

    /**
     * Getter for data's data type
     *
     * @return string
     */
    public function getDataType()
    {
        return $this->dataType;
    }

    /**
     * Getter for data operation
     *
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * Getter for api method
     *
     * @return string
     */
    public function getApiMethod()
    {
        return $this->apiMethod;
    }

    /**
     * Getter for api url for a store.
     *
     * @return string
     */
    public function getApiUrl()
    {
        if (!$this->apiUrl) {
            $this->apiUrl = $this->apiUri;

            if (array_key_exists('query', $this->params)) {
                $this->addQueryParams();
            }
        }

        return $this->apiUrl;
    }

    /**
     * Getter for auth path
     *
     * @return string
     */
    public function getAuth()
    {
        return $this->auth;
    }

    /**
     * Getter for request headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Getter for removeBackend
     *
     * @return boolean
     */
    public function removeUrlBackend()
    {
        return $this->removeBackend;
    }

    /**
     * Getter for Content-type
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Getter for data metadata
     *
     * @return array
     */
    public function getOperationMetadata()
    {
        return $this->operationMetadata;
    }

    /**
     * Getter for success regex.
     *
     * @return string
     */
    public function getSuccessRegex()
    {
        return $this->successRegex;
    }

    /**
     * Getter for return regex.
     *
     * @return string
     */
    public function getReturnRegex()
    {
        return $this->returnRegex;
    }

    /**
     * Getter for return regex matches index.
     *
     * @return string|null
     */
    public function getReturnIndex()
    {
        return $this->returnIndex;
    }

    /**
     * Function to append or add query parameters
     */
    public function addQueryParams(): void
    {
        foreach ($this->params['query'] as $paramName => $paramValue) {
            if (!str_contains($this->apiUrl, '?')) {
                $this->apiUrl = $this->apiUrl . '?';
            } else {
                $this->apiUrl = $this->apiUrl . '&';
            }
            $this->apiUrl = $this->apiUrl . $paramName . '=' . $paramValue;
        }
    }

    /**
     * Function to log a referenced deprecated operation at runtime.
     */
    public function logDeprecated(): void
    {
        if ($this->deprecated !== null) {
            LoggingUtil::getInstance()->getLogger(self::class)->deprecation(
                $message = "The operation {$this->name} is deprecated.",
                ['operationType' => $this->operation, 'deprecatedMessage' => $this->deprecated],
                true
            );
        }
    }
}
