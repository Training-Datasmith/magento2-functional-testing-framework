<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util\Validation;

use Magento\FunctionalTestingFramework\Exceptions\Collector\ExceptionCollector;

/**
 * Class SingleNodePerDocumentValidationUtil
 * @package Magento\FunctionalTestingFramework\Util\Validation
 */
class SingleNodePerFileValidationUtil
{
    /**
     * SingleNodePerDocumentValidationUtil constructor
     *
     * @param ExceptionCollector $exceptionCollector
     */
    public function __construct(
        /**
         * ExceptionColletor used to catch errors
         */
        private $exceptionCollector
    ) {
    }

    /**
     * Validate single node per dom document for a given tag name
     *
     * @param \DOMDocument $dom
     * @param string       $tag
     * @param string       $filename
     */
    public function validateSingleNodeForTag($dom, $tag, $filename = ''): void
    {
        $tagNodes = $dom->getElementsByTagName($tag);
        $count = $tagNodes->length;
        if ($count === 1) {
            return;
        }

        $errorMsg = "Single <{$tag}> node per xml file. {$count} found in file: {$filename}\n";
        $this->exceptionCollector->addError($filename, $errorMsg);
    }
}
