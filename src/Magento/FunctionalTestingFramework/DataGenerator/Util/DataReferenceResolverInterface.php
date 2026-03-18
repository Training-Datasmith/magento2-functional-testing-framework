<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataGenerator\Util;

interface DataReferenceResolverInterface
{
    const REFERENCE_REGEX_PATTERN = "/(?<reference>{{[\w]+\..+}})/";

    /**
     * @return mixed
     */
    public function getDataReference(string $data, string $originalDataEntity);

    /**
     * @return mixed
     */
    public function getDataUniqueness(string $data, string $originalDataEntity);
}
