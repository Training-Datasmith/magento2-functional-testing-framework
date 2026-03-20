<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Persist\Data_Persistence_Handler;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
class Persisted_Object_Handler
{
    public const HOOK_SCOPE = 'hook';
    public const TEST_SCOPE = 'test';
    public const SUITE_SCOPE = 'suite';
    /**
     * The singleton instance of this class
     */
    private static ?\Magento\Functional_Testing_Framework\Data_Generator\Handlers\Persisted_Object_Handler $INSTANCE = null;
    /**
     * Store of all hook created objects
     * @var DataPersistenceHandler[] array
     */
    private array $hook_objects = [];
    /**
     * Store of all test created objects
     * @var DataPersistenceHandler[] array
     */
    private array $test_objects = [];
    /**
     * Store of all suite created objects
     * @var DataPersistenceHandler[] array
     */
    private array $suite_objects = [];
    /**
     * Constructor
     */
    private function __construct()
    {
        // Empty Constructor
    }
    /**
     * Return the singleton instance of this class. Initialize it if needed.
     *
     * @return PersistedObjectHandler
     */
    public static function get_instance()
    {
        if (!self::$INSTANCE) {
            self::$INSTANCE = new Persisted_Object_Handler();
        }
        return self::$INSTANCE;
    }
    /**
     * Creates and stores the entity.
     * @param string $key                 StepKey of the createData action.
     * @param string $scope
     * @param string $entity              Name of xml entity to create.
     * @param array  $dependentObjectKeys StepKeys of other createData actions that are required.
     * @param array  $overrideFields      Array of FieldName => Value of override fields.
     * @param string $storeCode
     */
    public function create_entity(string $key, $scope, string $entity, $dependent_object_keys = [], $override_fields = [], ?string $store_code = ''): void
    {
        $retrieved_dependent_objects = [];
        foreach ($dependent_object_keys as $object_key) {
            $retrieved_dependent_objects[] = $this->retrieve_entity($object_key, $scope);
        }
        $retrieved_entity = Data_Object_Handler::get_instance()->get_object($entity);
        if ($retrieved_entity === null) {
            throw new Test_Reference_Exception('Entity "' . $entity . '" does not exist.' . "\nException occurred executing action at StepKey \"" . $key . '"');
        }
        $override_fields = $this->resolve_override_fields($override_fields);
        $persisted_object = new Data_Persistence_Handler($retrieved_entity, $retrieved_dependent_objects, $override_fields);
        $persisted_object->create_entity($store_code);
        if ($scope === self::TEST_SCOPE) {
            $this->test_objects[$key] = $persisted_object;
        } elseif ($scope === self::HOOK_SCOPE) {
            $this->hook_objects[$key] = $persisted_object;
        } else {
            $this->suite_objects[$key] = $persisted_object;
        }
    }
    /**
     * Retrieves and updates a previously created entity.
     * @param string $key                 StepKey of the createData action.
     * @param string $scope
     * @param string $updateEntity        Name of the static XML data to update the entity with.
     * @param array  $dependentObjectKeys StepKeys of other createData actions that are required.
     */
    public function update_entity($key, $scope, $update_entity, $dependent_object_keys = []): void
    {
        $retrieved_dependent_objects = [];
        foreach ($dependent_object_keys as $object_key) {
            $retrieved_dependent_objects[] = $this->retrieve_entity($object_key, $scope);
        }
        $original_entity = $this->retrieve_entity($key, $scope);
        $original_entity->update_entity($update_entity, $retrieved_dependent_objects);
    }
    /**
     * Retrieves and deletes a previously created entity.
     * @param string $key   StepKey of the createData action.
     * @param string $scope
     */
    public function delete_entity($key, $scope): void
    {
        $original_entity = $this->retrieve_entity($key, $scope);
        $original_entity->delete_entity();
    }
    /**
     * Performs GET on given entity and stores entity for use.
     * @param string  $key                 StepKey of getData action.
     * @param string  $scope
     * @param string  $entity              Name of XML static data to use.
     * @param array   $dependentObjectKeys StepKeys of other createData actions that are required.
     * @param string  $storeCode
     * @param integer $index
     */
    public function get_entity($key, $scope, $entity, $dependent_object_keys = [], ?string $store_code = '', ?string $index = null): void
    {
        $retrieved_dependent_objects = [];
        foreach ($dependent_object_keys as $object_key) {
            $retrieved_dependent_objects[] = $this->retrieve_entity($object_key, $scope);
        }
        $retrieved_entity = Data_Object_Handler::get_instance()->get_object($entity);
        $persisted_object = new Data_Persistence_Handler($retrieved_entity, $retrieved_dependent_objects);
        $persisted_object->get_entity($index, $store_code);
        if ($scope === self::TEST_SCOPE) {
            $this->test_objects[$key] = $persisted_object;
        } elseif ($scope === self::HOOK_SCOPE) {
            $this->hook_objects[$key] = $persisted_object;
        } else {
            $this->suite_objects[$key] = $persisted_object;
        }
    }
    /**
     * Retrieves a field from an entity, according to key and scope given.
     * @param string $stepKey
     * @param string $field
     * @param string $scope
     * @return string
     */
    public function retrieve_entity_field($step_key, $field, $scope)
    {
        $field_value = $this->retrieve_entity($step_key, $scope)->get_created_data_by_name($field);
        if ($field_value === null) {
            $warn_msg = "Undefined field {$field} in entity object with a stepKey of {$step_key}\n";
            $warn_msg .= 'Please fix the invalid reference. This will result in fatal error in next major release.';
            //TODO: change this to throw an exception in next major release
            Logging_Util::get_instance()->get_logger(Persisted_Object_Handler::class)->warning($warn_msg);
            if (Mftf_Application_Config::get_config()->verbose_enabled() && Mftf_Application_Config::get_config()->get_phase() !== Mftf_Application_Config::UNIT_TEST_PHASE) {
                print "\n{$warn_msg}\n";
            }
        }
        return $field_value;
    }
    /**
     * Attempts to retrieve Entity from given scope, falling back to outer scopes if not found.
     * @param string $stepKey
     * @param string $scope
     * @return DataPersistenceHandler
     * @throws TestReferenceException
     */
    private function retrieve_entity($step_key, $scope)
    {
        // Assume TEST_SCOPE is default
        $entity_arrays = [$this->test_objects, $this->hook_objects, $this->suite_objects];
        if ($scope === self::HOOK_SCOPE) {
            $entity_arrays[0] = $this->hook_objects;
            $entity_arrays[1] = $this->test_objects;
        }
        foreach ($entity_arrays as $entity_array) {
            if (array_key_exists($step_key, $entity_array)) {
                return $entity_array[$step_key];
            }
        }
        throw new Test_Reference_Exception("Entity with a CreateDataKey of {$step_key} could not be found");
    }
    /**
     * Clears store of all test persisted Objects
     */
    public function clear_test_objects(): void
    {
        $this->test_objects = [];
    }
    /**
     * Clears store of all hook persisted Objects
     */
    public function clear_hook_objects(): void
    {
        $this->hook_objects = [];
    }
    /**
     * Clears store of all suite persisted Objects
     */
    public function clear_suite_objects(): void
    {
        $this->suite_objects = [];
    }
    /**
     * Resolve secret values in $overrideFields
     */
    private function resolve_override_fields(array $override_fields): array
    {
        foreach ($override_fields as $index => $field) {
            if (is_array($field)) {
                $override_fields[$index] = $this->resolve_override_fields($field);
            } elseif (is_string($field)) {
                try {
                    $decrpted_field = Credential_Store::get_instance()->decrypt_all_secrets_in_string($field);
                    if ($decrpted_field !== false) {
                        $override_fields[$index] = $decrpted_field;
                    }
                } catch (Test_Framework_Exception) {
                    //catch exception if Credentials are not defined
                }
            }
        }
        return $override_fields;
    }
}