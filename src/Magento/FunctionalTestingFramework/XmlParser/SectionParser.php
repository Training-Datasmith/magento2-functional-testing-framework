<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\XmlParser;

use Magento\FunctionalTestingFramework\Config\DataInterface;
use Magento\FunctionalTestingFramework\ObjectManagerInterface;

/**
 * Generic Xml Parser.
 */
class SectionParser implements ParserInterface
{
    /**
     * SectionParser constructor.
     */
    public function __construct(
        /**
         * Object manager.
         *
         * @var \Magento\FunctionalTestingFramework\ObjectManager
         */
        protected \Magento\FunctionalTestingFramework\ObjectManagerInterface $objectManager,
        /**
         * Configuration data.
         */
        protected \Magento\FunctionalTestingFramework\Config\DataInterface $configData
    )
    {
    }

    /**
     * Get parsed xml data.
     *
     * @param string $type
     * @return array
     */
    public function getData($type)
    {
        return $this->configData->get($type);
    }
}
