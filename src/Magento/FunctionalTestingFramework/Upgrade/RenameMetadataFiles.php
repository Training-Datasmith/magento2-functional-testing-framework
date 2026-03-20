<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Upgrade;

use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Finder\Finder;
/**
 * Class RenameMetadataFiles
 * @package Magento\FunctionalTestingFramework\Upgrade
 */
class Rename_Metadata_Files implements Upgrade_Interface
{
    /**
     * Upgrades all test xml files
     */
    public function execute(Input_Interface $input, Output_Interface $output): string
    {
        $script_util = new Script_Util();
        $test_paths[] = $input->get_argument('path');
        if (empty($test_paths[0])) {
            $test_paths = $script_util->get_all_module_paths();
        }
        foreach ($test_paths as $tests_path) {
            $finder = new Finder();
            $finder->files()->in($tests_path)->name('*-meta.xml');
            foreach ($finder->files() as $file) {
                $old_file_name = $file->get_file_name();
                $new_file_name = $this->convert_file_name($old_file_name);
                $old_path = $file->get_pathname();
                $new_path = $file->get_path() . '/' . $new_file_name;
                print 'Renaming ' . $old_path . ' => ' . $new_path . "\n";
                rename($old_path, $new_path);
            }
        }
        return 'Finished renaming -meta.xml files.';
    }
    /**
     * Convert filenames like:
     *     user_role-meta.xml => UserRoleMeta.xml
     *     store-meta.xml => StoreMeta.xml
     */
    private function convert_file_name(string $old_file_name): string
    {
        $strip_ending = preg_replace('/-meta.xml/', '', $old_file_name);
        $hyphen_to_underscore = str_replace('-', '_', $strip_ending);
        $parts = explode('_', $hyphen_to_underscore);
        $uc_parts = [];
        foreach ($parts as $part) {
            $uc_parts[] = ucfirst($part);
        }
        $recombine = join('', $uc_parts);
        return $recombine . 'Meta.xml';
    }
}