<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Suite\Parsers;

use Magento\FunctionalTestingFramework\Config\DataInterface;

class SuiteDataParser
{
    /**
     * TestDataParser constructor.
     */
    public function __construct(
        /**
         * Suite data interface for parser.
         */
        private readonly DataInterface $suiteData
    ) {
    }

    /**
     * Returns an array of data based on *Test.xml files
     *
     * @return array
     */
    public function readSuiteData()
    {
        return $this->suiteData->get();
    }
}
