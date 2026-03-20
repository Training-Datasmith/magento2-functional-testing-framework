<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Persist;

use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Data_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Object_Manager_Factory;
/**
 * Class DataPersistenceHandler
 */
class Data_Persistence_Handler
{
    /**
     * Entity object data to use for create, delete, or update.
     *
     * @var EntityDataObject $entityObject
     */
    private object $entity_object;
    /**
     * Resulting created object from create or update.
     */
    private ?\Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object $created_object = null;
    /**
     * Array of dependent entities, handed to CurlHandler when entity is created.
     */
    private ?array $dependent_objects = null;
    /**
     * Store code in web api rest url.
     */
    private ?string $store_code = null;
    /**
     * DataPersistenceHandler constructor.
     *
     * @param EntityDataObject $entityObject
     * @param array            $dependentObjects
     * @param array            $customFields
     */
    public function __construct($entity_object, $dependent_objects = [], $custom_fields = [])
    {
        // merge any custom fields into a new EntityDataObject for the persistence handler
        if (!empty($custom_fields)) {
            $this->entity_object = new Entity_Data_Object($entity_object->get_name(), $entity_object->get_type(), array_merge($entity_object->get_all_data(), $custom_fields), $entity_object->get_linked_entities(), $this->strip_custom_fields_from_uniqueness_data($entity_object->get_uniqueness_data(), $custom_fields), $entity_object->get_var_references(), $entity_object->get_parent_name(), $entity_object->get_filename(), $entity_object->get_deprecated());
        } else {
            $this->entity_object = clone $entity_object;
        }
        $this->store_code = null;
        foreach ($dependent_objects as $dependent_object) {
            $this->dependent_objects[] = $dependent_object->get_created_object();
        }
    }
    /**
     * Function which executes a create request based on specific operation metadata
     *
     * @param string $storeCode
     * @throws TestFrameworkException
     */
    public function create_entity(?string $store_code = null): void
    {
        if (!empty($store_code)) {
            $this->store_code = $store_code;
        }
        $curl_handler = Object_Manager_Factory::get_object_manager()->create(Curl_Handler::class, ['operation' => 'create', 'entityObject' => $this->entity_object, 'storeCode' => $this->store_code]);
        $result = $curl_handler->execute_request($this->dependent_objects);
        $this->set_created_object($result, null, $curl_handler->get_request_data_array(), $curl_handler->is_content_type_json());
    }
    /**
     * Function which executes a put request based on specific operation metadata.
     *
     * @param string $updateDataName
     * @param array  $updateDependentObjects
     * @throws TestFrameworkException
     * @throws \Exception
     */
    public function update_entity($update_data_name, $update_dependent_objects = []): void
    {
        foreach ($update_dependent_objects as $dependent_object) {
            $this->dependent_objects[] = $dependent_object->get_created_object();
        }
        $update_entity_object = Data_Object_Handler::get_instance()->get_object($update_data_name);
        $curl_handler = Object_Manager_Factory::get_object_manager()->create(Curl_Handler::class, ['operation' => 'update', 'entityObject' => $update_entity_object, 'storeCode' => $this->store_code]);
        $result = $curl_handler->execute_request(array_merge($this->dependent_objects, [$this->created_object]));
        $this->set_created_object($result, null, $curl_handler->get_request_data_array(), $curl_handler->is_content_type_json());
    }
    /**
     * Function which executes a get request on specific operation metadata.
     *
     * @param integer|null $index
     * @param string       $storeCode
     * @throws TestFrameworkException
     */
    public function get_entity(?string $index = null, ?string $store_code = null): void
    {
        if (!empty($store_code)) {
            $this->store_code = $store_code;
        }
        $curl_handler = Object_Manager_Factory::get_object_manager()->create(Curl_Handler::class, ['operation' => 'get', 'entityObject' => $this->entity_object, 'storeCode' => $this->store_code]);
        $result = $curl_handler->execute_request($this->dependent_objects);
        $this->set_created_object($result, $index, $curl_handler->get_request_data_array(), $curl_handler->is_content_type_json());
    }
    /**
     * Function which executes a delete request based on specific operation metadata
     *
     * @throws TestFrameworkException
     */
    public function delete_entity(): void
    {
        $curl_handler = Object_Manager_Factory::get_object_manager()->create(Curl_Handler::class, ['operation' => 'delete', 'entityObject' => $this->created_object, 'storeCode' => $this->store_code]);
        $curl_handler->execute_request($this->dependent_objects);
    }
    /**
     * Returns the created data object, instantiated when the entity is created via API.
     *
     * @return EntityDataObject
     */
    public function get_created_object()
    {
        return $this->created_object;
    }
    /**
     * Returns a specific data value based on the CreatedObject's definition.
     * @param string $dataName
     * @return string
     * @throws TestFrameworkException
     */
    public function get_created_data_by_name($data_name)
    {
        return $this->created_object->get_data_by_name($data_name, Entity_Data_Object::NO_UNIQUE_PROCESS);
    }
    /**
     * Save the created data object.
     *
     * @param string|array $response
     * @param integer|null $index
     * @param array        $requestDataArray
     * @param boolean      $isJson
     */
    private function set_created_object($response, ?string $index, $request_data_array, $is_json): void
    {
        if ($is_json) {
            $response_data = json_decode($response, true);
            if (is_array($response_data) && null !== $index) {
                $response_data = $response_data[$index];
            }
            if (is_array($response_data)) {
                $persisted_data = $this->convert_to_flat_array(array_merge($request_data_array, $this->convert_custom_attributes_array($response_data)));
            } else {
                $persisted_data = $this->convert_to_flat_array(array_merge($request_data_array, ['return' => $response_data]));
            }
        } else {
            $persisted_data = array_merge($this->convert_to_flat_array($request_data_array), ['return' => $response]);
        }
        $this->created_object = new Entity_Data_Object($this->entity_object->get_name(), $this->entity_object->get_type(), $persisted_data, null, null);
    }
    /**
     * Convert an multi-dimensional array to flat array.
     *
     * @param array  $arrayIn
     * @param string $rootKey
     */
    private function convert_to_flat_array($array_in, ?string $root_key = ''): array
    {
        $array_out = [];
        foreach ($array_in as $key => $value) {
            if (is_array($value)) {
                if (!empty($root_key)) {
                    $new_root_key = $root_key . '[' . $key . ']';
                } else {
                    $new_root_key = $key;
                }
                $array_out = array_merge($array_out, $this->convert_to_flat_array($value, $new_root_key));
            } elseif (!empty($root_key)) {
                $array_out[$root_key . '[' . $key . ']'] = $value;
            } else {
                $array_out[$key] = $value;
            }
        }
        return $array_out;
    }
    /**
     * Convert custom_attributes array from
     * e.g.
     * 'custom_attributes' => [
     *      0 => [
     *          'attribute_code' => 'code1',
     *          'value' => 'value1',
     *      ],
     *      1 => [
     *          'attribute_code' => 'code2',
     *          'value' => 'value2',
     *      ],
     *  ]
     *
     * To
     *
     * 'custom_attributes' => [
     *      'code1' => 'value1',
     *      'code2' => 'value2',
     *  ]
     */
    private function convert_custom_attributes_array(array $array_in): array
    {
        $keys = ['custom_attributes'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array_in)) {
                continue;
            }
            $array_copy = $array_in[$key];
            foreach ($array_copy as $attributes) {
                $array_in[$key][$attributes['attribute_code']] = $attributes['value'];
            }
        }
        return $array_in;
    }
    /**
     * Function to strip out any overwritten custom field uniqueness data. Takes the uniqueness array and the
     * customFields from the user and unsets any intersections.
     *
     * @param array $uniquenessData
     * @param array $customFields
     * @return array
     */
    private function strip_custom_fields_from_uniqueness_data($uniqueness_data, $custom_fields)
    {
        $new_uniqueness_array = $uniqueness_data;
        $intersecting_keys = array_intersect_key($uniqueness_data, $custom_fields);
        foreach ($intersecting_keys as $custom_field_key => $custom_field_value) {
            unset($new_uniqueness_array[$custom_field_key]);
        }
        return $new_uniqueness_array;
    }
}