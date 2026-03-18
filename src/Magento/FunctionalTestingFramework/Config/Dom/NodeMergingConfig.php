<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config\Dom;

/**
 * Configuration of identifier attributes to be taken into account during merging
 */
class NodeMergingConfig
{
    /**
     * NodeMergingConfig constructor.
     */
    public function __construct(
        /**
         * Matching of XPath expressions to path patterns.
         */
        private readonly NodePathMatcher $nodePathMatcher,
        /**
         * Format: array('/node/path' => '<node_id_attribute>', ...)
         */
        private readonly array $idAttributes
    ) {
    }

    /**
     * Retrieve name of an identifier attribute for a node
     *
     * @param string $nodeXpath
     * @return string|null
     */
    public function getIdAttribute($nodeXpath)
    {
        foreach ($this->idAttributes as $pathPattern => $idAttribute) {
            if ($this->nodePathMatcher->pathMatch($pathPattern, $nodeXpath)) {
                return $idAttribute;
            }
        }
        return null;
    }

    /**
     * Getter to return nodePathMatcher for convenience
     *
     * @return NodePathMatcher
     */
    public function getNodePathMatcher()
    {
        return $this->nodePathMatcher;
    }
}
