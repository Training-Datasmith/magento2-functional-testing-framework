<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\Dom;

/**
 * Configuration of identifier attributes to be taken into account during merging
 */
class Node_Merging_Config
{
    /**
     * NodeMergingConfig constructor.
     */
    public function __construct(
        /**
         * Matching of XPath expressions to path patterns.
         */
        private readonly Node_Path_Matcher $node_path_matcher,
        /**
         * Format: array('/node/path' => '<node_id_attribute>', ...)
         */
        private readonly array $id_attributes
    )
    {
    }
    /**
     * Retrieve name of an identifier attribute for a node
     *
     * @param string $nodeXpath
     * @return string|null
     */
    public function get_id_attribute($node_xpath)
    {
        foreach ($this->id_attributes as $path_pattern => $id_attribute) {
            if ($this->node_path_matcher->path_match($path_pattern, $node_xpath)) {
                return $id_attribute;
            }
        }
        return null;
    }
    /**
     * Getter to return nodePathMatcher for convenience
     *
     * @return NodePathMatcher
     */
    public function get_node_path_matcher()
    {
        return $this->node_path_matcher;
    }
}