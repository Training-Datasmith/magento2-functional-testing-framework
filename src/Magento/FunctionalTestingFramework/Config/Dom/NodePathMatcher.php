<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\Dom;

/**
 * Matching of XPath expressions to path patterns
 */
class Node_Path_Matcher
{
    /**
     * Whether a subject XPath matches to a given path pattern
     *
     * @param string $pathPattern  Example: '/some/static/path' or '/some/regexp/path(/item)+'.
     * @param string $xpathSubject Example: '/some[@attr="value"]/static/ns:path'.
     */
    public function path_match($path_pattern, $xpath_subject): bool
    {
        $path_subject = $this->simplify_xpath($xpath_subject);
        $path_pattern = '#^' . $path_pattern . '$#';
        return (bool) preg_match($path_pattern, $path_subject);
    }
    /**
     * Strip off predicates and namespaces from the XPath
     *
     * @param string $xpath
     * @return string
     */
    public function simplify_xpath($xpath): ?string
    {
        $result = $xpath;
        $result = preg_replace('/\[@[^\]]+?\]/', '', $result);
        return preg_replace('/\/[^:]+?\:/', '/', (string) $result);
    }
}