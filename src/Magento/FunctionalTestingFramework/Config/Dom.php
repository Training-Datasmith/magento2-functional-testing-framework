<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

use Magento\Functional_Testing_Framework\Config\Dom\Validation_Exception;
/**
 * Magento configuration XML DOM utility
 */
class Dom
{
    /**
     * Prefix which will be used for root namespace
     */
    public const ROOT_NAMESPACE_PREFIX = 'x';
    /**
     * Format of items in errors array to be used by default. Available placeholders - fields of \LibXMLError.
     */
    public const ERROR_FORMAT_DEFAULT = "%message%\nLine: %line%\n";
    /**
     * Dom document
     *
     * @var \DOMDocument
     */
    protected $dom;
    /**
     * Configuration of identifier attributes to be taken into account during merging.
     */
    protected \Magento\Functional_Testing_Framework\Config\Dom\Node_Merging_Config $node_merging_config;
    /**
     * Default namespace for xml elements
     */
    protected ?string $root_namespace;
    /**
     * Build DOM with initial XML contents and specifying identifier attributes for merging
     *
     * Format of $idAttributes: array('/xpath/to/some/node' => 'id_attribute_name')
     * The path to ID attribute name should not include any attribute notations or modifiers -- only node names
     *
     * @param string $xml
     * @param string $typeAttributeName
     * @param string $schemaFile
     * @param string $errorFormat
     */
    public function __construct(
        $xml,
        array $id_attributes = [],
        /**
         * Name of attribute that specifies type of argument node
         */
        protected $type_attribute_name = null,
        /**
         * Schema validation file
         */
        protected $schema_file = null,
        /**
         * Format of error messages
         */
        protected $error_format = self::ERROR_FORMAT_DEFAULT
    )
    {
        $this->node_merging_config = new Dom\Node_Merging_Config(new Dom\Node_Path_Matcher(), $id_attributes);
        $this->dom = $this->init_dom($xml);
        $this->root_namespace = $this->dom->lookup_namespace_uri($this->dom->namespace_uri);
    }
    /**
     * Merge $xml into DOM document
     *
     * @param string             $xml
     * @param string             $filename
     * @param ExceptionCollector $exceptionCollector
     */
    public function merge($xml, $filename = null, $exception_collector = null): void
    {
        $dom = $this->init_dom($xml, $filename);
        $this->merge_node($dom->document_element, '');
    }
    /**
     * Recursive merging of the \DOMElement into the original document
     *
     * Algorithm:
     * 1. Find the same node in original document
     * 2. Extend and override original document node attributes and scalar value if found
     * 3. Append new node if original document doesn't have the same node
     *
     * @param string      $parentPath Path to parent node.
     * @return void
     */
    protected function merge_node(\Dom_Element $node, $parent_path)
    {
        $path = $this->get_node_path_by_parent($node, $parent_path);
        $matched_node = $this->get_matched_node($path);
        /* Update matched node attributes and value */
        if ($matched_node) {
            $this->merge_matching_node($node, $parent_path, $matched_node, $path);
        } else {
            /* Add node as is to the document under the same parent element */
            $parent_matched_node = $this->get_matched_node($parent_path);
            $new_node = $this->dom->import_node($node, true);
            $parent_matched_node->append_child($new_node);
        }
    }
    /**
     * Function to process matching node merges. Broken into shared logic for extending classes.
     *
     * @param string      $parentPath
     * @param |DomElement $matchedNode
     * @param string      $path
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @TODO Ported magento code - to be refactored later
     */
    protected function merge_matching_node(\Dom_Element $node, $parent_path, $matched_node, $path)
    {
        //different node type
        if ($this->type_attribute_name && $node->has_attribute($this->type_attribute_name) && $matched_node->has_attribute($this->type_attribute_name) && $node->get_attribute($this->type_attribute_name) !== $matched_node->get_attribute($this->type_attribute_name)) {
            $this->replace_node_value($parent_path, $node, $matched_node);
            return;
        }
        $this->merge_attributes($matched_node, $node);
        if ($node->node_value === '' && $matched_node->node_value !== '' && $matched_node->child_nodes->length === 1) {
            $this->replace_node_value($parent_path, $node, $matched_node);
        }
        if (!$node->has_child_nodes()) {
            return;
        }
        /* override node value */
        if ($this->is_text_node($node)) {
            /* skip the case when the matched node has children, otherwise they get overridden */
            if (!$matched_node->has_child_nodes() || $this->is_text_node($matched_node)) {
                $matched_node->node_value = $node->child_nodes->item(0)->node_value;
            }
        } else {
            /* recursive merge for all child nodes */
            foreach ($node->child_nodes as $child_node) {
                if ($child_node instanceof \Dom_Element) {
                    $this->merge_node($child_node, $path);
                }
            }
        }
    }
    /**
     * Replace node value.
     *
     * @param string      $parentPath
     *
     * @return void
     */
    protected function replace_node_value($parent_path, \Dom_Element $node, \Dom_Element $matched_node)
    {
        $parent_matched_node = $this->get_matched_node($parent_path);
        $new_node = $this->dom->import_node($node, true);
        $parent_matched_node->replace_child($new_node, $matched_node);
    }
    /**
     * Check if the node content is text
     *
     * @param \DOMElement $node
     */
    protected function is_text_node($node): bool
    {
        return $node->child_nodes->length === 1 && $node->child_nodes->item(0) instanceof \Dom_Text;
    }
    /**
     * Merges attributes of the merge node to the base node
     *
     * @param \DOMElement $baseNode
     * @param \DOMNode    $mergeNode
     * @return void
     */
    protected function merge_attributes($base_node, $merge_node)
    {
        foreach ($merge_node->attributes as $attribute) {
            // Do not overwrite filename of base node
            if ($attribute->name === 'filename') {
                $base_node->set_attribute($this->get_attribute_name($attribute), $base_node->get_attribute('filename') . ',' . $attribute->value);
                continue;
            }
            $base_node->set_attribute($this->get_attribute_name($attribute), $attribute->value);
        }
    }
    /**
     * Identify node path based on parent path and node attributes
     */
    protected function get_node_path_by_parent(\Dom_Element $node, string $parent_path): string
    {
        $prefix = $this->root_namespace === null ? '' : self::ROOT_NAMESPACE_PREFIX . ':';
        $path = $parent_path . '/' . $prefix . $node->tag_name;
        $id_attribute = $this->node_merging_config->get_id_attribute($path);
        if ($id_attribute) {
            foreach (explode('|', $id_attribute) as $id_attribute_value) {
                if ($node->has_attribute($id_attribute_value)) {
                    $path .= "[@{$id_attribute_value}='" . $node->get_attribute($id_attribute_value) . "']";
                    break;
                }
            }
        }
        return $path;
    }
    /**
     * Getter for node by path
     * An exception is possible if original document contains multiple nodes for identifier
     *
     * @param string $nodePath
     * @throws \Exception
     * @return \DOMElement|null
     */
    protected function get_matched_node($node_path)
    {
        $x_path = new \Domx_Path($this->dom);
        if ($this->root_namespace) {
            $x_path->register_namespace(self::ROOT_NAMESPACE_PREFIX, $this->root_namespace);
        }
        $matched_nodes = $x_path->query($node_path);
        $node = null;
        if ($matched_nodes->length > 1) {
            throw new \Exception("More than one node matching the query: {$node_path}");
        }
        if ($matched_nodes->length === 1) {
            return $matched_nodes->item(0);
        }
        return $node;
    }
    /**
     * Validate dom document
     *
     * @param string       $errorFormat
     * @return array of errors
     * @throws \Exception
     */
    public static function validate_dom_document(\Dom_Document $dom, string $schema_file_name, $error_format = self::ERROR_FORMAT_DEFAULT)
    {
        libxml_use_internal_errors(true);
        try {
            $result = $dom->schema_validate($schema_file_name);
            $errors = [];
            if (!$result) {
                $validation_errors = libxml_get_errors();
                if (count($validation_errors)) {
                    foreach ($validation_errors as $error) {
                        $errors[] = self::render_error_message($error, $error_format);
                    }
                } else {
                    $errors[] = 'Unknown validation error';
                }
            }
        } catch (\Exception $exception) {
            libxml_use_internal_errors(false);
            throw new \Exception(sprintf('Failed to validate xml using schema: %s. Exception: %s', $schema_file_name, $exception->get_message()));
        }
        libxml_use_internal_errors(false);
        return $errors;
    }
    /**
     * Render error message string by replacing placeholders '%field%' with properties of \LibXMLError
     *
     * @param string       $format
     * @return string
     * @throws \InvalidArgumentException
     */
    private static function render_error_message(\Lib_Xml_Error $error_info, $format)
    {
        $result = $format;
        foreach ($error_info as $field => $value) {
            $placeholder = '%' . $field . '%';
            $value = trim((string) $value);
            $result = str_replace($placeholder, $value, $result);
        }
        if (str_contains($result, '%')) {
            throw new \InvalidArgumentException("Error format '{$format}' contains unsupported placeholders.");
        }
        return $result;
    }
    /**
     * DOM document getter
     *
     * @return \DOMDocument
     */
    public function get_dom()
    {
        return $this->dom;
    }
    /**
     * Create DOM document based on $xml parameter
     *
     * @param string $xml
     * @param string $filename
     * @throws \Magento\FunctionalTestingFramework\Config\Dom\ValidationException
     */
    protected function init_dom($xml, $filename = null): \Dom_Document
    {
        $dom = new \Dom_Document();
        try {
            $dom_success = $dom->load_xml($xml);
            if (!$dom_success) {
                throw new \Exception();
            }
        } catch (\Exception) {
            throw new Validation_Exception("XML Parse Error: {$filename}\n");
        }
        if ($this->schema_file) {
            $errors = self::validate_dom_document($dom, $this->schema_file, $this->error_format);
            if (count($errors)) {
                throw new \Magento\Functional_Testing_Framework\Config\Dom\Validation_Exception(implode("\n", $errors));
            }
        }
        return $dom;
    }
    /**
     * Validate self contents towards to specified schema
     *
     * @param string $schemaFileName Absolute path to schema file.
     * @param array  $errors
     */
    public function validate($schema_file_name, &$errors = []): bool
    {
        $errors = self::validate_dom_document($this->dom, $schema_file_name, $this->error_format);
        return !count($errors);
    }
    /**
     * Set schema file
     *
     * @param string $schemaFile
     * @return $this
     */
    public function set_schema_file($schema_file): static
    {
        $this->schema_file = $schema_file;
        return $this;
    }
    /**
     * Returns the attribute name with prefix, if there is one
     *
     * @param \DOMAttr $attribute
     * @return string
     */
    private function get_attribute_name($attribute)
    {
        if ($attribute->prefix !== null && !empty($attribute->prefix)) {
            return $attribute->prefix . ':' . $attribute->name;
        }
        return $attribute->name;
    }
}