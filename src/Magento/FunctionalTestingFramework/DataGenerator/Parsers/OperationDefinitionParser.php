<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataGenerator\Parsers;

use Magento\FunctionalTestingFramework\Config\DataInterface;

/**
 * Class OperationDefinitionParser
 */
class OperationDefinitionParser
{
    /**
     * MetadataParser constructor.
     */
    public function __construct(
        /**
         * Meta Data.
         */
        private readonly DataInterface $metadata
    )
    {
    }

    /**
     * Returns an array containing all data read from operations.xml files.
     *
     * @return array
     */
    public function readOperationMetadata()
    {
        return $this->metadata->get();
    }
}
