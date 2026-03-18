<?php

declare(strict_types=1);
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Module\Util;

class ModuleUtils
{
    /**
     * Module util function that returns UTF-8 encoding string with control/invisible characters removed,
     * and it returns the original string when on error.
     */
    public function utf8SafeControlCharacterTrim(string $input): string
    {
        // Convert $input string to UTF-8 encoding
        $convInput = iconv('ISO-8859-1', 'UTF-8//IGNORE', $input);
        if ($convInput !== false) {
            // Remove invisible control characters, unused code points and replacement character
            // so that they don't break xml test results for Allure
            $cleanInput = preg_replace('/[^\PC\s]|\x{FFFD}/u', '', $convInput);
            if ($cleanInput !== null) {
                return $cleanInput;
            }
            $err = preg_last_error_msg();
            print("MagentoCLI response preg_replace() with error $err.\n");
        }

        return $input;
    }
}
