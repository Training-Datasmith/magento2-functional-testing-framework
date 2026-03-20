<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager;

use Magento\Functional_Testing_Framework\Object_Manager\Config\Config as ObjectManagerConfig;
/**
 * Class Config
 * Filesystem configuration loader. Loads configuration from XML files, split by scopes
 *
 * @internal
 */
class Config extends Object_Manager_Config
{
    /**
     * Class reflections.
     *
     * @var \ReflectionClass[]
     */
    protected $non_shared_ref_classes = [];
    /**
     * Check whether type is shared
     *
     * @param string $type
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function is_shared($type): bool
    {
        if (isset($this->non_shared[$type])) {
            return false;
        }
        if (isset($this->virtual_types[$type])) {
            return true;
        }
        if (!isset($this->non_shared_ref_classes[$type])) {
            $this->non_shared_ref_classes[$type] = new \ReflectionClass($type);
        }
        foreach ($this->non_shared as $none_shared => $flag) {
            if ($this->non_shared_ref_classes[$type]->is_subclass_of($none_shared)) {
                return false;
            }
        }
        return true;
    }
}