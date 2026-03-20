<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\Reader;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
use Magento\Functional_Testing_Framework\Util\Iterator\File;
class Mftf_Filesystem extends \Magento\Functional_Testing_Framework\Config\Reader\Filesystem
{
    /**
     * Method to redirect file name passing into Dom class
     *
     * @param File $fileList
     * @return array
     * @throws \Exception
     */
    public function read_files($file_list)
    {
        $exception_collector = new Exception_Collector();
        /** @var \Magento\FunctionalTestingFramework\Test\Config\Dom $configMerger */
        $config_merger = null;
        $debug_level = Mftf_Application_Config::get_config()->get_debug_level();
        foreach ($file_list as $key => $content) {
            //check if file is empty and continue to next if it is
            if (!parent::verify_file_empty($content, $file_list->get_filename())) {
                continue;
            }
            try {
                if (!$config_merger) {
                    $config_merger = $this->create_config_merger($this->dom_document_class, $content, $file_list->get_filename(), $exception_collector);
                } else {
                    $config_merger->merge($content, $file_list->get_filename(), $exception_collector);
                }
                // run per file validation with generate:tests -d
                if (strcasecmp($debug_level, Mftf_Application_Config::LEVEL_DEVELOPER) === 0) {
                    $this->validate_schema($config_merger, $file_list->get_filename());
                }
            } catch (\Magento\Functional_Testing_Framework\Config\Dom\Validation_Exception $e) {
                throw new \Exception('Invalid XML in file ' . $key . ":\n" . $e->get_message());
            }
        }
        $exception_collector->throw_exception();
        //run validation on merged file with generate:tests
        if (strcasecmp($debug_level, Mftf_Application_Config::LEVEL_DEFAULT) === 0) {
            $this->validate_schema($config_merger);
        }
        if ($config_merger) {
            return $this->converter->convert($config_merger->get_dom());
        }
        return [];
    }
    /**
     * Return newly created instance of a config merger
     *
     * @param string             $mergerClass
     * @param string             $initialContents
     * @param string             $filename
     * @param ExceptionCollector $exceptionCollector
     * @return \Magento\FunctionalTestingFramework\Config\Dom
     * @throws \UnexpectedValueException
     */
    protected function create_config_merger($merger_class, $initial_contents, $filename = null, $exception_collector = null)
    {
        $result = new $merger_class($initial_contents, $filename, $exception_collector, $this->id_attributes, null, $this->per_file_schema);
        if (!$result instanceof \Magento\Functional_Testing_Framework\Config\Dom) {
            throw new \UnexpectedValueException("Instance of the DOM config merger is expected, got {$merger_class} instead.");
        }
        return $result;
    }
}