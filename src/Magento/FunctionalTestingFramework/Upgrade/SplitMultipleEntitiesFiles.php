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
use Symfony\Component\Finder\Finder;
/**
 * Class SplitMultipleEntitiesFiles
 * @package Magento\FunctionalTestingFramework\Upgrade
 */
class Split_Multiple_Entities_Files implements Upgrade_Interface
{
    public const XML_VERSION = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    public const XML_COPYRIGHT = '<!--' . PHP_EOL . ' /**' . PHP_EOL . '  * Copyright © Magento, Inc. All rights reserved.' . PHP_EOL . '  * See COPYING.txt for license details.' . PHP_EOL . '  */' . PHP_EOL . '-->' . PHP_EOL;
    public const XML_NAMESPACE = 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . PHP_EOL;
    public const XML_SCHEMA_LOCATION = "\t" . 'xsi:noNamespaceSchemaLocation="urn:magento:mftf:';
    public const FILENAME_BASE = 'base';
    public const FILENAME_SUFFIX = 'type';
    /**
     * OutputInterface
     */
    private ?\Symfony\Component\Console\Output\Output_Interface $output = null;
    /**
     * Total test updated
     */
    private int $tests_updated = 0;
    /**
     * Entity categories for the upgrade script
     */
    private array $entity_categories = ['Suite' => 'Suite/etc/suiteSchema.xsd', 'Test' => 'Test/etc/testSchema.xsd', 'ActionGroup' => 'Test/etc/actionGroupSchema.xsd', 'Page' => 'Page/etc/PageObject.xsd', 'Section' => 'Page/etc/SectionObject.xsd'];
    /**
     * Scan all xml files and split xml files that contains more than one entities
     * for Test, Action Group, Page, Section, Suite types.
     *
     * @throws TestFrameworkException
     */
    public function execute(Input_Interface $input, Output_Interface $output): string
    {
        $script_util = new Script_Util();
        $this->output = $output;
        $this->tests_updated = 0;
        $test_paths[] = $input->get_argument('path');
        if (empty($test_paths[0])) {
            $test_paths = $script_util->get_all_module_paths();
        }
        // Process module xml files
        foreach ($this->entity_categories as $type => $urn) {
            $xml_files = $script_util->get_module_xml_files_by_scope($test_paths, $type);
            $this->process_xml_files($xml_files, $type, $urn);
        }
        return "Split multiple entities in {$this->tests_updated} file(s).";
    }
    /**
     * Split on list of xml files
     *
     * @param Finder $xmlFiles
     * @param string $type
     * @param string $urn
     */
    private function process_xml_files($xml_files, $type, $urn): void
    {
        foreach ($xml_files as $file) {
            $contents = $file->get_contents();
            $dom_document = new \Dom_Document();
            $dom_document->load_xml($contents);
            $entities = $dom_document->get_elements_by_tag_name(lcfirst($type));
            if ($entities->length > 1) {
                $filename = $file->get_real_path();
                if ($this->output->is_verbose()) {
                    $this->output->writeln('Processing file:' . $filename);
                }
                foreach ($entities as $entity) {
                    /** @var \DOMElement $entity */
                    $entity_name = $entity->get_attribute('name');
                    $entity_content = $entity->owner_document->save_xml($entity);
                    $dir = dirname($file);
                    $dir .= DIRECTORY_SEPARATOR . ucfirst(basename($file, '.xml'));
                    $split_file_name = $this->format_name($entity_name, $type);
                    $this->file_put_contents($dir . DIRECTORY_SEPARATOR . $split_file_name . '.xml', $type, $urn, $entity_content);
                    if ($this->output->is_verbose()) {
                        $this->output->writeln('Created file:' . $dir . DIRECTORY_SEPARATOR . $split_file_name . '.xml');
                    }
                    $this->tests_updated++;
                }
                unlink($file);
                if ($this->output->is_verbose()) {
                    $this->output->writeln('Unlinked file:' . $filename . PHP_EOL);
                }
            }
        }
    }
    /**
     * Create file with contents and create dir if needed
     *
     * @param string $type
     */
    private function file_put_contents(string $full_path, $type, string $urn, string $contents): void
    {
        $dir = dirname($full_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        // Make sure not overwriting an existing file
        $full_path = $this->get_non_existing_file_full_path($full_path, $type);
        $full_contents = self::XML_VERSION . self::XML_COPYRIGHT . '<' . lcfirst($type) . 's ' . self::XML_NAMESPACE . self::XML_SCHEMA_LOCATION . $urn . '">' . PHP_EOL . '    ' . $contents . PHP_EOL . '</' . lcfirst($type) . 's>' . PHP_EOL;
        file_put_contents($full_path, $full_contents);
    }
    /**
     * Format name to include type if it's Page, Section or Action Group
     *
     * @param string $name
     * @param string $type
     */
    private function format_name($name, $type): string
    {
        $name = ucfirst($name);
        $type = ucfirst($type);
        if ($type !== 'Section' && $type !== 'Page' && $type !== 'ActionGroup') {
            return $name;
        }
        $parts = $this->get_file_name_parts($name, $type);
        if (empty($parts[self::FILENAME_SUFFIX])) {
            $name .= $type;
        }
        return $name;
    }
    /**
     * Vary the input to return a non-existing file name
     *
     * @param string $type
     */
    private function get_non_existing_file_full_path(string $full_path, $type): string
    {
        $type = ucfirst($type);
        $dir = dirname($full_path);
        $filename = basename($full_path, '.xml');
        $i = 1;
        $parts = [];
        while (file_exists($full_path)) {
            if (empty($parts)) {
                $parts = $this->get_file_name_parts($filename, $type);
            }
            $basename = $parts[self::FILENAME_BASE] . strval(++$i);
            $full_path = $dir . DIRECTORY_SEPARATOR . $basename . $parts[self::FILENAME_SUFFIX] . '.xml';
        }
        return $full_path;
    }
    /**
     * Split filename into two parts and return it in an associate array with keys FILENAME_BASE and FILENAME_SUFFIX
     */
    private function get_file_name_parts(string $filename, string $type): array
    {
        $type = ucfirst($type);
        $file_name_parts = [];
        if (str_ends_with($filename, $type)) {
            $file_name_parts[self::FILENAME_BASE] = substr($filename, 0, strlen($filename) - strlen($type));
            $file_name_parts[self::FILENAME_SUFFIX] = $type;
        } else {
            $file_name_parts[self::FILENAME_BASE] = $filename;
            $file_name_parts[self::FILENAME_SUFFIX] = '';
        }
        return $file_name_parts;
    }
}