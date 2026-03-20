<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Upgrade;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Finder\Finder;
/**
 * Class UpdateTestSchemaPaths
 * @package Magento\FunctionalTestingFramework\Upgrade
 */
class Update_Test_Schema_Paths implements Upgrade_Interface
{
    /**
     * Total test updated
     */
    private int $tests_updated = 0;
    /**
     * Entity type to urn map
     */
    private array $type_to_urns = ['ActionGroup' => 'urn:magento:mftf:Test/etc/actionGroupSchema.xsd', 'Data' => 'urn:magento:mftf:DataGenerator/etc/dataProfileSchema.xsd', 'Metadata' => 'urn:magento:mftf:DataGenerator/etc/dataOperation.xsd', 'Page' => 'urn:magento:mftf:Page/etc/PageObject.xsd', 'Section' => 'urn:magento:mftf:Page/etc/SectionObject.xsd', 'Suite' => 'urn:magento:mftf:Suite/etc/suiteSchema.xsd', 'Test' => 'urn:magento:mftf:Test/etc/testSchema.xsd'];
    /**
     * Upgrades all test xml files, replacing relative schema paths to URN.
     *
     * @throws TestFrameworkException
     */
    public function execute(Input_Interface $input, Output_Interface $output): string
    {
        $script_util = new Script_Util();
        $this->tests_updated = 0;
        $test_paths[] = $input->get_argument('path');
        if (empty($test_paths[0])) {
            $test_paths = $script_util->get_all_module_paths();
        }
        // Process module xml files
        foreach ($this->type_to_urns as $type => $urn) {
            $xml_files = $script_util->get_module_xml_files_by_scope($test_paths, $type);
            $this->process_xml_files($xml_files, $urn);
        }
        return "Schema Path updated to use MFTF URNs in {$this->tests_updated} file(s).";
    }
    /**
     * Convert xml schema location from non urn based to urn based
     *
     * @param Finder $xmlFiles
     * @param string $urn
     */
    private function process_xml_files($xml_files, $urn): void
    {
        $pattern = '/xsi:noNamespaceSchemaLocation[\s]*=[\s]*"(?<urn>[^\<\>"\']*)"/';
        foreach ($xml_files as $file) {
            $file_path = $file->get_real_path();
            $contents = $file->get_contents();
            preg_match($pattern, $contents, $matches);
            if (isset($matches['urn'])) {
                if (trim($matches['urn']) !== $urn) {
                    file_put_contents($file_path, str_replace($matches['urn'], $urn, $contents));
                    $this->tests_updated++;
                }
            }
        }
    }
}