<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\Reader;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
/**
 * Filesystem configuration loader. Loads configuration from XML files, split by scopes.
 */
class Filesystem implements \Magento\Functional_Testing_Framework\Config\Reader_Interface
{
    /**
     * Path to corresponding XSD file with validation rules for merged config
     *
     * @var string
     */
    protected $schema;
    /**
     * Path to corresponding XSD file with validation rules for separate config files
     *
     * @var string
     */
    protected $per_file_schema;
    /**
     * List of id attributes for merge
     */
    protected array $id_attributes = [];
    /**
     * File path to schema file.
     *
     * @var string
     */
    protected $schema_file;
    /**
     * Constructor
     *
     * @param string                                                              $fileName
     * @param array                                                               $idAttributes
     * @param string                                                              $domDocumentClass
     * @param string                                                              $defaultScope
     */
    public function __construct(
        /**
         * File locator
         */
        protected \Magento\Functional_Testing_Framework\Config\File_Resolver_Interface $file_resolver,
        /**
         * Config converter
         */
        protected \Magento\Functional_Testing_Framework\Config\Converter_Interface $converter,
        \Magento\Functional_Testing_Framework\Config\Schema_Locator_Interface $schema_locator,
        /**
         * Config validation state object.
         */
        protected \Magento\Functional_Testing_Framework\Config\Validation_State_Interface $validation_state,
        /**
         * The name of file that stores configuration
         */
        protected $file_name,
        $id_attributes = [],
        /**
         * Class of dom configuration document used for merge
         */
        protected $dom_document_class = \Magento\Functional_Testing_Framework\Config\Dom::class,
        /**
         * Default scope.
         */
        protected $default_scope = 'global'
    )
    {
        $this->id_attributes = array_replace($this->id_attributes, $id_attributes);
        $this->schema_file = $schema_locator->get_schema();
        $this->per_file_schema = $schema_locator->get_per_file_schema() && $this->validation_state->is_validation_required() ? $schema_locator->get_per_file_schema() : null;
    }
    /**
     * Load configuration scope
     *
     * @return array
     */
    public function read(?string $scope = null)
    {
        $scope = $scope ?: $this->default_scope;
        $file_list = $this->file_resolver->get($this->file_name, $scope);
        if (!count($file_list)) {
            return [];
        }
        return $this->read_files($file_list);
    }
    /**
     * Read configuration files
     *
     * @param \Magento\FunctionalTestingFramework\Util\Iterator\File $fileList
     * @return array
     * @throws \Exception
     */
    protected function read_files($file_list)
    {
        /** @var \Magento\FunctionalTestingFramework\Config\Dom $configMerger */
        $config_merger = null;
        $debug_level = Mftf_Application_Config::get_config()->get_debug_level();
        foreach ($file_list as $content) {
            //check if file is empty and continue to next if it is
            if (!$this->verify_file_empty($content, $file_list->get_filename())) {
                continue;
            }
            try {
                if (!$config_merger) {
                    $config_merger = $this->create_config_merger($this->dom_document_class, $content);
                } else {
                    $config_merger->merge($content);
                }
                if (strcasecmp($debug_level, Mftf_Application_Config::LEVEL_DEVELOPER) === 0) {
                    $this->validate_schema($config_merger, $file_list->get_filename());
                }
            } catch (\Magento\Functional_Testing_Framework\Config\Dom\Validation_Exception $e) {
                throw new \Exception('Invalid XML in file ' . $file_list->get_filename() . ":\n" . $e->get_message());
            }
        }
        $this->validate_schema($config_merger);
        if ($config_merger) {
            return $this->converter->convert($config_merger->get_dom());
        }
        return [];
    }
    /**
     * Return newly created instance of a config merger
     *
     * @param string $mergerClass
     * @param string $initialContents
     * @return \Magento\FunctionalTestingFramework\Config\Dom
     * @throws \UnexpectedValueException
     */
    protected function create_config_merger($merger_class, $initial_contents)
    {
        $result = new $merger_class($initial_contents, $this->id_attributes, null, $this->per_file_schema);
        if (!$result instanceof \Magento\Functional_Testing_Framework\Config\Dom) {
            throw new \UnexpectedValueException("Instance of the DOM config merger is expected, got {$merger_class} instead.");
        }
        return $result;
    }
    /**
     * Checks if content is empty and logs warning, returns false if file is empty
     *
     * @param string $content
     * @param string $fileName
     */
    protected function verify_file_empty($content, $file_name): bool
    {
        if (empty($content)) {
            if (Mftf_Application_Config::get_config()->verbose_enabled()) {
                Logging_Util::get_instance()->get_logger(Filesystem::class)->warning('XML File is empty.', ['File' => $file_name]);
            }
            return false;
        }
        return true;
    }
    /**
     * Validate read xml against expected schema
     *
     * @param string $configMerger
     * @param string $filename
     * @throws \Exception
     * @return void
     */
    protected function validate_schema($config_merger, ?string $filename = null)
    {
        if ($this->validation_state->is_validation_required()) {
            $errors = [];
            if ($config_merger && !$config_merger->validate($this->schema_file, $errors)) {
                foreach ($errors as $error) {
                    $error = str_replace(PHP_EOL, '', $error);
                    Logging_Util::get_instance()->get_logger(Filesystem::class)->critical_failure('Schema validation error ', $filename ? ['file' => $filename, 'error' => $error] : ['error' => $error], true);
                }
                throw new Fast_Fail_Exception('Schema validation errors found in xml file(s)' . $filename);
            }
        }
    }
}