<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Filesystem;

use Filesystem_Iterator;
use Recursive_Directory_Iterator;
class Dir_Setup_Util
{
    /**
     * Array which will track any previously cleared directories, to prevent any unintended removal.
     */
    private static array $DIR_CONTEXT = [];
    /**
     * Method used to clean export dir if needed and create new empty export dir.
     *
     * @param string $fullPath
     */
    public static function create_group_dir($full_path): void
    {
        //prevent redundant calls to these directories
        $sanitized_path = rtrim($full_path, DIRECTORY_SEPARATOR);
        // make sure we haven't already cleaned up this directory at any point before deletion
        if (in_array($sanitized_path, self::$DIR_CONTEXT)) {
            return;
        }
        if (file_exists($sanitized_path)) {
            self::rm_dir_recursive($sanitized_path);
        }
        mkdir($sanitized_path, 0777, true);
        self::$DIR_CONTEXT[] = $sanitized_path;
    }
    /**
     * Takes a directory path and recursively deletes all files and folders.
     */
    public static function rmdir_recursive(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $it = new Recursive_Directory_Iterator($directory, Filesystem_Iterator::SKIP_DOTS);
        while ($it->valid()) {
            $path = $directory . DIRECTORY_SEPARATOR . $it->get_filename();
            if ($it->is_dir()) {
                self::rm_dir_recursive($path);
            } else {
                unlink($path);
            }
            $it->next();
        }
        rmdir($directory);
    }
}