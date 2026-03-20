<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Composer;

/**
 * Class ComposerPackage contains composer json information in a MFTF test package
 */
class Composer_Package extends Abstract_Composer
{
    /**
     * @var \Composer\Package\CompletePackage
     */
    private $root_package;
    /**
     * Retrieve package name from composer json
     *
     * @return string
     */
    public function get_name()
    {
        /** @var \Composer\Package\CompletePackage $package */
        $package = $this->get_root_package();
        return $package->get_pretty_name();
    }
    /**
     * Retrieve package type from composer json
     *
     * @return string
     */
    public function get_type()
    {
        /** @var \Composer\Package\CompletePackage $package */
        $package = $this->get_root_package();
        return $package->get_type();
    }
    /**
     * Retrieve package version from composer json
     *
     * @return string
     */
    public function get_version()
    {
        /** @var \Composer\Package\CompletePackage $package */
        $package = $this->get_root_package();
        return $package->get_pretty_version();
    }
    /**
     * Retrieve package description from composer json
     *
     * @return string
     */
    public function get_description()
    {
        /** @var \Composer\Package\CompletePackage $package */
        $package = $this->get_root_package();
        return $package->get_description();
    }
    /**
     * Retrieve package require from composer json
     *
     * @return array
     */
    public function get_requires()
    {
        /** @var \Composer\Package\CompletePackage $package */
        $package = $this->get_root_package();
        return $package->get_requires();
    }
    /**
     * Retrieve package dev require from composer json
     *
     * @return array
     */
    public function get_dev_requires()
    {
        /** @var \Composer\Package\CompletePackage $package */
        $package = $this->get_root_package();
        return $package->get_dev_requires();
    }
    /**
     * Retrieve package suggest from composer json
     *
     * @return array
     */
    public function get_suggests()
    {
        /** @var \Composer\Package\CompletePackage $package */
        $package = $this->get_root_package();
        return $package->get_suggests();
    }
    /**
     * Retrieve magento module names in package's suggest
     *
     * @return array
     */
    public function get_suggested_magento_modules()
    {
        return $this->parse_suggests_for_magento_module_names($this->get_suggests());
    }
    /**
     * Determines if package is a mftf test package
     */
    public function is_mftf_test_package(): bool
    {
        return $this->get_type() === self::TEST_MODULE_PACKAGE_TYPE;
    }
    /**
     * Retrieve packages require for given package name and version
     *
     * @param string $name
     * @param string $version
     * @return array
     */
    public function get_requires_for_package($name, $version)
    {
        /** @var \Composer\Package\CompletePackage $package */
        $package = $this->get_composer()->get_repository_manager()->find_package($name, $version);
        return $package->get_requires();
    }
    /**
     * Check if a package is required in composer json
     *
     * @param string $packageName
     */
    public function is_package_required_in_composer_json($package_name): bool
    {
        return in_array($package_name, array_keys($this->get_requires())) || in_array($package_name, array_keys($this->get_dev_requires()));
    }
    /**
     * Get root package
     *
     * @return \Composer\Package\RootPackageInterface
     */
    public function get_root_package()
    {
        if (!$this->root_package) {
            $this->root_package = $this->get_composer()->get_package();
        }
        return $this->root_package;
    }
}