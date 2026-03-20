<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\Dom;

/**
 * Configuration of nodes that represent numeric or associative arrays
 */
class Array_Node_Config
{
    /**
     * Flat array of expanded patterns for matching xpath
     *
     * @var array
     */
    private $flat_assoc_array = [];
    /**
     * ArrayNodeConfig constructor.
     */
    public function __construct(
        /**
         * Matching of XPath expressions to path patterns.
         */
        private readonly Node_Path_Matcher $node_path_matcher,
        /**
         * Format: array('/associative/array/path' => '<array_key_attribute>', ...)
         */
        private readonly array $assoc_arrays,
        /**
         * Format: array('/numeric/array/path', ...)
         */
        private readonly array $numeric_arrays = []
    )
    {
        $this->flat_assoc_array = $this->flatten_to_assoc_key_attributes($this->assoc_arrays);
    }
    /**
     * Whether a node is a numeric array or not
     *
     * @param string $nodeXpath
     */
    public function is_numeric_array($node_xpath): bool
    {
        foreach ($this->numeric_arrays as $path_pattern) {
            if ($this->node_path_matcher->path_match($path_pattern, $node_xpath)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Retrieve name of array key attribute, if a node is an associative array
     *
     * @param string $nodeXpath
     * @return string|null
     */
    public function get_assoc_array_key_attribute($node_xpath)
    {
        if (array_key_exists($node_xpath, $this->flat_assoc_array)) {
            return $this->flat_assoc_array[$node_xpath];
        }
        foreach ($this->assoc_arrays as $path_pattern => $key_attribute) {
            if ($this->node_path_matcher->path_match($path_pattern, $node_xpath)) {
                return $key_attribute;
            }
        }
        return null;
    }
    /**
     * Function which takes a patterned list of xpath matchers and flattens to a single level array for
     * performance improvement
     *
     * @param array $assocArrayAttributes
     */
    private function flatten_to_assoc_key_attributes($assoc_array_attributes): array
    {
        $final_patterns = [];
        foreach ($assoc_array_attributes as $pattern => $key) {
            $vars = explode('/', ltrim((string) $pattern, '/'));
            $string_patterns = [''];
            foreach ($vars as $var) {
                if (strstr($var, '|')) {
                    $rep_open = str_replace('(', '', $var);
                    $rep_closed = str_replace(')', '', $rep_open);
                    $nested_patterns = explode('|', $rep_closed);
                    $string_patterns = $this->merge_strings($string_patterns, $nested_patterns);
                    continue;
                }
                // append this path to all of the paths that currently exist
                array_walk($string_patterns, function (string &$value, $key) use ($var): void {
                    $value .= '/' . $var;
                });
            }
            $final_patterns = array_merge($final_patterns, array_fill_keys($string_patterns, $key));
        }
        return $final_patterns;
    }
    /**
     * Takes 2 arrays and appends all string in the second array to each entry in the first.
     *
     * @param string[] $parentStrings
     * @param string[] $childStrings
     */
    private function merge_strings($parent_strings, array $child_strings): array
    {
        $result = [];
        foreach ($parent_strings as $p_string) {
            foreach ($child_strings as $c_string) {
                $result[] = $p_string . '/' . $c_string;
            }
        }
        return $result;
    }
}