<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Composer;

use Composer\IO\Buffer_Io;
/**
 *  Abstract Composer Handler
 */
abstract class Abstract_Composer
{
    public const TEST_MODULE_PACKAGE_TYPE = 'magento2-functional-test-module';
    public const MAGENTO_MODULE_PACKAGE_TYPE = 'magento2-module';
    public const MODULE_NAME_IN_SUGGEST_REGEX_INDEX = 'module_name';
    public const MODULE_NAME_IN_SUGGEST_REGEX = '/type:\s*' . self::MAGENTO_MODULE_PACKAGE_TYPE . '\s*,\s*name:\s*(?<' . self::MODULE_NAME_IN_SUGGEST_REGEX_INDEX . '>[^,\s]+_[^,\s]+)/';
    /**#@+
     * Composer package array keys
     */
    public const PACKAGE_NAME = 'name';
    public const PACKAGE_TYPE = 'type';
    public const PACKAGE_VERSION = 'version';
    public const PACKAGE_DESCRIPTION = 'description';
    public const PACKAGE_INSTALLEDPATH = 'installedPath';
    public const PACKAGE_REQUIRES = 'requires';
    public const PACKAGE_DEVREQUIRES = 'devRequires';
    public const PACKAGE_SUGGESTS = 'suggests';
    public const PACKAGE_SUGGESTED_MAGENTO_MODULES = 'suggestedMagentoModules';
    /**#@-*/
    /**
     * @var \Composer\Composer
     */
    protected $composer;
    /**
     * @param string $composerFile
     */
    public function __construct($composer_file)
    {
        $this->composer = \Composer\Factory::create(new Buffer_Io(), $composer_file);
    }
    /**
     * Get composer
     *
     * @return \Composer\Composer
     */
    protected function get_composer()
    {
        return $this->composer;
    }
    /**
     * Parse input array and return all suggested magento module names, i.e. an example "suggest" in composer.json
     *
     * "suggest": {
     *   "magento/module-backend": "type: magento2-module, name: Magento_Backend, version: ~100.0.0",
     *   "magento/module-store": "type: magento2-module, name: Magento_Store, version: ~100.0.0"
     * }
     *
     * @param array $suggests
     * @return array
     */
    protected function parse_suggests_for_magento_module_names($suggests)
    {
        $magento_module_names = [];
        foreach ($suggests as $suggest) {
            // Expecting pattern - type: magento2-module, name: Magento_Store, version: ~100.0.0
            preg_match(self::MODULE_NAME_IN_SUGGEST_REGEX, (string) $suggest, $match);
            if (isset($match[self::MODULE_NAME_IN_SUGGEST_REGEX_INDEX])) {
                $magento_module_names[] = $match[self::MODULE_NAME_IN_SUGGEST_REGEX_INDEX];
            }
        }
        return array_unique($magento_module_names);
    }
}