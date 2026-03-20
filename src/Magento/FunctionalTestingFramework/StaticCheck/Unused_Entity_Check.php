<?php

declare (strict_types=1);
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Dom_Element;
use Exception;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Class UnusedEntityCheck
 *
 * @package Magento\FunctionalTestingFramework\StaticCheck
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Unused_Entity_Check implements Static_Check_Interface
{
    public const ERROR_LOG_FILENAME = 'mftf-unused-entity-usage-checks';
    public const ENTITY_REGEX_PATTERN = "/\\{(\\{)*([\\w.]+)(\\})*\\}/";
    public const SELECTOR_REGEX_PATTERN = '/selector=["\']([^\'"]*)/';
    public const ERROR_LOG_MESSAGE = 'MFTF Unused Entity Usage Check';
    public const SECTION_REGEX_PATTERN = "/\\w*Section\\b/";
    public const REQUIRED_ENTITY = '/<requiredEntity(.*?)>(.+?)<\/requiredEntity>/';
    public const ENTITY_SEPERATED_BY_DOT_REFERENCE = '/([\w]+)(\.)+([\w]+)/';
    /**
     * ScriptUtil instance
     */
    private ?\Magento\Functional_Testing_Framework\Util\Script\Script_Util $script_util = null;
    /**
     * @var array
     */
    private $errors = [];
    /**
     * @var string
     */
    private $output = '';
    /**
     * Checks test dependencies, determined by references in tests versus the dependencies listed in the Magento module
     *
     * @throws Exception
     */
    public function execute(Input_Interface $input): void
    {
        $this->errors = $this->unused_entities();
        $this->output = $this->script_util->print_errors_to_file($this->errors, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
    }
    /**
     * Centralized method to get unused Entities
     * @return array
     */
    public function unused_entities()
    {
        $this->script_util = new Script_Util();
        $dom_document = new \Dom_Document();
        $module_paths = $this->script_util->get_all_module_paths();
        $test_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Test');
        $action_group_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'ActionGroup');
        $data_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Data');
        $page_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Page');
        $section_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Section');
        $suite_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Suite');
        foreach ($data_xml_files as $file_path) {
            $dom_document->load($file_path);
            $entity_result = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('entity'), ['type' => 'name']);
            foreach ($entity_result as $entities_result_data) {
                $data_names[$entities_result_data[key($entities_result_data)]] = ['dataFilePath' => $file_path->get_real_path()];
            }
        }
        foreach ($action_group_xml_files as $file_path) {
            $dom_document->load($file_path);
            $action_group_name = $dom_document->get_elements_by_tag_name('actionGroup')->item(0)->get_attribute('name');
            if (!empty($dom_document->get_elements_by_tag_name('actionGroup')->item(0)->get_attribute('deprecated'))) {
                continue;
            }
            $all_action_group_file_names[$action_group_name] = $file_path->get_real_path();
        }
        foreach ($section_xml_files as $file_path) {
            $dom_document->load($file_path);
            $section_name = $dom_document->get_elements_by_tag_name('section')->item(0)->get_attribute('name');
            $section_file_names[$section_name] = $file_path->get_real_path();
        }
        foreach ($page_xml_files as $file_path) {
            $dom_document->load($file_path);
            $page_name = $dom_document->get_elements_by_tag_name('page')->item(0)->get_attribute('name');
            $page_files[$page_name] = $file_path->get_real_path();
        }
        $action_group_references = $this->unused_action_entity($dom_document, $action_group_xml_files, $test_xml_files, $all_action_group_file_names, $suite_xml_files);
        $entity_references = $this->unused_data($dom_document, $action_group_xml_files, $test_xml_files, $data_names, $data_xml_files);
        $pages_reference = $this->unused_page_entity($dom_document, $action_group_xml_files, $test_xml_files, $page_files, $suite_xml_files);
        $section_reference = $this->unused_section_entity($dom_document, $action_group_xml_files, $test_xml_files, $page_xml_files, $section_file_names, $suite_xml_files);
        return $this->set_error_output(array_merge(array_values($action_group_references), array_values($entity_references), array_values($pages_reference), array_values($section_reference)));
    }
    /**
     * Setting error message
     *
     * @throws Exception
     */
    private function set_error_output(array $unused_file_path): array
    {
        $test_errors = [];
        foreach ($unused_file_path as $files) {
            $contents = file_get_contents($files);
            $file = fopen($files, 'a');
            if (!str_contains($contents, '<!--@Ignore(Unused_Entity_Check)-->')) {
                fwrite($file, '<!--@Ignore(Unused_Entity_Check)-->');
            }
        }
        return $test_errors;
    }
    /**
     * Retrieves Unused Action Group Entities
     *
     * @param  DOMDocument $domDocument
     * @param  ScriptUtil  $actionGroupXmlFiles
     * @param  ScriptUtil  $testXmlFiles
     * @param  ScriptUtil  $allActionGroupFileNames
     * @param  ScriptUtil  $suiteXmlFiles
     * @throws Exception
     */
    public function unused_action_entity($dom_document, $action_group_xml_files, $test_xml_files, array $all_action_group_file_names, $suite_xml_files): array
    {
        foreach ($suite_xml_files as $file_path) {
            $dom_document->load($file_path);
            $references_suite = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('actionGroup'), 'ref');
            foreach ($references_suite as $references_result_suite) {
                if (isset($all_action_group_file_names[$references_result_suite])) {
                    unset($all_action_group_file_names[$references_result_suite]);
                }
            }
        }
        foreach ($action_group_xml_files as $file_path) {
            $dom_document->load($file_path);
            $action_group = $dom_document->get_elements_by_tag_name('actionGroup')->item(0);
            $references = $action_group->get_attribute('extends');
            if (in_array($references, array_keys($all_action_group_file_names))) {
                unset($all_action_group_file_names[$references]);
            }
        }
        foreach ($test_xml_files as $file_path) {
            $dom_document->load($file_path);
            $test_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('actionGroup'), 'ref');
            foreach ($test_references as $test_references_result) {
                if (isset($all_action_group_file_names[$test_references_result])) {
                    unset($all_action_group_file_names[$test_references_result]);
                }
            }
        }
        return $all_action_group_file_names;
    }
    /**
     * Retrieves Unused Page Entities
     *
     * @param  DOMDocument $domDocument
     * @return array
     * @throws Exception
     */
    public function unused_page_entity($dom_document, $action_group_xml_files, $test_xml_files, $page_names, $suite_xml_files)
    {
        foreach ($suite_xml_files as $file_path) {
            $dom_document->load($file_path);
            $contents = file_get_contents($file_path);
            $pages_references_in_suites = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('amOnPage'), 'url');
            foreach ($pages_references_in_suites as $pages_references_in_suites_result) {
                $explodepages_references_result = explode('.', trim((string) $pages_references_in_suites_result, '{}'));
                unset($page_names[$explodepages_references_result[0]]);
            }
            $page_names = $this->entity_reference_pattern_check($dom_document, $page_names, $contents, false, []);
        }
        foreach ($action_group_xml_files as $file_path) {
            $dom_document->load($file_path);
            $contents = file_get_contents($file_path);
            $pages_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('amOnPage'), 'url');
            foreach ($pages_references as $pages_references_result) {
                $explodepages_references_result = explode('.', trim((string) $pages_references_result, '{}'));
                unset($page_names[$explodepages_references_result[0]]);
            }
            $page_names = $this->entity_reference_pattern_check($dom_document, $page_names, $contents, false, []);
        }
        foreach ($test_xml_files as $file_path) {
            $dom_document->load($file_path);
            $contents = file_get_contents($file_path);
            $test_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('amOnPage'), 'url');
            foreach ($test_references as $pages_references_result) {
                $explodepages_references_result = explode('.', trim((string) $pages_references_result, '{}'));
                unset($page_names[$explodepages_references_result[0]]);
            }
            $page_names = $this->entity_reference_pattern_check($dom_document, $page_names, $contents, false, []);
        }
        return $page_names;
    }
    /**
     * Common Pattern Check Method
     *
     * @param  DOMDocument $domDocument
     * @param  string      $contents
     * @throws Exception
     */
    private function entity_reference_pattern_check($dom_document, array $file_names, string|bool $contents, bool $data, $remove_data_file_path): array
    {
        $section_argument_value_reference = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('argument'), 'value');
        $section_default_value_argument_reference = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('argument'), 'defaultValue');
        $section_argument_value = array_merge($section_argument_value_reference, $section_default_value_argument_reference);
        foreach ($section_argument_value as $section_argument_value_result) {
            $exploded_reference = str_contains((string) $section_argument_value_result, '$') ? explode('.', trim((string) $section_argument_value_result, '$')) : explode('.', trim((string) $section_argument_value_result, '{}'));
            if (in_array($exploded_reference[0], array_keys($file_names))) {
                $remove_data_file_path[] = $file_names[$exploded_reference[0]]['dataFilePath'] ?? [];
                unset($file_names[$exploded_reference[0]]);
            }
        }
        preg_match_all(self::ENTITY_REGEX_PATTERN, $contents, $bracket_references_data);
        preg_match_all(self::ENTITY_SEPERATED_BY_DOT_REFERENCE, $contents, $entity_seperated_by_dot_reference_action_group);
        $entity_reference_data_result_action_group = array_merge(array_unique($bracket_references_data[0]), array_unique($entity_seperated_by_dot_reference_action_group[0]));
        foreach (array_unique($entity_reference_data_result_action_group) as $bracket_references_results) {
            $bracket_references_data_result_output = explode('.', trim($bracket_references_results, '{}'));
            if (in_array($bracket_references_data_result_output[0], array_keys($file_names))) {
                $remove_data_file_path[] = $file_names[$bracket_references_data_result_output[0]]['dataFilePath'] ?? [];
                unset($file_names[$bracket_references_data_result_output[0]]);
            }
        }
        return $data === true ? ['dataFilePath' => $remove_data_file_path, 'fileNames' => $file_names] : $file_names;
    }
    /**
     * Retrieves Unused Section Entities
     *
     * @param  DOMDocument $domDocument
     * @param  ScriptUtil  $actionGroupXmlFiles
     * @param  ScriptUtil  $testXmlFiles
     * @param  ScriptUtil  $pageXmlFiles
     * @param  array       $sectionFileNames
     * @param  ScriptUtil  $suiteXmlFiles
     * @return array
     * @throws Exception
     */
    public function unused_section_entity($dom_document, $action_group_xml_files, $test_xml_files, $page_xml_files, $section_file_names, $suite_xml_files)
    {
        foreach ($suite_xml_files as $file_path) {
            $contents = file_get_contents($file_path);
            $dom_document->load($file_path);
            preg_match_all(self::SELECTOR_REGEX_PATTERN, $contents, $selector_references);
            if (isset($selector_references[1])) {
                foreach (array_unique($selector_references[1]) as $selector_references_result) {
                    $trim_selector = explode('.', trim($selector_references_result, '{{}}'));
                    if (isset($section_file_names[$trim_selector[0]])) {
                        unset($section_file_names[$trim_selector[0]]);
                    }
                }
            }
            $section_file_names = $this->entity_reference_pattern_check($dom_document, $section_file_names, $contents, false, []);
        }
        foreach ($action_group_xml_files as $file_path) {
            $contents = file_get_contents($file_path);
            $dom_document->load($file_path);
            preg_match_all(self::SELECTOR_REGEX_PATTERN, $contents, $selector_references);
            if (isset($selector_references[1])) {
                foreach (array_unique($selector_references[1]) as $selector_references_result) {
                    $trim_selector = explode('.', trim($selector_references_result, '{{}}'));
                    if (isset($section_file_names[$trim_selector[0]])) {
                        unset($section_file_names[$trim_selector[0]]);
                    }
                }
            }
            $section_file_names = $this->entity_reference_pattern_check($dom_document, $section_file_names, $contents, false, []);
        }
        return $this->get_unused_section_entities_reference_in_action_group_and_test_files($test_xml_files, $page_xml_files, $dom_document, $section_file_names);
    }
    /**
     * Get unused section entities reference in Action group and Test files
     * @param  ScriptUtil  $testXmlFiles
     * @param  ScriptUtil  $pageXmlFiles
     * @param  DOMDocument $domDocument
     * @param  array       $sectionFileNames
     * @return array
     * @throws Exception
     */
    private function get_unused_section_entities_reference_in_action_group_and_test_files($test_xml_files, $page_xml_files, $dom_document, $section_file_names)
    {
        foreach ($test_xml_files as $file_path) {
            $contents = file_get_contents($file_path);
            $dom_document->load($file_path);
            preg_match_all(self::SELECTOR_REGEX_PATTERN, $contents, $selector_references);
            if (isset($selector_references[1])) {
                foreach (array_unique($selector_references[1]) as $selector_references_result) {
                    $trim_selector = explode('.', trim($selector_references_result, '{{}}'));
                    if (isset($section_file_names[$trim_selector[0]])) {
                        unset($section_file_names[$trim_selector[0]]);
                    }
                }
            }
            $section_file_names = $this->entity_reference_pattern_check($dom_document, $section_file_names, $contents, false, []);
        }
        foreach ($page_xml_files as $file_path) {
            $contents = file_get_contents($file_path);
            preg_match_all(self::SELECTOR_REGEX_PATTERN, $contents, $selector_references);
            if (isset($selector_references[1])) {
                foreach (array_unique($selector_references[1]) as $selector_references_result) {
                    $trim_selector = explode('.', trim($selector_references_result, '{{}}'));
                    if (isset($section_file_names[$trim_selector[0]])) {
                        unset($section_file_names[$trim_selector[0]]);
                    }
                }
            }
            $section_file_names = $this->entity_reference_pattern_check($dom_document, $section_file_names, $contents, false, []);
        }
        return $section_file_names;
    }
    /**
     * Return Unused Data entities
     *
     * @param  DOMDocument $domDocument
     */
    public function unused_data($dom_document, $action_group_xml_files, $test_xml_files, array $data_names, $data_xml_files): array
    {
        $remove_data_file_path = [];
        foreach ($data_xml_files as $file_path) {
            $dom_document->load($file_path);
            $contents = file_get_contents($file_path);
            preg_match_all(self::REQUIRED_ENTITY, $contents, $required_entity_reference);
            foreach ($required_entity_reference[2] as $required_entity_reference_result) {
                if (isset($data_names[$required_entity_reference_result])) {
                    $remove_data_file_path[] = $data_names[$required_entity_reference_result]['dataFilePath'];
                    unset($data_names[$required_entity_reference_result]);
                }
            }
        }
        foreach ($action_group_xml_files as $file_path) {
            $dom_document->load($file_path);
            $contents = file_get_contents($file_path);
            $get_unused_file_path = $this->entity_reference_pattern_check($dom_document, $data_names, $contents, true, $remove_data_file_path);
            $data_names = $get_unused_file_path['fileNames'];
            $remove_data_file_path = $get_unused_file_path['dataFilePath'];
        }
        foreach ($test_xml_files as $file_path) {
            $dom_document->load($file_path);
            $contents = file_get_contents($file_path);
            $get_unused_file_path = $this->entity_reference_pattern_check($dom_document, $data_names, $contents, true, $remove_data_file_path);
            $data_names = $get_unused_file_path['fileNames'];
            $remove_data_file_path = $get_unused_file_path['dataFilePath'];
            $created_data_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('createData'), 'entity');
            $updated_data_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('updateData'), 'entity');
            $get_data_references = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name('getData'), 'entity');
            $data_references = array_unique(array_merge($created_data_references, $updated_data_references, $get_data_references));
            foreach ($data_references as $data_references_result) {
                if (isset($data_names[$data_references_result])) {
                    $remove_data_file_path[] = $data_names[$data_references_result]['dataFilePath'];
                    unset($data_names[$data_references_result]);
                }
            }
        }
        $data_file_path_result = $this->unset_file_path($data_names, $remove_data_file_path);
        return array_unique($data_file_path_result);
    }
    /**
     * Remove used entities file path from unused entities array
     */
    private function unset_file_path(array $data_names, $remove_data_file_path): array
    {
        $data_file_path_result = [];
        foreach ($data_names as $key => $data_names_result) {
            if (in_array($data_names_result['dataFilePath'], $remove_data_file_path)) {
                unset($data_names[$key]);
                continue;
            }
            $data_file_path_result[] = $data_names[$key]['dataFilePath'];
        }
        return array_unique($data_file_path_result);
    }
    /**
     * Return attribute value for each node in DOMNodeList as an array
     *
     * @param  DOMNodeList $nodes
     * @param  string      $attributeName
     */
    private function get_attributes_from_dom_node_list($nodes, array|string $attribute_name): array
    {
        $attributes = [];
        foreach ($nodes as $node) {
            if (is_string($attribute_name)) {
                $attribute_value = $node->get_attribute($attribute_name);
            } else {
                $attribute_value = [$node->get_attribute(key($attribute_name)) => $node->get_attribute($attribute_name[key($attribute_name)])];
            }
            if (!empty($attribute_value)) {
                $attributes[] = $attribute_value;
            }
        }
        return $attributes;
    }
    /**
     * Extract actionGroup DomElement from xml file
     */
    public function get_action_group_dom_element(string $contents): Dom_Element
    {
        $dom_document = new \Dom_Document();
        $dom_document->load_xml($contents);
        return $dom_document->get_elements_by_tag_name('actionGroup')[0];
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
     * Return output
     *
     * @return string
     */
    public function get_output()
    {
        return $this->output;
    }
}