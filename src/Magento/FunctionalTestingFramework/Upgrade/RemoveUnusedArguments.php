<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Upgrade;

use Dom_Element;
use Magento\Functional_Testing_Framework\Static_Check\Action_Group_Standards_Check;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Filesystem\Filesystem;
/**
 * Class RenameMetadataFiles
 * @package Magento\FunctionalTestingFramework\Upgrade
 */
class Remove_Unused_Arguments implements Upgrade_Interface
{
    public const ARGUMENTS_BLOCK_REGEX_PATTERN = "/\\s*<arguments.*\\/arguments>/s";
    /**
     * Updates all actionGroup xml files
     */
    public function execute(Input_Interface $input, Output_Interface $output): string
    {
        $script_util = new Script_Util();
        $test_paths[] = $input->get_argument('path');
        if (empty($test_paths[0])) {
            $test_paths = $script_util->get_all_module_paths();
        }
        $xml_files = $script_util->get_module_xml_files_by_scope($test_paths, 'ActionGroup');
        $action_groups_updated = 0;
        $file_system = new Filesystem();
        foreach ($xml_files as $file) {
            $contents = $file->get_contents();
            $arguments_check = new Action_Group_Standards_Check();
            /** @var DOMElement $actionGroup */
            $action_group = $arguments_check->get_action_group_dom_element($contents);
            $all_arguments = $arguments_check->extract_action_group_arguments($action_group);
            $unused_arguments = $arguments_check->find_unused_arguments($all_arguments, $contents);
            if (empty($unused_arguments)) {
                continue;
            }
            //Remove <arguments> block if all arguments are unused
            if (empty(array_diff($all_arguments, $unused_arguments))) {
                $contents = preg_replace(self::ARGUMENTS_BLOCK_REGEX_PATTERN, '', $contents);
            } else {
                foreach ($unused_arguments as $argument) {
                    $argument_regex_pattern = "/\\s*<argument.*name\\s*=\\s*\"" . $argument . "\".*\\/>/";
                    $contents = preg_replace($argument_regex_pattern, '', $contents);
                }
            }
            $file_system->dump_file($file->get_real_path(), $contents);
            $action_groups_updated++;
        }
        return "Removed unused action group arguments from {$action_groups_updated} file(s).";
    }
}