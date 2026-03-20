<?php

declare (strict_types=1);
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Module\Util;

class Module_Utils
{
    /**
     * Module util function that returns UTF-8 encoding string with control/invisible characters removed,
     * and it returns the original string when on error.
     */
    public function utf8safe_control_character_trim(string $input): string
    {
        // Convert $input string to UTF-8 encoding
        $conv_input = iconv('ISO-8859-1', 'UTF-8//IGNORE', $input);
        if ($conv_input !== false) {
            // Remove invisible control characters, unused code points and replacement character
            // so that they don't break xml test results for Allure
            $clean_input = preg_replace('/[^\PC\s]|\x{FFFD}/u', '', $conv_input);
            if ($clean_input !== null) {
                return $clean_input;
            }
            $err = preg_last_error_msg();
            print "MagentoCLI response preg_replace() with error {$err}.\n";
        }
        return $input;
    }
}