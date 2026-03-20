<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Objects;

use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
/**
 * Class OperationDefinitionObject
 * @SuppressWarnings(PHPMD)
 */
class Operation_Definition_Object
{
    public const HTTP_CONTENT_TYPE_HEADER = 'Content-Type';
    /**
     * Api request url.
     *
     * @var string
     */
    private $api_url;
    /**
     * Resource specific URI for the request
     */
    private readonly string $api_uri;
    /**
     * Content type of body
     *
     * @var string
     */
    private $content_type;
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
        private $data_type,
        /**
         * Api method such as ('POST', 'PUT', 'GET', DELETE', etc.)
         */
        private $api_method,
        $api_uri,
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
        private $operation_metadata,
        $content_type,
        /**
         * Determines if operation should remove backend_name from URL.
         */
        private $remove_backend,
        /**
         * Regex to check for request success.
         */
        private $success_regex = null,
        /**
         * Regex to grab return value from response.
         */
        private $return_regex = null,
        /**
         * Index of element to be returned from "returnRegex" matches.
         */
        private $return_index = null,
        /**
         * Deprecated message.
         */
        private $deprecated = null
    )
    {
        $this->api_uri = trim($api_uri ?? '', '/');
        $this->api_url = null;
        if (!empty($content_type)) {
            $this->content_type = $content_type;
        } else {
            $this->content_type = 'application/x-www-form-urlencoded';
        }
        // add content type as a header
        $this->headers[] = self::HTTP_CONTENT_TYPE_HEADER . ': ' . $this->content_type;
    }
    /**
     * Getter for the deprecated attr of the section
     *
     * @return string
     */
    public function get_deprecated()
    {
        return $this->deprecated;
    }
    /**
     * Getter for data's data type
     *
     * @return string
     */
    public function get_data_type()
    {
        return $this->data_type;
    }
    /**
     * Getter for data operation
     *
     * @return string
     */
    public function get_operation()
    {
        return $this->operation;
    }
    /**
     * Getter for api method
     *
     * @return string
     */
    public function get_api_method()
    {
        return $this->api_method;
    }
    /**
     * Getter for api url for a store.
     *
     * @return string
     */
    public function get_api_url()
    {
        if (!$this->api_url) {
            $this->api_url = $this->api_uri;
            if (array_key_exists('query', $this->params)) {
                $this->add_query_params();
            }
        }
        return $this->api_url;
    }
    /**
     * Getter for auth path
     *
     * @return string
     */
    public function get_auth()
    {
        return $this->auth;
    }
    /**
     * Getter for request headers
     *
     * @return array
     */
    public function get_headers()
    {
        return $this->headers;
    }
    /**
     * Getter for removeBackend
     *
     * @return boolean
     */
    public function remove_url_backend()
    {
        return $this->remove_backend;
    }
    /**
     * Getter for Content-type
     *
     * @return string
     */
    public function get_content_type()
    {
        return $this->content_type;
    }
    /**
     * Getter for data metadata
     *
     * @return array
     */
    public function get_operation_metadata()
    {
        return $this->operation_metadata;
    }
    /**
     * Getter for success regex.
     *
     * @return string
     */
    public function get_success_regex()
    {
        return $this->success_regex;
    }
    /**
     * Getter for return regex.
     *
     * @return string
     */
    public function get_return_regex()
    {
        return $this->return_regex;
    }
    /**
     * Getter for return regex matches index.
     *
     * @return string|null
     */
    public function get_return_index()
    {
        return $this->return_index;
    }
    /**
     * Function to append or add query parameters
     */
    public function add_query_params(): void
    {
        foreach ($this->params['query'] as $param_name => $param_value) {
            if (!str_contains($this->api_url, '?')) {
                $this->api_url = $this->api_url . '?';
            } else {
                $this->api_url = $this->api_url . '&';
            }
            $this->api_url = $this->api_url . $param_name . '=' . $param_value;
        }
    }
    /**
     * Function to log a referenced deprecated operation at runtime.
     */
    public function log_deprecated(): void
    {
        if ($this->deprecated !== null) {
            Logging_Util::get_instance()->get_logger(self::class)->deprecation($message = "The operation {$this->name} is deprecated.", ['operationType' => $this->operation, 'deprecatedMessage' => $this->deprecated], true);
        }
    }
}