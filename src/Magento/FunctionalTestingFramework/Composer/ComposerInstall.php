<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Composer;

use Composer\Package\Complete_Package_Interface;
/**
 * Class ComposerInstaller handles information and dependencies for composer installed packages
 */
class Composer_Install extends Abstract_Composer
{
    /**
     * @var \Composer\Package\Locker
     */
    private $locker;
    /**
     * Determines if package is a mftf test package
     *
     * @param string $packageName
     * @return boolean
     */
    public function is_mftf_test_package($package_name)
    {
        return $this->is_installed_package_of_type($package_name, self::TEST_MODULE_PACKAGE_TYPE);
    }
    /**
     * Determines if package is a magento package
     *
     * @param string $packageName
     * @return boolean
     */
    public function is_magento_package($package_name)
    {
        return $this->is_installed_package_of_type($package_name, self::MAGENTO_MODULE_PACKAGE_TYPE);
    }
    /**
     * Determines if an installed package is of a certain type
     *
     * @param string $packageName
     * @param string $packageType
     */
    public function is_installed_package_of_type($package_name, $package_type): bool
    {
        /** @var CompletePackageInterface $package */
        foreach ($this->get_locker()->get_locked_repository()->get_packages() as $package) {
            if ($package->get_name() === $package_name && $package->get_type() === $package_type) {
                return true;
            }
        }
        return false;
    }
    /**
     * Collect all installed mftf test packages from composer lock
     */
    public function get_installed_test_packages(): array
    {
        $packages = [];
        /** @var CompletePackageInterface $package */
        foreach ($this->get_locker()->get_locked_repository()->get_packages() as $package) {
            if ($package->get_type() === self::TEST_MODULE_PACKAGE_TYPE) {
                $packages[$package->get_name()] = [self::PACKAGE_NAME => $package->get_name(), self::PACKAGE_TYPE => $package->get_type(), self::PACKAGE_VERSION => $package->get_pretty_version(), self::PACKAGE_DESCRIPTION => $package->get_description(), self::PACKAGE_SUGGESTS => $package->get_suggests(), self::PACKAGE_REQUIRES => $package->get_requires(), self::PACKAGE_DEVREQUIRES => $package->get_dev_requires(), self::PACKAGE_SUGGESTED_MAGENTO_MODULES => $this->parse_suggests_for_magento_module_names($package->get_suggests()), self::PACKAGE_INSTALLEDPATH => $this->get_composer()->get_installation_manager()->get_install_path($package)];
            }
        }
        return $packages;
    }
    /**
     * Load locker
     *
     * @return \Composer\Package\Locker
     */
    private function get_locker()
    {
        if (!$this->locker) {
            $this->locker = $this->get_composer()->get_locker();
        }
        return $this->locker;
    }
}