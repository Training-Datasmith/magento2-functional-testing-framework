<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Upgrade;

/**
 * Contains a list of Upgrade Scripts
 * @api
 */
interface Upgrade_Script_List_Interface
{
    /**
     * Gets list of upgrade script instances
     *
     * @return \Magento\FunctionalTestingFramework\Upgrade\UpgradeInterface[]
     */
    public function get_upgrade_scripts();
}