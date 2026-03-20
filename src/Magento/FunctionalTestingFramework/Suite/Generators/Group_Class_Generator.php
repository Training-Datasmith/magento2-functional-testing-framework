<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Suite\Generators;

use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Suite\Objects\Suite_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Hook_Object;
use Magento\Functional_Testing_Framework\Test\Util\Action_Object_Extractor;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
use Mustache_Engine;
use Mustache_loader_filesystem_Loader;
class Group_Class_Generator
{
    public const MUSTACHE_TEMPLATE_NAME = 'SuiteClass';
    public const SUITE_NAME_TAG = 'suiteName';
    public const TEST_COUNT_TAG = 'testCount';
    public const BEFORE_MUSTACHE_KEY = 'before';
    public const AFTER_MUSTACHE_KEY = 'after';
    public const ENTITY_NAME_TAG = 'entityName';
    public const ENTITY_MERGE_KEY = 'stepKey';
    public const REQUIRED_ENTITY_KEY = 'requiredEntities';
    public const LAST_REQUIRED_ENTITY_TAG = 'last';
    public const MUSTACHE_VAR_TAG = 'var';
    public const MAGENTO_CLI_COMMAND_COMMAND = 'command';
    public const REPLACEMENT_ACTIONS = ['comment' => 'print'];
    public const GROUP_DIR_NAME = 'Group';
    public const FUNCTION_PLACEHOLDER = 'PLACEHOLDER';
    public const FUNCTION_START = 'this->getModuleForAction("';
    public const FUNCTION_END = '")';
    public const FUNCTION_REPLACE_REGEX = '/(PLACEHOLDER->([^\(]+))\(/';
    /**
     * Mustache_Engine instance for template loading
     */
    private readonly \Mustache_Engine $mustache_engine;
    /**
     * Static function to return group directory path for precondition files.
     */
    public static function get_group_dir_path(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::GROUP_DIR_NAME . DIRECTORY_SEPARATOR;
    }
    /**
     * GroupClassGenerator constructor
     */
    public function __construct()
    {
        $this->mustache_engine = new Mustache_Engine(['loader' => new Mustache_loader_filesystem_Loader(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'), 'partials_loader' => new Mustache_loader_filesystem_Loader(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'partials')]);
    }
    /**
     * Method for adding preconditions and creating a corresponding group file for codeception. After generation,
     * the method returns the config path for the group file.
     *
     * @param SuiteObject $suiteObject
     * @throws TestReferenceException
     */
    public function generate_group_class($suite_object): string
    {
        $class_content = $this->create_class_content($suite_object);
        $config_entry = self::GROUP_DIR_NAME . DIRECTORY_SEPARATOR . $suite_object->get_name();
        $file_path = self::get_group_dir_path() . $suite_object->get_name() . '.php';
        file_put_contents($file_path, $class_content);
        return str_replace(DIRECTORY_SEPARATOR, '\\', $config_entry);
    }
    /**
     * Function to create group class content based on suite object definition.
     *
     * @param SuiteObject $suiteObject
     * @return string;
     * @throws TestReferenceException
     */
    private function create_class_content($suite_object)
    {
        $mustache_data = [];
        $mustache_data[self::SUITE_NAME_TAG] = $suite_object->get_name();
        $mustache_data[self::TEST_COUNT_TAG] = count($suite_object->get_tests());
        $mustache_data[self::BEFORE_MUSTACHE_KEY] = $this->build_hook_mustache_array($suite_object->get_before_hook());
        $mustache_data[self::AFTER_MUSTACHE_KEY] = $this->build_hook_mustache_array($suite_object->get_after_hook());
        $mustache_data[self::MUSTACHE_VAR_TAG] = $this->extract_class_var($mustache_data[self::BEFORE_MUSTACHE_KEY], $mustache_data[self::AFTER_MUSTACHE_KEY]);
        return $this->mustache_engine->render(self::MUSTACHE_TEMPLATE_NAME, $mustache_data);
    }
    /**
     * Function which takes the before and after arrays containing the steps for the hook objects and extracts
     * any variables names needed by the class template.
     */
    private function extract_class_var(array $before_array, array $after_array): array
    {
        $before_var = $before_array[self::MUSTACHE_VAR_TAG] ?? [];
        $after_var = $after_array[self::MUSTACHE_VAR_TAG] ?? [];
        return array_merge($before_var, $after_var);
    }
    /**
     * Function which takes hook objects and transforms data into array for mustache template engine.
     *
     * @param TestHookObject $hookObj
     * @return array
     * @throws TestReferenceException
     */
    private function build_hook_mustache_array($hook_obj)
    {
        $actions = [];
        $mustache_hook_array['actions'][] = ['webDriverInit' => true];
        $mustache_hook_array['helpers'] = [];
        foreach ($hook_obj->get_actions() as $action) {
            /** @var ActionObject $action */
            $index = count($actions);
            if ($action->get_type() === Action_Object::ACTION_TYPE_HELPER) {
                $mustache_hook_array['helpers'][] = $action->get_custom_action_attributes()['class'];
            }
            //deleteData contains either url or createDataKey, if it contains the former it needs special formatting
            if ($action->get_type() !== 'createData' && !array_key_exists(Test_Generator::REQUIRED_ENTITY_REFERENCE, $action->get_custom_action_attributes())) {
                $actions = $this->build_module_actions_mustache_array($action, $actions);
                continue;
            }
            // add these as vars to be created a class level in the template
            if ($action->get_type() === 'createData') {
                $mustache_hook_array[self::MUSTACHE_VAR_TAG][] = [self::ENTITY_MERGE_KEY => $action->get_step_key()];
            }
            $entity_array = [];
            $entity_array[self::ENTITY_MERGE_KEY] = $action->get_step_key();
            $entity_array[$action->get_type()] = $action->get_step_key();
            $entity_array = $this->build_persistence_mustache_array($action, $entity_array);
            $actions[$index] = $entity_array;
        }
        $mustache_hook_array['actions'] = array_merge($mustache_hook_array['actions'], $actions);
        $mustache_hook_array['actions'][] = ['webDriverReset' => true];
        return $mustache_hook_array;
    }
    /**
     * Takes an action object and array of generated action steps. Convert the action object into generated php and
     * appends the entry to the given array. The result is returned by the function.
     *
     * @param ActionObject $action
     * @param array        $actionEntries
     * @return array
     * @throws TestReferenceException
     */
    private function build_module_actions_mustache_array($action, $action_entries)
    {
        $step = Test_Generator::get_instance()->generate_steps_php([$action], Test_Generator::SUITE_SCOPE, self::FUNCTION_PLACEHOLDER);
        $raw_php = str_replace(["\t"], '', $step);
        $multiple_commands = explode(PHP_EOL, $raw_php, -1);
        $multiple_commands = array_filter($multiple_commands);
        foreach ($multiple_commands as $command) {
            $action_entries = $this->replace_reserved_tester_functions($command . PHP_EOL, $action_entries, self::FUNCTION_PLACEHOLDER);
        }
        return $action_entries;
    }
    /**
     * Takes a generated php step, an array containing generated php entries for the template, and the actor name
     * for the generated step.
     *
     * @param array  $actionEntries
     * @return array
     */
    private function replace_reserved_tester_functions(string $formatted_step, $action_entries, string $actor)
    {
        $formatted_step = rtrim($formatted_step);
        foreach (self::REPLACEMENT_ACTIONS as $test_action => $replacement) {
            $test_action_call = "\${$actor}->{$test_action}";
            if (str_starts_with($formatted_step, $test_action_call)) {
                $resulting_step = str_replace($test_action_call, $replacement, $formatted_step);
                $action_entries[] = ['action' => $resulting_step];
            } else {
                $placeholder = self::FUNCTION_PLACEHOLDER;
                $begin = self::FUNCTION_START;
                $end = self::FUNCTION_END;
                $resulting_step = preg_replace_callback(self::FUNCTION_REPLACE_REGEX, fn($matches) => str_replace($placeholder, $begin . $matches[2] . $end, $matches[1]) . '(', $formatted_step);
                $action_entries[] = ['action' => $resulting_step];
            }
        }
        return $action_entries;
    }
    /**
     * Takes an action object of persistence type and formats an array entiry for mustache template interpretation.
     *
     * @param ActionObject $action
     */
    private function build_persistence_mustache_array($action, array $entity_array): array
    {
        $entity_array[self::ENTITY_NAME_TAG] = $action->get_custom_action_attributes()['entity'] ?? $action->get_custom_action_attributes()[Test_Generator::REQUIRED_ENTITY_REFERENCE];
        // append entries for any required entities to this entry
        $required_entities = $this->build_req_entities_mustache_array($action->get_custom_action_attributes());
        if (!array_key_exists(-1, $required_entities)) {
            $entity_array[self::REQUIRED_ENTITY_KEY] = $required_entities;
        }
        // append entries for customFields if specified by the user.
        if (array_key_exists('customFields', $action->get_custom_action_attributes())) {
            $entity_array['customFields'] = $action->get_step_key() . 'Fields';
        }
        return $entity_array;
    }
    /**
     * Function which takes any required entities under a 'createData' tag and transforms data into array to be consumed
     * by mustache template.
     * (<createData entity="" stepKey="">
     *      <requiredEntity'...)
     *
     * @param array $customAttributes
     */
    private function build_req_entities_mustache_array($custom_attributes): array
    {
        $required_entities = [];
        foreach ($custom_attributes as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }
            if ($attribute[Action_Object_Extractor::NODE_NAME] === 'requiredEntity') {
                $required_entities[] = [self::ENTITY_NAME_TAG => $attribute[Test_Generator::REQUIRED_ENTITY_REFERENCE]];
            }
        }
        //append "last" attribute to final entry for mustache template (omit trailing comma)
        $required_entities[count($required_entities) - 1][self::LAST_REQUIRED_ENTITY_TAG] = true;
        return $required_entities;
    }
}