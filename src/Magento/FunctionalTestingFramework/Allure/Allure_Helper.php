<?php

/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Allure;

use Qameta\Allure\Allure;
use Qameta\Allure\Io\Data_Source_Interface;
class Allure_Helper
{
    /**
     * Adds attachment to the current step.
     *
     * @param mixed  $data
     * @param string $caption
     */
    public static function add_attachment_to_current_step($data, $caption): void
    {
        if (!is_string($data)) {
            try {
                $data = serialize($data);
            } catch (\Exception) {
                throw new \Exception($data->get_message());
            }
        }
        if (@file_exists($data) && is_file($data)) {
            Allure::attachment_file($caption, $data);
        } else {
            Allure::attachment($caption, $data);
        }
    }
    /**
     * Adds Attachment to the last executed step.
     * Use this when adding attachments outside of an $I->doSomething() step/context.
     *
     * @param mixed  $data
     * @param string $caption
     */
    public static function add_attachment_to_last_step($data, $caption): void
    {
        if (!is_string($data)) {
            $data = serialize($data);
        }
        if (@file_exists($data) && is_file($data)) {
            Allure::attachment_file($caption, $data);
        } else {
            Allure::attachment($caption, $data);
        }
    }
    public static function do_add_attachment(Data_Source_Interface $data_source, string $name, ?string $type = null, ?string $file_extension = null): void
    {
        $attachment = Allure::get_config()->get_result_factory()->create_attachment()->set_name($name)->set_type($type)->set_file_extension($file_extension);
        Allure::get_lifecycle()->add_attachment($attachment, $data_source);
    }
}