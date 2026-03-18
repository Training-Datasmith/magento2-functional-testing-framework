<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config\Reader;

use Magento\FunctionalTestingFramework\Config\MftfApplicationConfig;
use Magento\FunctionalTestingFramework\Exceptions\FastFailException;
use Magento\FunctionalTestingFramework\Util\Logger\LoggingUtil;

/**
 * Filesystem configuration loader. Loads configuration from XML files, split by scopes.
 */
class Filesystem implements \Magento\FunctionalTestingFramework\Config\ReaderInterface
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
    protected $perFileSchema;

    /**
     * List of id attributes for merge
     */
    protected array $idAttributes = [];

    /**
     * File path to schema file.
     *
     * @var string
     */
    protected $schemaFile;

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
        protected \Magento\FunctionalTestingFramework\Config\FileResolverInterface $fileResolver,
        /**
         * Config converter
         */
        protected \Magento\FunctionalTestingFramework\Config\ConverterInterface $converter,
        \Magento\FunctionalTestingFramework\Config\SchemaLocatorInterface $schemaLocator,
        /**
         * Config validation state object.
         */
        protected \Magento\FunctionalTestingFramework\Config\ValidationStateInterface $validationState,
        /**
         * The name of file that stores configuration
         */
        protected $fileName,
        $idAttributes = [],
        /**
         * Class of dom configuration document used for merge
         */
        protected $domDocumentClass = \Magento\FunctionalTestingFramework\Config\Dom::class,
        /**
         * Default scope.
         */
        protected $defaultScope = 'global'
    ) {
        $this->idAttributes = array_replace($this->idAttributes, $idAttributes);
        $this->schemaFile = $schemaLocator->getSchema();
        $this->perFileSchema = $schemaLocator->getPerFileSchema() && $this->validationState->isValidationRequired()
            ? $schemaLocator->getPerFileSchema() : null;
    }

    /**
     * Load configuration scope
     *
     * @return array
     */
    public function read(?string $scope = null)
    {
        $scope = $scope ?: $this->defaultScope;
        $fileList = $this->fileResolver->get($this->fileName, $scope);
        if (!count($fileList)) {
            return [];
        }

        return $this->readFiles($fileList);
    }

    /**
     * Read configuration files
     *
     * @param \Magento\FunctionalTestingFramework\Util\Iterator\File $fileList
     * @return array
     * @throws \Exception
     */
    protected function readFiles($fileList)
    {
        /** @var \Magento\FunctionalTestingFramework\Config\Dom $configMerger */
        $configMerger = null;
        $debugLevel = MftfApplicationConfig::getConfig()->getDebugLevel();
        foreach ($fileList as $content) {
            //check if file is empty and continue to next if it is
            if (!$this->verifyFileEmpty($content, $fileList->getFilename())) {
                continue;
            }
            try {
                if (!$configMerger) {
                    $configMerger = $this->createConfigMerger($this->domDocumentClass, $content);
                } else {
                    $configMerger->merge($content);
                }
                if (strcasecmp($debugLevel, MftfApplicationConfig::LEVEL_DEVELOPER) === 0) {
                    $this->validateSchema($configMerger, $fileList->getFilename());
                }
            } catch (\Magento\FunctionalTestingFramework\Config\Dom\ValidationException $e) {
                throw new \Exception("Invalid XML in file " . $fileList->getFilename() . ":\n" . $e->getMessage());
            }
        }
        $this->validateSchema($configMerger);
        if ($configMerger) {
            return $this->converter->convert($configMerger->getDom());
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
    protected function createConfigMerger($mergerClass, $initialContents)
    {
        $result = new $mergerClass(
            $initialContents,
            $this->idAttributes,
            null,
            $this->perFileSchema
        );
        if (!$result instanceof \Magento\FunctionalTestingFramework\Config\Dom) {
            throw new \UnexpectedValueException(
                "Instance of the DOM config merger is expected, got {$mergerClass} instead."
            );
        }
        return $result;
    }

    /**
     * Checks if content is empty and logs warning, returns false if file is empty
     *
     * @param string $content
     * @param string $fileName
     */
    protected function verifyFileEmpty($content, $fileName): bool
    {
        if (empty($content)) {
            if (MftfApplicationConfig::getConfig()->verboseEnabled()) {
                LoggingUtil::getInstance()->getLogger(Filesystem::class)->warning(
                    "XML File is empty.",
                    ["File" => $fileName]
                );
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
    protected function validateSchema($configMerger, ?string $filename = null)
    {
        if ($this->validationState->isValidationRequired()) {
            $errors = [];
            if ($configMerger && !$configMerger->validate($this->schemaFile, $errors)) {
                foreach ($errors as $error) {
                    $error = str_replace(PHP_EOL, "", $error);
                    LoggingUtil::getInstance()->getLogger(Filesystem::class)->criticalFailure(
                        "Schema validation error ",
                        ($filename ? [ "file"=> $filename, "error" => $error]: ["error" => $error]),
                        true
                    );
                }
                throw new FastFailException("Schema validation errors found in xml file(s)" . $filename);
            }
        }
    }
}
