<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Upgrade;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Finder\Finder;
/**
 * Class RemoveModuleFileInSuiteFiles
 * @package Magento\FunctionalTestingFramework\Upgrade
 */
class Remove_Module_File_In_Suite_Files implements Upgrade_Interface
{
    /**
     * OutputInterface
     */
    private ?\Symfony\Component\Console\Output\Output_Interface $output = null;
    /**
     * Console output style
     */
    private ?\Symfony\Component\Console\Style\Symfony_Style $io_style = null;
    /**
     * Indicate if notice is print
     */
    private bool $print_notice = false;
    /**
     * Number of test being updated
     */
    private int $tests_updated = 0;
    /**
     * Indicate if a match and replace has happened
     */
    private bool $replaced = false;
    /**
     * Scan all suite xml files, remove <module file="".../> node, and print update message
     *
     * @throws TestFrameworkException
     */
    public function execute(Input_Interface $input, Output_Interface $output): string
    {
        $script_util = new Script_Util();
        $this->set_output_style($input, $output);
        $this->output = $output;
        $test_paths[] = $input->get_argument('path');
        if (empty($test_paths[0])) {
            $test_paths = $script_util->get_all_module_paths();
        }
        // Get module suite xml files
        $xml_files = $script_util->get_module_xml_files_by_scope($test_paths, 'Suite');
        $this->process_xml_files($xml_files);
        return "Removed module file reference in {$this->tests_updated} suite file(s).";
    }
    /**
     * Process on list of xml files
     *
     * @param Finder $xmlFiles
     */
    private function process_xml_files($xml_files): void
    {
        foreach ($xml_files as $file) {
            $contents = $file->get_contents();
            $file_path = $file->get_real_path();
            $this->replaced = false;
            $contents = $this->remove_module_file_attribute_in_suite($contents, $file_path);
            if ($this->replaced) {
                file_put_contents($file_path, $contents);
                $this->tests_updated++;
            }
        }
    }
    /**
     * Remove module file attribute in Suite xml file
     *
     * @param string $contents
     * @param string $file
     * @return string|string[]|null
     */
    private function remove_module_file_attribute_in_suite($contents, $file): ?string
    {
        $pattern = '/<module[^\<\>]+file[\s]*=[\s]*"(?<file>[^"\<\>]*)"[^\>\<]*>/';
        return preg_replace_callback($pattern, function ($matches) use ($file): string|array {
            if (!$this->print_notice) {
                $this->io_style->note('`file` is not a valid attribute for <module> in Suite XML schema.' . PHP_EOL . 'The `file`references in the following xml files are commented out. ' . 'Consider using <test> instead.');
                $this->print_notice = true;
            }
            $this->output->writeln(PHP_EOL . '"' . trim((string) $matches[0]) . '"' . PHP_EOL . 'is commented out from file: ' . $file . PHP_EOL);
            $result = str_replace('<module', '<!--module', $matches[0]);
            $result = str_replace('>', '--> <!-- Please replace with <test name="" -->', $result);
            $this->replaced = true;
            return $result;
        }, $contents);
    }
    /**
     * Set Symfony Style for output
     */
    private function set_output_style(Input_Interface $input, Output_Interface $output): void
    {
        // For output style
        if (null === $this->io_style) {
            $this->io_style = new Symfony_Style($input, $output);
        }
    }
}