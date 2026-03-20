<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Util;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Data_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
class Data_Extension_Util
{
    /**
     * ObjectExtensionUtil constructor.
     */
    public function __construct()
    {
        // empty
    }
    /**
     * Resolves test references for extending test objects
     *
     * @param EntityDataObject $entityObject
     * @throws XmlException
     */
    public function extend_entity($entity_object): \Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object
    {
        // Check to see if the parent entity exists
        $parent_entity = Data_Object_Handler::get_instance()->get_object($entity_object->get_parent_name());
        if ($parent_entity === null) {
            throw new Xml_Exception('Parent Entity ' . $entity_object->get_parent_name() . ' not defined for Entity ' . $entity_object->get_name() . '.' . PHP_EOL);
        }
        // Check to see if the parent entity is already an extended entity
        if ($parent_entity->get_parent_name() !== null) {
            throw new Xml_Exception('Cannot extend an entity that already extends another entity. Entity: ' . $parent_entity->get_name() . '.' . PHP_EOL);
        }
        if (Mftf_Application_Config::get_config()->verbose_enabled() && Mftf_Application_Config::get_config()->get_phase() !== Mftf_Application_Config::UNIT_TEST_PHASE) {
            print 'Extending Data: ' . $parent_entity->get_name() . ' => ' . $entity_object->get_name() . PHP_EOL;
        }
        //get parent entity type if child does not have a type
        $new_type = $entity_object->get_type() ?? $parent_entity->get_type();
        // Get all data for both parent and child and merge
        $referenced_data = $parent_entity->get_all_data();
        $new_data = array_extend($referenced_data, $entity_object->get_all_data());
        // Get all linked references for both parent and child and merge
        $referenced_links = $parent_entity->get_linked_entities();
        $new_linked_references = array_merge($referenced_links, $entity_object->get_linked_entities());
        // Get all unique references for both parent and child and merge
        $referenced_unique_data = $parent_entity->get_uniqueness_data();
        $new_unique_references = array_merge($referenced_unique_data, $entity_object->get_uniqueness_data());
        // Get all var references for both parent and child and merge
        $referenced_vars = $parent_entity->get_var_references();
        $new_var_references = array_merge($referenced_vars, $entity_object->get_var_references());
        // Remove unique references for objects that are replaced without such reference
        $unmatched_unique_references = array_diff_key($referenced_unique_data, $entity_object->get_uniqueness_data());
        foreach ($unmatched_unique_references as $unique_key => $unique_data) {
            if (array_key_exists($unique_key, $entity_object->get_all_data())) {
                unset($new_unique_references[$unique_key]);
            }
        }
        // Create new entity object to return
        $extended_entity = new Entity_Data_Object($entity_object->get_name(), $new_type, $new_data, $new_linked_references, $new_unique_references, $new_var_references, $entity_object->get_parent_name(), $entity_object->get_filename(), $entity_object->get_deprecated());
        return $extended_entity;
    }
}