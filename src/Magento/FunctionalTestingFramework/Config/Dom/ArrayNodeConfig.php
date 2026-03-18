<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config\Dom;

/**
 * Configuration of nodes that represent numeric or associative arrays
 */
class ArrayNodeConfig
{
    /**
     * Flat array of expanded patterns for matching xpath
     *
     * @var array
     */
    private $flatAssocArray = [];

    /**
     * ArrayNodeConfig constructor.
     */
    public function __construct(
        /**
         * Matching of XPath expressions to path patterns.
         */
        private readonly NodePathMatcher $nodePathMatcher,
        /**
         * Format: array('/associative/array/path' => '<array_key_attribute>', ...)
         */
        private readonly array $assocArrays,
        /**
         * Format: array('/numeric/array/path', ...)
         */
        private readonly array $numericArrays = []
    ) {
        $this->flatAssocArray = $this->flattenToAssocKeyAttributes($this->assocArrays);
    }

    /**
     * Whether a node is a numeric array or not
     *
     * @param string $nodeXpath
     */
    public function isNumericArray($nodeXpath): bool
    {
        foreach ($this->numericArrays as $pathPattern) {
            if ($this->nodePathMatcher->pathMatch($pathPattern, $nodeXpath)) {
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
    public function getAssocArrayKeyAttribute($nodeXpath)
    {
        if (array_key_exists($nodeXpath, $this->flatAssocArray)) {
            return $this->flatAssocArray[$nodeXpath];
        }

        foreach ($this->assocArrays as $pathPattern => $keyAttribute) {
            if ($this->nodePathMatcher->pathMatch($pathPattern, $nodeXpath)) {
                return $keyAttribute;
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
    private function flattenToAssocKeyAttributes($assocArrayAttributes): array
    {
        $finalPatterns = [];
        foreach ($assocArrayAttributes as $pattern => $key) {
            $vars = explode("/", ltrim((string) $pattern, "/"));
            $stringPatterns = [""];
            foreach ($vars as $var) {
                if (strstr($var, "|")) {
                    $repOpen = str_replace("(", "", $var);
                    $repClosed = str_replace(")", "", $repOpen);
                    $nestedPatterns = explode("|", $repClosed);
                    $stringPatterns = $this->mergeStrings($stringPatterns, $nestedPatterns);
                    continue;
                }

                // append this path to all of the paths that currently exist
                array_walk($stringPatterns, function (string &$value, $key) use ($var): void {
                    $value .= "/" . $var;
                });
            }

            $finalPatterns = array_merge($finalPatterns, array_fill_keys($stringPatterns, $key));
        }

        return $finalPatterns;
    }

    /**
     * Takes 2 arrays and appends all string in the second array to each entry in the first.
     *
     * @param string[] $parentStrings
     * @param string[] $childStrings
     */
    private function mergeStrings($parentStrings, array $childStrings): array
    {
        $result = [];
        foreach ($parentStrings as $pString) {
            foreach ($childStrings as $cString) {
                $result[] = $pString . "/" . $cString;
            }
        }

        return $result;
    }
}
