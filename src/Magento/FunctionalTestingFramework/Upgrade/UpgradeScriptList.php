<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Upgrade;

/**
 * Class UpgradeScriptList has a list of scripts.
 * @codingStandardsIgnoreFile
 */
class Upgrade_Script_List implements Upgrade_Script_List_Interface
{
    /**
     * Property contains all upgrade scripts.
     *
     * @var \Magento\FunctionalTestingFramework\Upgrade\UpgradeInterface[]
     */
    private readonly array $scripts;
    /**
     * Constructor
     */
    public function __construct(array $scripts = [])
    {
        $this->scripts = ['removeUnusedArguments' => new Remove_Unused_Arguments(), 'upgradeTestSchema' => new Update_Test_Schema_Paths(), 'upgradeAssertionSchema' => new Update_Assertion_Schema(), 'renameMetadataFiles' => new Rename_Metadata_Files(), 'removeModuleFileInSuiteFiles' => new Remove_Module_File_In_Suite_Files(), 'splitMultipleEntitiesFiles' => new Split_Multiple_Entities_Files()] + $scripts;
    }
    /**
     * {@inheritdoc}
     */
    public function get_upgrade_scripts()
    {
        return $this->scripts;
    }
}