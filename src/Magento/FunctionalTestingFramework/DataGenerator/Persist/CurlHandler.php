<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Persist;

use Magento\Functional_Testing_Framework\Allure\Allure_Helper;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Operation_Definition_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Operation_Definition_Object;
use Magento\Functional_Testing_Framework\Data_Transport\Admin_Form_Executor;
use Magento\Functional_Testing_Framework\Data_Transport\Frontend_Form_Executor;
use Magento\Functional_Testing_Framework\Data_Transport\Protocol\Curl_Interface;
use Magento\Functional_Testing_Framework\Data_Transport\Web_Api_Executor;
use Magento\Functional_Testing_Framework\Data_Transport\Web_Api_No_Auth_Executor;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
/**
 * Class CurlHandler
 */
class Curl_Handler
{
    /**
     * The data definitions used to map the operation.
     *
     * @var OperationDefinitionObject $operationDefinition
     */
    private $operation_definition;
    /**
     * The request data.
     *
     * @var array
     */
    private $request_data;
    /**
     * If the content type is Json.
     */
    private bool $is_json;
    /**
     * Operation to Curl method mapping.
     */
    private static array $curl_method_mapping = ['create' => Curl_Interface::POST, 'delete' => Curl_Interface::DELETE, 'update' => Curl_Interface::PUT, 'get' => Curl_Interface::GET];
    /**
     * ApiSubObject constructor.
     *
     * @param string           $operation
     * @param EntityDataObject $entityObject
     * @param string           $storeCode
     */
    public function __construct(
        /**
         * Describes the operation for the executor ('create','update','delete')
         */
        private $operation,
        /**
         * The entity object data being created, updated, or deleted.
         */
        private $entity_object,
        /**
         * Store code in web api rest url.
         */
        private $store_code = null
    )
    {
        $this->operation_definition = Operation_Definition_Object_Handler::get_instance()->get_operation_definition($this->operation, $this->entity_object->get_type());
        $this->is_json = false;
    }
    /**
     * Executes an api request based on parameters given by constructor.
     *
     * @param array $dependentEntities
     * @return array | null
     * @throws TestFrameworkException
     * @throws \Exception
     */
    public function execute_request($dependent_entities)
    {
        $executor = null;
        $success_regex = null;
        $return_regex = null;
        $return_index = null;
        if (null !== $dependent_entities && is_array($dependent_entities)) {
            $entities = array_merge([$this->entity_object], $dependent_entities);
        } else {
            $entities = [$this->entity_object];
        }
        $api_url = $this->resolve_url_reference($this->operation_definition->get_api_url(), $entities);
        $headers = $this->operation_definition->get_headers();
        $authorization = $this->operation_definition->get_auth();
        $content_type = $this->operation_definition->get_content_type();
        $success_regex = $this->operation_definition->get_success_regex();
        $return_regex = $this->operation_definition->get_return_regex();
        $return_index = $this->operation_definition->get_return_index();
        $method = $this->operation_definition->get_api_method();
        $this->operation_definition->log_deprecated();
        Allure_Helper::add_attachment_to_current_step($api_url, 'API Endpoint');
        Allure_Helper::add_attachment_to_current_step(json_encode($headers, JSON_PRETTY_PRINT), 'Request Headers');
        $operation_data_resolver = new Operation_Data_Array_Resolver($dependent_entities);
        $this->request_data = $operation_data_resolver->resolve_operation_data_array($this->entity_object, $this->operation_definition->get_operation_metadata(), $this->operation_definition->get_operation(), false);
        Allure_Helper::add_attachment_to_current_step(json_encode($this->request_data, JSON_PRETTY_PRINT), 'Request Body');
        if ($content_type === 'application/json' && $authorization === 'adminOauth') {
            $this->is_json = true;
            $executor = new Web_Api_Executor($this->store_code);
        } elseif ($authorization === 'adminFormKey') {
            $executor = new Admin_Form_Executor($this->operation_definition->remove_url_backend());
        } elseif ($authorization === 'customerFormKey') {
            $executor = new Frontend_Form_Executor($this->request_data['customer_email'], $this->request_data['customer_password']);
        } elseif ($authorization === 'anonymous') {
            $this->is_json = true;
            $executor = new Web_Api_No_Auth_Executor($this->store_code);
        }
        if (!$executor) {
            throw new Test_Framework_Exception(sprintf("Invalid content type and/or auth type. content type = %s, auth type = %s\n", $content_type, $authorization));
        }
        $executor->write($api_url, $this->request_data, $method ?? self::$curl_method_mapping[$this->operation], $headers);
        $response = $executor->read($success_regex, $return_regex, $return_index);
        $executor->close();
        Allure_Helper::add_attachment_to_current_step(json_encode(json_decode($response, true), JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES), 'Response Data');
        return $response;
    }
    /**
     * Getter for request data in array.
     *
     * @return array
     */
    public function get_request_data_array()
    {
        return $this->request_data;
    }
    /**
     * If content type of a request is Json.
     *
     * @return boolean
     */
    public function is_content_type_json()
    {
        return $this->is_json;
    }
    /**
     * Resolve rul reference from entity objects.
     *
     * @param string $urlIn
     * @return string
     */
    private function resolve_url_reference($url_in, array $entity_objects)
    {
        $url_out = $url_in;
        $matched_params = [];
        // Find all the params ({}) references
        preg_match_all('/[{](.+?)[}]/', $url_in, $matched_params);
        foreach ($matched_params[0] as $param_key => $param_value) {
            $param_entity_parent = '';
            $matched_parent = [];
            $data_item = $matched_params[1][$param_key];
            // Find all the parent property (Type.key) references, assuming there will be only one
            // parent property reference within one param
            preg_match_all("/(.+?)\\./", $data_item, $matched_parent);
            if (!empty($matched_parent[0])) {
                $param_entity_parent = $matched_parent[1][0];
                $data_item = preg_replace('/^' . $matched_parent[0][0] . '/', '', $data_item);
            }
            foreach ($entity_objects as $entity_object) {
                $param = null;
                if ($param_entity_parent === '' || $entity_object->get_type() === $param_entity_parent) {
                    $param = $entity_object->get_data_by_name($data_item, Entity_Data_Object::CEST_UNIQUE_VALUE);
                }
                if (null !== $param) {
                    $url_out = str_replace($param_value, $param, $url_out);
                    continue;
                }
            }
        }
        return $url_out;
    }
}