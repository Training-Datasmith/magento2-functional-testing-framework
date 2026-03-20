<?php

/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Util\Filesystem;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
class Cest_File_Creator_Util
{
    /**
     * Singleton CestFileCreatorUtil Instance.
     */
    private static ?\Magento\Functional_Testing_Framework\Util\Filesystem\Cest_File_Creator_Util $INSTANCE = null;
    /**
     * CestFileCreatorUtil constructor.
     */
    private function __construct()
    {
    }
    /**
     * Get CestFileCreatorUtil instance.
     */
    public static function get_instance(): Cest_File_Creator_Util
    {
        if (!self::$INSTANCE) {
            self::$INSTANCE = new Cest_File_Creator_Util();
        }
        return self::$INSTANCE;
    }
    /**
     * Create a single PHP file containing the $cestPhp using the $filename.
     * If the _generated directory doesn't exist it will be created.
     *
     *
     * @throws TestFrameworkException
     */
    public function create(string $filename, string $export_directory, string $test_php): void
    {
        Dir_Setup_Util::create_group_dir($export_directory);
        $export_file_path = $export_directory . DIRECTORY_SEPARATOR . $filename . '.php';
        $file = fopen($export_file_path, 'w');
        if (!$file) {
            throw new Test_Framework_Exception(sprintf('Could not open test file: "%s"', $export_file_path));
        }
        fwrite($file, $test_php);
        fclose($file);
    }
}