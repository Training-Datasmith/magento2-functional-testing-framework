<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Util;

use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Data_Object_Handler;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
/**
 * Class resolves data references in data entities at the runtime of tests.
 */
class Runtime_Data_Reference_Resolver implements Data_Reference_Resolver_Interface
{
    /**
     * Returns data by reference if reference exist.
     *
     * @return array|false|string|null
     * @throws TestReferenceException
     * @throws TestFrameworkException
     */
    public function get_data_reference(string $data, string $original_data_entity)
    {
        $result = null;
        preg_match(self::REFERENCE_REGEX_PATTERN, $data, $matches);
        if (empty($matches['reference'])) {
            return $data;
        }
        $stripped_reference = str_replace(['{{', '}}'], '', $matches['reference']);
        [$entity, $var] = explode('.', $stripped_reference);
        switch ($entity) {
            case Action_Object::__ENV:
                $result = str_replace($matches['reference'], getenv($var), $data);
                break;
            case Action_Object::__CREDS:
                $value = Credential_Store::get_instance()->get_secret($var);
                $result = Credential_Store::get_instance()->decrypt_secret_value($value);
                if ($result === false) {
                    throw new Test_Framework_Exception("\nFailed to decrypt value {$value}\n");
                }
                $result = str_replace($matches['reference'], $result, $data);
                break;
            default:
                $entity_object = Data_Object_Handler::get_instance()->get_object($entity);
                if ($entity_object === null) {
                    throw new Test_Reference_Exception("Could not find data entity by name \"{$entity_object}\" " . "referenced in Data entity \"{$original_data_entity}\"" . PHP_EOL);
                }
                $entity_data = $entity_object->get_all_data();
                if (!isset($entity_data[$var])) {
                    throw new Test_Reference_Exception("Could not resolve entity reference \"{$matches['reference']}\" " . "in Data entity \"{$original_data_entity}\"" . PHP_EOL);
                }
                $result = $entity_data[$var];
        }
        return $result;
    }
    /**
     * Returns data uniqueness for data entity field.
     *
     * @return string|null
     * @throws TestReferenceException
     */
    public function get_data_uniqueness(string $data, string $original_data_entity)
    {
        preg_match(Action_Object::ACTION_ATTRIBUTE_VARIABLE_REGEX_PATTERN, $data, $matches);
        if (empty($matches['reference'])) {
            return null;
        }
        $stripped_reference = str_replace(['{{', '}}'], '', $matches['reference']);
        [$entity, $var] = explode('.', $stripped_reference);
        $entity_object = Data_Object_Handler::get_instance()->get_object($entity);
        if ($entity_object === null) {
            throw new Test_Reference_Exception("Could not resolve entity reference \"{$matches['reference']}\" " . "in Data entity \"{$original_data_entity}\"");
        }
        return $entity_object->get_uniqueness_data_by_name($var);
    }
}