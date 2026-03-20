<?php

// @codingStandardsIgnoreFile
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
class Generate_Dev_Urn_Command extends Command
{
    private const SUCCESS_EXIT_CODE = 0;
    /**
     * Argument for the path to IDE config file
     */
    public const IDE_FILE_PATH_ARGUMENT = 'path';
    public const PROJECT_PATH_IDENTIFIER = '$PROJECT_DIR$';
    public const MFTF_SRC_PATH = 'src/Magento/FunctionalTestingFramework/';
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('generate:urn-catalog')->set_description('Generates the catalog of URNs to *.xsd mappings for the IDE to highlight xml.')->add_argument(self::IDE_FILE_PATH_ARGUMENT, Input_Argument::REQUIRED, 'Path to file to output the catalog. For PhpStorm use .idea/misc.xml')->add_option('force', 'f', Input_Option::VALUE_NONE, 'forces creation of misc.xml file if not found in the path given.');
    }
    /**
     * Executes the current command.
     *
     * @throws \Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $misc_xml_file_path = $input->get_argument(self::IDE_FILE_PATH_ARGUMENT);
        $misc_xml_file = realpath($misc_xml_file_path);
        $force = (bool) $input->get_option('force');
        if ($misc_xml_file === false) {
            if ($force === true) {
                // create file and refresh realpath
                $xml = '<project version="4"/>';
                file_put_contents($misc_xml_file_path, $xml);
                $misc_xml_file = realpath($misc_xml_file_path);
            } else {
                $exception_message = "misc.xml not found in given path '{$misc_xml_file_path}'";
                Logging_Util::get_instance()->get_logger(Generate_Dev_Urn_Command::class)->error($exception_message);
                throw new Test_Framework_Exception($exception_message);
            }
        }
        $dom = new \Dom_Document('1.0');
        $dom->preserve_white_space = false;
        $dom->format_output = true;
        $dom->load_xml(file_get_contents($misc_xml_file));
        //Locate ProjectResources node, create one if none are found.
        $node_for_work = null;
        foreach ($dom->get_elements_by_tag_name('component') as $child) {
            if ($child->get_attribute('name') === 'ProjectResources') {
                $node_for_work = $child;
            }
        }
        if ($node_for_work === null) {
            $project = $dom->get_elements_by_tag_name('project')->item(0);
            $node_for_work = $dom->create_element('component');
            $node_for_work->set_attribute('name', 'ProjectResources');
            $project->append_child($node_for_work);
        }
        //Extract url=>location mappings that already exist, add MFTF URNs and reappend
        $resources = [];
        $resource_nodes = $node_for_work->get_elements_by_tag_name('resource');
        $resource_count = $resource_nodes->length;
        for ($i = 0; $i < $resource_count; $i++) {
            $child = $resource_nodes[0];
            $resources[$child->get_attribute('url')] = $child->get_attribute('location');
            $child->parent_node->remove_child($child);
        }
        $resources = array_merge($resources, $this->generate_resources_array());
        foreach ($resources as $url => $location) {
            $resource_node = $dom->create_element('resource');
            $resource_node->set_attribute('url', $url);
            $resource_node->set_attribute('location', $location);
            $node_for_work->append_child($resource_node);
        }
        //Save output
        $dom->save($misc_xml_file);
        $output->writeln("MFTF URN mapping successfully added to {$misc_xml_file}.");
        return self::SUCCESS_EXIT_CODE;
    }
    /**
     * Generates urn => location array for all MFTF schema.
     */
    private function generate_resources_array(): array
    {
        return ['urn:magento:mftf:DataGenerator/etc/dataOperation.xsd' => $this->get_resource_path('DataGenerator/etc/dataOperation.xsd'), 'urn:magento:mftf:DataGenerator/etc/dataProfileSchema.xsd' => $this->get_resource_path('DataGenerator/etc/dataProfileSchema.xsd'), 'urn:magento:mftf:Page/etc/PageObject.xsd' => $this->get_resource_path('Page/etc/PageObject.xsd'), 'urn:magento:mftf:Page/etc/SectionObject.xsd' => $this->get_resource_path('Page/etc/SectionObject.xsd'), 'urn:magento:mftf:Test/etc/actionGroupSchema.xsd' => $this->get_resource_path('Test/etc/actionGroupSchema.xsd'), 'urn:magento:mftf:Test/etc/testSchema.xsd' => $this->get_resource_path('Test/etc/testSchema.xsd'), 'urn:magento:mftf:Suite/etc/suiteSchema.xsd' => $this->get_resource_path('Suite/etc/suiteSchema.xsd')];
    }
    /**
     * Returns path (full or PhpStorm project-based) to XSD file
     *
     * @param $relativePath
     * @return string
     * @throws TestFrameworkException
     */
    private function get_resource_path(string $relative_path)
    {
        $urn_path = realpath(File_Path_Formatter::format(FW_BP) . self::MFTF_SRC_PATH . $relative_path);
        $project_root = $this->get_project_root_path();
        if ($project_root !== null) {
            return str_replace($project_root, self::PROJECT_PATH_IDENTIFIER, $urn_path);
        }
        return $urn_path;
    }
    /**
     * Returns Project root directory absolute path
     * @TODO Find out how to detect other types of installation
     *
     * @return string|null
     */
    private function get_project_root_path(): string|false|null
    {
        $framework_root = realpath(__DIR__);
        if ($this->is_installed_by_composer($framework_root)) {
            return strstr($framework_root, '/vendor/', true);
        }
        return null;
    }
    /**
     * Determines whether MFTF was installed using Composer
     *
     * @param string $frameworkRoot
     */
    private function is_installed_by_composer($framework_root): bool
    {
        return str_contains($framework_root, '/vendor/');
    }
}