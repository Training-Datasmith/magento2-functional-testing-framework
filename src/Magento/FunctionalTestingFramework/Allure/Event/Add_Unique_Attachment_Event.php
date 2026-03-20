<?php

/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Allure\Event;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Symfony\Component\Mime\Mime_Types;
use Yandex\Allure\Adapter\Allure_Exception;
use Yandex\Allure\Adapter\Event\Add_Attachment_Event;
class Add_Unique_Attachment_Event extends Add_Attachment_Event
{
    private const DEFAULT_FILE_EXTENSION = 'txt';
    private const DEFAULT_MIME_TYPE = 'text/plain';
    /**
     * Near copy of parent function, added uniqid call for filename to prevent buggy allure behavior.
     *
     * @param mixed  $filePathOrContents
     * @param string $type
     *
     * @throws AllureException
     */
    public function get_attachment_file_name($file_path_or_contents, $type): string
    {
        $file_path = $file_path_or_contents;
        if (!is_string($file_path) || !file_exists($file_path) || !is_file($file_path)) {
            //Save contents to temporary file
            $file_path = tempnam(sys_get_temp_dir(), 'allure-attachment');
            if (!file_put_contents($file_path, $file_path_or_contents)) {
                throw new Allure_Exception("Failed to save attachment contents to {$file_path}");
            }
        }
        if (!isset($type)) {
            $type = $this->guess_file_mime_type($file_path);
        }
        $file_extension = $this->guess_file_extension($type);
        $file_sha1 = uniqid(sha1_file($file_path));
        $output_path = parent::get_output_path($file_sha1, $file_extension);
        if (!$this->copy_file($file_path, $output_path)) {
            throw new Allure_Exception("Failed to copy attachment from {$file_path} to {$output_path}.");
        }
        return $this->get_output_file_name($file_sha1, $file_extension);
    }
    /**
     * Copies file from one path to another. Wrapper for mocking in unit test.
     *
     *
     * @throws TestFrameworkException
     */
    private function copy_file(string $file_path, string $output_path): bool
    {
        if (Mftf_Application_Config::get_config()->get_phase() === Mftf_Application_Config::UNIT_TEST_PHASE) {
            return true;
        }
        return copy($file_path, $output_path);
    }
    /**
     * Copy of parent private function.
     *
     *
     */
    private function guess_file_mime_type(string $file_path): string
    {
        $type = Mime_Types::get_default()->guess_mime_type($file_path);
        if (!isset($type)) {
            return self::DEFAULT_MIME_TYPE;
        }
        return $type;
    }
    /**
     * Copy of parent private function.
     *
     *
     */
    private function guess_file_extension(string $mime_type): string
    {
        $candidate = Mime_Types::get_default()->get_extensions($mime_type);
        if (empty($candidate)) {
            return self::DEFAULT_FILE_EXTENSION;
        }
        return reset($candidate);
    }
}