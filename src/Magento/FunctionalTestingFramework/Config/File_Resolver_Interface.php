<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Interface FileResolverInterface
 */
interface File_Resolver_Interface
{
    /**
     * Retrieve the list of configuration files with given name that relate to specified scope
     *
     * @param string $filename
     * @param string $scope
     * @return array|\Magento\FunctionalTestingFramework\Util\Iterator\File
     */
    public function get($filename, $scope);
}