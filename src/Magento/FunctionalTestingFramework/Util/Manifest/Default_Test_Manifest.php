<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Manifest;

use Magento\Functional_Testing_Framework\Test\Objects\Test_Object;
class Default_Test_Manifest extends Base_Test_Manifest
{
    public const DEFAULT_CONFIG = 'default';
    /**
     * Path to the test manifest file.
     */
    protected string $manifest_path;
    /**
     * A static array to track which test manifests have been cleared to prevent overwriting during generation.
     */
    private static array $CLEARED_MANIFESTS = [];
    /**
     * An array containing all test names for output.
     *
     * @var string[]
     */
    protected $test_names = [];
    /**
     * DefaultTestManifest constructor.
     *
     * @param array  $suiteConfiguration
     * @param string $testPath
     */
    public function __construct($suite_configuration, $test_path)
    {
        $this->manifest_path = dirname($test_path) . DIRECTORY_SEPARATOR . 'testManifest.txt';
        $this->clean_manifest($this->manifest_path);
        parent::__construct($test_path, self::DEFAULT_CONFIG, $suite_configuration);
    }
    /**
     * Takes a test name and set of tests, records the names in a file for codeception to consume.
     *
     * @param TestObject $testObject
     */
    public function add_test($test_object): void
    {
        $this->test_names[] = $test_object->get_codeception_name();
    }
    /**
     * Function which outputs a list of all test files to the defined testManifest.txt file.
     */
    public function generate(): void
    {
        $file_resource = fopen($this->manifest_path, 'a');
        foreach ($this->test_names as $test_name) {
            $line = $this->relative_dir_path . DIRECTORY_SEPARATOR . $test_name . '.php';
            fwrite($file_resource, $line . PHP_EOL);
        }
        $this->generate_suite_entries($file_resource);
        fclose($file_resource);
    }
    /**
     * Function which takes the test suites passed to the manifest and generates corresponding entries in the manifest.
     *
     * @param resource $fileResource
     * @return void
     */
    protected function generate_suite_entries($file_resource)
    {
        foreach ($this->get_suite_config() as $suite_name => $tests) {
            if (count($tests) === 0) {
                continue;
            }
            $line = "-g {$suite_name}";
            fwrite($file_resource, $line . PHP_EOL);
        }
    }
    /**
     * Function which checks the path for an existing test manifest and clears if the file has not already been cleared
     * during current runtime.
     */
    private function clean_manifest(string $path): void
    {
        // if we have already cleared the file then simply return
        if (in_array($path, self::$CLEARED_MANIFESTS)) {
            return;
        }
        // if the file exists remove
        if (file_exists($path)) {
            unlink($path);
        }
        self::$CLEARED_MANIFESTS[] = $path;
    }
}