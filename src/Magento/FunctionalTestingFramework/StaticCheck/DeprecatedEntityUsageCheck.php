<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Dom_Element;
use Dom_Node_List;
use Exception;
use InvalidArgumentException;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Data_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Operation_Definition_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Operation_Definition_Object;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Page\Objects\Element_Object;
use Magento\Functional_Testing_Framework\Page\Objects\Page_Object;
use Magento\Functional_Testing_Framework\Page\Objects\Section_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Group_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Object;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Spl_File_Info;
/**
 * Class DeprecatedEntityUsageCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Deprecated_Entity_Usage_Check implements Static_Check_Interface
{
    public const EXTENDS_REGEX_PATTERN = '/extends=["\']([^\'"]*)/';
    public const ACTIONGROUP_REGEX_PATTERN = '/ref=["\']([^\'"]*)/';
    public const DEPRECATED_REGEX_PATTERN = '/deprecated=["\']([^\'"]*)/';
    public const ERROR_LOG_FILENAME = 'mftf-deprecated-entity-usage-checks';
    public const ERROR_LOG_MESSAGE = 'MFTF Deprecated Entity Usage Check';
    /**
     * Array containing all errors found after running the execute() function
     */
    private array $errors = [];
    /**
     * String representing the output summary found after running the execute() function
     *
     * @var string
     */
    private $output;
    /**
     * ScriptUtil instance
     */
    private ?\Magento\Functional_Testing_Framework\Util\Script\Script_Util $script_util = null;
    /**
     * Data operations
     */
    private array $data_operations = ['create', 'update', 'get', 'delete'];
    /**
     * Test xml files to scan
     *
     * @var Finder|array
     */
    private $test_xml_files = [];
    /**
     * Action group xml files to scan
     *
     * @var Finder|array
     */
    private $action_group_xml_files = [];
    /**
     * Suite xml files to scan
     *
     * @var Finder|array
     */
    private $suite_xml_files = [];
    /**
     * Root suite xml files to scan
     *
     * @var Finder|array
     */
    private $root_suite_xml_files = [];
    /**
     * Data xml files to scan
     *
     * @var Finder|array
     */
    private $data_xml_files = [];
    /**
     * Checks test dependencies, determined by references in tests versus the dependencies listed in the Magento module
     *
     * @throws Exception
     */
    public function execute(Input_Interface $input): void
    {
        $this->script_util = new Script_Util();
        $this->load_all_xml_files($input);
        $this->errors = [];
        $this->errors += $this->find_reference_errors_in_action_files($this->test_xml_files);
        $this->errors += $this->find_reference_errors_in_action_files($this->action_group_xml_files);
        $this->errors += $this->find_reference_errors_in_action_files($this->suite_xml_files, true);
        if (!empty($this->root_suite_xml_files)) {
            $this->errors += $this->find_reference_errors_in_action_files($this->root_suite_xml_files, true);
        }
        $this->errors += $this->find_reference_errors_in_data_files($this->data_xml_files);
        // Hold on to the output and print any errors to a file
        $this->output = $this->script_util->print_errors_to_file($this->errors, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
    }
    /**
     * Return array containing all errors found after running the execute() function
     *
     * @return array
     */
    public function get_errors()
    {
        return $this->errors;
    }
    /**
     * Return string of a short human readable result of the check. For example: "No Dependency errors found."
     *
     * @return string
     */
    public function get_output()
    {
        return $this->output;
    }
    /**
     * Read all XML files for scanning
     *
     * @throws Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function load_all_xml_files(\Symfony\Component\Console\Input\Input_Interface $input): void
    {
        $module_paths = [];
        $include_root_path = true;
        $path = $input->get_option('path');
        if ($path) {
            if (!realpath($path)) {
                throw new InvalidArgumentException('Invalid --path option: ' . $path);
            }
            Mftf_Application_Config::create(true, Mftf_Application_Config::UNIT_TEST_PHASE, false, Mftf_Application_Config::LEVEL_DEFAULT, true);
            $module_paths[] = realpath($path);
            $include_root_path = false;
        } else {
            $module_paths = $this->script_util->get_all_module_paths();
        }
        // These files can contain references to other entities
        $this->test_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Test');
        $this->action_group_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'ActionGroup');
        $this->suite_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Suite');
        if ($include_root_path) {
            $this->root_suite_xml_files = $this->script_util->get_root_suite_xml_files();
        }
        $this->data_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Data');
        if (empty($this->thistest_xml_files) && empty($this->action_group_xml_files) && empty($this->suite_xml_files) && empty($this->data_xml_files)) {
            if ($path) {
                throw new InvalidArgumentException('Invalid --path option: ' . $path . PHP_EOL . 'Please make sure --path points to a valid MFTF Test Module.');
            }
            if (empty($this->root_suite_xml_files)) {
                throw new Test_Framework_Exception('No xml file to scan.');
            }
        }
    }
    /**
     * Find reference errors in set of action files
     *
     * @param Finder  $files
     * @throws XmlException
     */
    private function find_reference_errors_in_action_files($files, bool $check_test_ref = false): array
    {
        $test_errors = [];
        /** @var SplFileInfo $filePath */
        foreach ($files as $file_path) {
            $contents = file_get_contents($file_path);
            if ($this->is_deprecated($contents)) {
                continue;
            }
            preg_match_all(Action_Object::ACTION_ATTRIBUTE_VARIABLE_REGEX_PATTERN, $contents, $brace_references);
            preg_match_all(self::ACTIONGROUP_REGEX_PATTERN, $contents, $action_group_references);
            preg_match_all(self::EXTENDS_REGEX_PATTERN, $contents, $extend_references);
            $dom_document = new \Dom_Document();
            $dom_document->load($file_path);
            $created_data_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('createData'), 'entity');
            $updated_data_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('updateData'), 'entity');
            $get_data_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('getData'), 'entity');
            // Remove Duplicates
            $action_group_references[1] = array_unique($action_group_references[1]);
            $extend_references[1] = array_unique($extend_references[1]);
            $brace_references[0] = array_unique($brace_references[0]);
            $brace_references[2] = array_filter(array_unique($brace_references[2]));
            $created_data_references = array_unique($created_data_references);
            $updated_data_references = array_unique($updated_data_references);
            $get_data_references = array_unique($get_data_references);
            // Resolve entity references
            $entity_references = $this->script_util->resolve_entity_references($brace_references[0], $contents, true);
            // Resolve parameterized references
            $entity_references = array_merge($entity_references, $this->script_util->resolve_parametrized_references($brace_references[2], $contents, true));
            // Resolve action group entity by names
            $entity_references = array_merge($entity_references, $this->script_util->resolve_entity_by_names($action_group_references[1]));
            // Resolve extends entity by names
            $entity_references = array_merge($entity_references, $this->script_util->resolve_entity_by_names($extend_references[1]));
            // Resolve create data entity by names
            $entity_references = array_merge($entity_references, $this->script_util->resolve_entity_by_names($created_data_references));
            // Resolve update data entity by names
            $entity_references = array_merge($entity_references, $this->script_util->resolve_entity_by_names($updated_data_references));
            // Resolve get data entity by names
            $entity_references = array_merge($entity_references, $this->script_util->resolve_entity_by_names($get_data_references));
            // Find test references if needed
            if ($check_test_ref) {
                $entity_references = array_merge($entity_references, $this->resolve_test_entity_in_suite($dom_document));
            }
            // Find violating references
            $violating_references = $this->find_violating_references($entity_references);
            // Find metadata references from persist data
            $metadata_references = $this->get_metadata_from_data($created_data_references, 'create');
            $metadata_references = array_merge_recursive($metadata_references, $this->get_metadata_from_data($updated_data_references, 'update'));
            $metadata_references = array_merge_recursive($metadata_references, $this->get_metadata_from_data($get_data_references, 'get'));
            // Find violating references
            $violating_references = array_merge($violating_references, $this->find_violating_metadata_references($metadata_references));
            // Set error output
            $test_errors = array_merge($test_errors, $this->set_error_output($violating_references, $file_path));
        }
        return $test_errors;
    }
    /**
     * Checks if entity is deprecated in action files.
     * @param string $contents
     */
    private function is_deprecated(string|bool $contents): bool
    {
        preg_match_all(self::DEPRECATED_REGEX_PATTERN, $contents, $deprecated_entity);
        return !empty($deprecated_entity[1]);
    }
    /**
     * Find reference errors in a set of data files
     *
     * @param Finder $files
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function find_reference_errors_in_data_files($files): array
    {
        $test_errors = [];
        /** @var SplFileInfo $filePath */
        foreach ($files as $file_path) {
            $data_references = [];
            $metadata_references = [];
            $dom_document = new \Dom_Document();
            $dom_document->load($file_path);
            $entities = $dom_document->get_elements_by_tag_name('entity');
            foreach ($entities as $entity) {
                /** @var DOMElement $entity */
                $deprecated = $entity->get_attribute('deprecated');
                // skip check if entity is deprecated
                if (!empty($deprecated)) {
                    continue;
                }
                $entity_name = $entity->get_attribute('name');
                $metadata_type = $entity->get_attribute('type');
                $parent_entity_name = $entity->get_attribute('extends');
                if (!empty($metadata_type && !isset($metadata_references[$entity_name][$metadata_type]))) {
                    $metadata_references[$entity_name][$metadata_type] = 'all';
                }
                if (!empty($parent_entity_name)) {
                    $data_references[$entity_name][] = $parent_entity_name;
                }
                // Find metadata reference in `var` elements
                $var_elements = $entity->get_elements_by_tag_name('var');
                foreach ($var_elements as $var_element) {
                    /** @var DOMElement $varElement */
                    $metadata_type = $var_element->get_attribute('entityType');
                    if (!empty($metadata_type) && !isset($metadata_references[$entity_name][$metadata_type])) {
                        $metadata_references[$entity_name][$metadata_type] = 'all';
                    }
                }
                // Find metadata reference in `requiredEntity` elements, and
                // Find data references in `requiredEntity` elements
                $required_elements = $entity->get_elements_by_tag_name('requiredEntity');
                foreach ($required_elements as $required_element) {
                    /** @var DOMElement $requiredElement */
                    $metadata_type = $required_element->get_attribute('type');
                    if (!empty($metadata_type) && !isset($metadata_references[$entity_name][$metadata_type])) {
                        $metadata_references[$entity_name][$metadata_type] = 'all';
                    }
                    $data_references[$entity_name][] = $required_element->node_value;
                }
            }
            // Find violating references
            // Metadata references is unique
            $violating_references = $this->find_violating_metadata_references($metadata_references);
            // Data references is not unique
            $violating_references = array_merge_recursive($violating_references, $this->find_violating_data_references($this->two_dimension_array_unique($data_references)));
            // Set error output
            $test_errors = array_merge($test_errors, $this->set_error_output($violating_references, $file_path));
        }
        return $test_errors;
    }
    /**
     * Trim duplicate values from two-dimensional array. Dimension 1 array key is unique.
     */
    private function two_dimension_array_unique(array $in_array): array
    {
        $out_array = [];
        foreach ($in_array as $key => $arr) {
            $out_array[$key] = array_unique($arr);
        }
        return $out_array;
    }
    /**
     * Return attribute value for each node in DOMNodeList as an array
     */
    private function get_attributes_from_dom_node_list(\Dom_Node_List $nodes, string $attribute_name): array
    {
        $attributes = [];
        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            $attribute_value = $node->get_attribute($attribute_name);
            if (!empty($attribute_value)) {
                $attributes[] = $attribute_value;
            }
        }
        return $attributes;
    }
    /**
     * Find metadata from data array
     */
    private function get_metadata_from_data(array $references, string $type): array
    {
        $meta_data_references = [];
        try {
            foreach ($references as $data_name) {
                /** @var EntityDataObject $dataEntity */
                $data_entity = $this->script_util->find_entity($data_name);
                if ($data_entity) {
                    $metadata = $data_entity->get_type();
                    if (!empty($metadata)) {
                        $meta_data_references[$data_name][$metadata] = $type;
                    }
                }
            }
        } catch (Exception) {
        }
        return $meta_data_references;
    }
    /**
     * Find violating metadata references. Input array format is either
     *
     *  [
     *      $dataName1 => [
     *          $metaDataName1 = [
     *              'create',
     *              'update',
     *          ],
     *      ],
     *      $dataName2 => [
     *          $metaDataName2 => 'create',
     *      ],
     *      $dataName3 => [
     *          $metaDataName3 = [
     *              'get',
     *              'create',
     *              'update',
     *          ],
     *      ],
     *      ...
     *  ]
     *
     *  or
     *
     *  [
     *      $dataName1 => [
     *          $metaDataName1 => 'all',
     *          $metaDataName2 => 'all',
     *          ...
     *      ],
     *      $dataName2 => [
     *          $metaDataName2 => 'all',
     *          ...
     *      ],
     *      $dataName5 => [
     *          $metaDataName5 => 'all',
     *          $metaDataName4 => 'all',
     *          $metaDataName1 => 'all',
     *          ...
     *      ],
     *      ...
     *  ]
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function find_violating_metadata_references(array $references): array
    {
        $all_objects = Operation_Definition_Object_Handler::get_instance()->get_all_objects();
        // Find Violations
        $violating_references = [];
        foreach ($references as $data_name => $metadata_array) {
            foreach ($metadata_array as $metadata => $types) {
                $operations = [];
                $strict = true;
                if (is_array($types)) {
                    $operations = $types;
                } elseif ($types === 'all') {
                    $operations = $this->data_operations;
                    $strict = false;
                } else {
                    $operations = [$types];
                }
                $deprecated = null;
                $exists = false;
                foreach ($operations as $operation) {
                    if (array_key_exists($operation . $metadata, $all_objects)) {
                        $exists = true;
                        /** @var OperationDefinitionObject $entity */
                        $entity = $all_objects[$operation . $metadata];
                        // When not strictly checking, it's not deprecated as long as we found one that's not deprecated
                        if (!$strict && empty($entity->get_deprecated())) {
                            $deprecated = false;
                            break;
                        }
                        // When strictly checking, it's deprecated as long as we found one that's deprecated
                        if ($strict && !empty($entity->get_deprecated())) {
                            $deprecated = true;
                            break;
                        }
                    }
                }
                if ($exists && !$strict && $deprecated !== false) {
                    $deprecated = true;
                }
                if ($strict && $deprecated !== true) {
                    $deprecated = false;
                }
                if ($deprecated) {
                    $violating_references["\"{$data_name}\" references deprecated"][] = [
                        'name' => $metadata,
                        // TODO add filename in OperationDefinitionObject
                        'file' => 'metadata xml file',
                    ];
                }
            }
        }
        return $violating_references;
    }
    /**
     * Find violating data references. Input array format is
     *
     *  [
     *      $dataName1 => [ $requiredDataName1, $requiredDataName2, $requiredDataName3],
     *      $dataName2 => [ $requiredDataName2, $requiredDataName5, $requiredDataName7],
     *      ...
     *  ]
     *
     * @param array $references
     */
    private function find_violating_data_references($references): array
    {
        // Find Violations
        $violating_references = [];
        foreach ($references as $data_name => $required_data_names) {
            foreach ($required_data_names as $required_data_name) {
                try {
                    /** @var EntityDataObject $requiredData */
                    $required_data = Data_Object_Handler::get_instance()->get_object($required_data_name);
                    if ($required_data && $required_data->get_deprecated()) {
                        $violating_references["\"{$data_name}\" references deprecated"][] = ['name' => $required_data_name, 'file' => $required_data->get_filename()];
                    }
                } catch (Exception) {
                }
            }
        }
        return $violating_references;
    }
    /**
     * Find violating references. Input array format is
     *
     *  [
     *      'actionGroupName' => $actionGroupEntity,
     *      'dataGroupName' => $dataEntity,
     *      'testName' => $testEntity,
     *      'pageName' => $pageEntity,
     *      'section.field' => $fieldElementEntity,
     *      ...
     *  ]
     */
    private function find_violating_references(array $references): array
    {
        // Find Violations
        $violating_references = [];
        foreach ($references as $key => $entity) {
            if ($entity->get_deprecated()) {
                $class_type = $entity::class;
                $name = $entity->get_name();
                if ($class_type === Element_Object::class) {
                    $name = $key;
                    list($section, ) = explode('.', (string) $key, 2);
                    /** @var SectionObject $references[$section] */
                    $file = Static_Checks_List::get_file_path($references[$section]->get_filename());
                } else {
                    $file = Static_Checks_List::get_file_path($entity->get_filename());
                }
                $violating_references[$this->get_subject_from_class_type($class_type)][] = ['name' => $name, 'file' => $file];
            }
        }
        return $violating_references;
    }
    /**
     * Build and return error output for violating references
     *
     * @param SplFileInfo $path
     * @return array{non-falsy-string}[]
     */
    private function set_error_output(array $violating_references, $path): array
    {
        $test_errors = [];
        $file_path = Static_Checks_List::get_file_path($path->get_real_path());
        if (!empty($violating_references)) {
            // Build error output
            $error_output = "\nFile \"{$file_path}\" contains:\n";
            foreach ($violating_references as $subject => $data) {
                $error_output .= "\t- {$subject}:\n";
                foreach ($data as $item) {
                    $error_output .= "\t\t\"" . $item['name'] . '" in ' . $item['file'] . "\n";
                }
            }
            $test_errors[$file_path][] = $error_output;
        }
        return $test_errors;
    }
    /**
     * Resolve test entity in suite
     */
    private function resolve_test_entity_in_suite(\Dom_Document $dom_document): array
    {
        $test_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('test'), 'name');
        // Remove Duplicates
        $test_references = array_unique($test_references);
        // Resolve test entity by names
        try {
            return $this->script_util->resolve_entity_by_names($test_references);
        } catch (Xml_Exception) {
            return [];
        }
    }
    /**
     * Return subject string for a class name
     */
    private function get_subject_from_class_type(string $classname): ?string
    {
        $subject = null;
        if ($classname === Action_Group_Object::class) {
            $subject = 'Deprecated ActionGroup(s)';
        } elseif ($classname === Test_Object::class) {
            $subject = 'Deprecated Test(s)';
        } elseif ($classname === Section_Object::class) {
            $subject = 'Deprecated Section(s)';
        } elseif ($classname === Page_Object::class) {
            $subject = 'Deprecated Page(s)';
        } elseif ($classname === Element_Object::class) {
            $subject = 'Deprecated Element(s)';
        } elseif ($classname === Entity_Data_Object::class) {
            $subject = 'Deprecated Data(s)';
        } elseif ($classname === Operation_Definition_Object::class) {
            $subject = 'Deprecated Metadata(s)';
        }
        return $subject;
    }
}