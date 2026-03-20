<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Module;

use Codeception\Module as CodeceptionModule;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Persisted_Object_Handler;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
/**
 * Class MagentoActionProxies
 *
 * Contains all proxy functions whose corresponding MFTF actions need to be accessible for AcceptanceTester $I
 *
 * @package Magento\FunctionalTestingFramework\Module
 */
class Magento_Action_Proxies extends Codeception_Module
{
    /**
     * Create an entity
     *
     * @param string $key                 StepKey of the createData action.
     * @param string $scope
     * @param string $entity              Name of xml entity to create.
     * @param array  $dependentObjectKeys StepKeys of other createData actions that are required.
     * @param array  $overrideFields      Array of FieldName => Value of override fields.
     * @param string $storeCode
     */
    public function create_entity($key, $scope, $entity, $dependent_object_keys = [], $override_fields = [], $store_code = ''): void
    {
        Persisted_Object_Handler::get_instance()->create_entity($key, $scope, $entity, $dependent_object_keys, $override_fields, $store_code);
    }
    /**
     * Retrieves and updates a previously created entity
     *
     * @param string $key                 StepKey of the createData action.
     * @param string $scope
     * @param string $updateEntity        Name of the static XML data to update the entity with.
     * @param array  $dependentObjectKeys StepKeys of other createData actions that are required.
     */
    public function update_entity($key, $scope, $update_entity, $dependent_object_keys = []): void
    {
        Persisted_Object_Handler::get_instance()->update_entity($key, $scope, $update_entity, $dependent_object_keys);
    }
    /**
     * Performs GET on given entity and stores entity for use
     *
     * @param string  $key                 StepKey of getData action.
     * @param string  $scope
     * @param string  $entity              Name of XML static data to use.
     * @param array   $dependentObjectKeys StepKeys of other createData actions that are required.
     * @param string  $storeCode
     * @param integer $index
     */
    public function get_entity($key, $scope, $entity, $dependent_object_keys = [], $store_code = '', $index = null): void
    {
        Persisted_Object_Handler::get_instance()->get_entity($key, $scope, $entity, $dependent_object_keys, $store_code, $index);
    }
    /**
     * Retrieves and deletes a previously created entity
     *
     * @param string $key   StepKey of the createData action.
     * @param string $scope
     */
    public function delete_entity($key, $scope): void
    {
        Persisted_Object_Handler::get_instance()->delete_entity($key, $scope);
    }
    /**
     * Retrieves a field from an entity, according to key and scope given
     *
     * @param string $stepKey
     * @param string $field
     * @param string $scope
     * @return string
     */
    public function retrieve_entity_field($step_key, $field, $scope)
    {
        return Persisted_Object_Handler::get_instance()->retrieve_entity_field($step_key, $field, $scope);
    }
    /**
     * Get encrypted value by key
     *
     * @param string $key
     * @return string|null
     * @throws TestFrameworkException
     */
    public function get_secret($key)
    {
        return Credential_Store::get_instance()->get_secret($key);
    }
    /**
     * Returns a value to origin of the action
     *
     * @param mixed $value
     * @return mixed
     */
    public function return($value)
    {
        return $value;
    }
}