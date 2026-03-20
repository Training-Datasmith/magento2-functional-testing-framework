<?php

/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Static_Check;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
/**
 * Class StaticChecksList has a list of static checks to run on test xml
 * @codingStandardsIgnoreFile
 */
class Static_Checks_List implements Static_Check_List_Interface
{
    public const DEPRECATED_ENTITY_USAGE_CHECK_NAME = 'deprecatedEntityUsage';
    public const PAUSE_ACTION_USAGE_CHECK_NAME = 'pauseActionUsage';
    public const CREATED_DATA_FROM_OUTSIDE_ACTIONGROUP = 'createdDataFromOutsideActionGroup';
    public const UNUSED_ENTITY_CHECK = 'unusedEntityCheck';
    public const CLASS_FILE_NAMING_CHECK = 'classFileNamingCheck';
    public const STATIC_RESULTS = 'tests' . DIRECTORY_SEPARATOR . '_output' . DIRECTORY_SEPARATOR . 'static-results';
    /**
     * Property contains all static check scripts.
     *
     * @var StaticCheckInterface[]
     */
    private readonly array $checks;
    /**
     * Directory path for static checks error files
     */
    private static ?string $error_files_path = null;
    /**
     * Constructor
     *
     * @throws TestFrameworkException
     */
    public function __construct(array $checks = [])
    {
        $this->checks = ['testDependencies' => new Test_Dependency_Check(), 'actionGroupArguments' => new Action_Group_Standards_Check(), self::DEPRECATED_ENTITY_USAGE_CHECK_NAME => new Deprecated_Entity_Usage_Check(), 'annotations' => new Annotations_Check(), self::PAUSE_ACTION_USAGE_CHECK_NAME => new Pause_Action_Usage_Check(), self::UNUSED_ENTITY_CHECK => new Unused_Entity_Check(), self::CREATED_DATA_FROM_OUTSIDE_ACTIONGROUP => new Created_Data_From_Outside_Action_Group_Check(), self::CLASS_FILE_NAMING_CHECK => new Class_File_Naming_Check()] + $checks;
        // Static checks error files directory
        if (null === self::$error_files_path) {
            self::$error_files_path = File_Path_Formatter::format(TESTS_BP) . self::STATIC_RESULTS;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_static_checks()
    {
        return $this->checks;
    }
    /**
     * Return the directory path for the static check error files
     */
    public static function get_error_files_path()
    {
        return self::$error_files_path;
    }
    /**
     * Return relative path to files for unit testing purposes.
     * @param string $fileNames
     * @return string
     */
    public static function get_file_path($file_names)
    {
        if (!empty($file_names)) {
            $relative_file_names = ltrim(str_replace(MAGENTO_BP, '', $file_names));
            if (!empty($relative_file_names)) {
                return $relative_file_names;
            }
        }
        return $file_names;
    }
}