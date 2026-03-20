<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Upgrade;

use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
/**
 * Class UpdateAssertionSchema
 * @package Magento\FunctionalTestingFramework\Upgrade
 */
class Update_Assertion_Schema implements Upgrade_Interface
{
    /**
     * Upgrades all test xml files, changing as many <assert> actions to be nested as possible
     * WILL NOT CATCH cases where style is a mix of old and new
     */
    public function execute(Input_Interface $input, Output_Interface $output): string
    {
        $script_util = new Script_Util();
        $test_paths[] = $input->get_argument('path');
        if (empty($test_paths[0])) {
            $test_paths = $script_util->get_all_module_paths();
        }
        $tests_updated = 0;
        foreach ($test_paths as $tests_path) {
            $finder = new Finder();
            $finder->files()->in($tests_path)->name('*.xml');
            $file_system = new Filesystem();
            foreach ($finder->files() as $file) {
                $contents = $file->get_contents();
                // Isolate <assert ... /> but never <assert> ... </assert>, stops after finding first />
                preg_match_all('/<assert.*\/>/', $contents, $potential_assertions);
                $new_assertions = [];
                $index = 0;
                if (empty($potential_assertions[0])) {
                    continue;
                }
                foreach ($potential_assertions[0] as $potential_assertion) {
                    $new_assertions[$index] = $this->convert_old_assertion_to_new($potential_assertion);
                    $index++;
                }
                foreach ($new_assertions as $current_index => $replacements) {
                    $contents = str_replace($potential_assertions[0][$current_index], $replacements, $contents);
                }
                $file_system->dump_file($file->get_real_path(), $contents);
                $tests_updated++;
            }
        }
        return "Assertion Syntax updated in {$tests_updated} file(s).";
    }
    /**
     * Takes given string and attempts to convert it from single line to multi-line
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function convert_old_assertion_to_new(string $assertion): string
    {
        // <assertSomething => assertSomething
        $assert_type = ltrim(explode(' ', $assertion)[0], '<');
        // regex to all attribute=>value pairs
        $all_attributes = 'stepKey|actual|actualType|expected|expectedType|expectedValue|';
        $all_attributes .= 'delta|message|selector|attribute|before|after|remove';
        $grab_value_regex = '/(' . $all_attributes . ')=(\'[^\']*\'|"[^"]*")/';
        // Makes 3 arrays in $grabbedParts:
        // 0 contains stepKey="value"
        // 1 contains stepKey
        // 2 contains value
        $sorted_parts = [];
        preg_match_all($grab_value_regex, $assertion, $grabbed_parts);
        for ($i = 0; $i < count($grabbed_parts[0]); $i++) {
            $sorted_parts[$grabbed_parts[1][$i]] = $grabbed_parts[2][$i];
        }
        // Begin trimming values and adding back into new string
        $trimmed_parts = [];
        $new_string = "<{$assert_type}";
        $sub_elements = ['actual' => [], 'expected' => []];
        foreach ($sorted_parts as $type => $value) {
            // If attribute="'value'", elseif attribute='"value"', new nested format will break if we leave these in
            if (str_starts_with($value, '"')) {
                $value = rtrim(ltrim($value, '"'), '"');
            } elseif (str_starts_with($value, "'")) {
                $value = rtrim(ltrim($value, "'"), "'");
            }
            // If value is empty string (" " or ' '), trim again to become empty
            if (str_replace(' ', '', $value) === "''") {
                $value = '';
            } elseif (str_replace(' ', '', $value) === '""') {
                $value = '';
            }
            // Value is ready for storage/reapply
            $trimmed_parts[$type] = $value;
            if (in_array($type, ['stepKey', 'delta', 'message', 'before', 'after', 'remove'])) {
                // Add back as attribute safely
                $new_string .= " {$type}=\"{$value}\"";
                continue;
            }
            // Store in subtype for child element creation
            if ($type === 'actual') {
                $sub_elements['actual']['value'] = $value;
            } elseif ($type === 'actualType') {
                $sub_elements['actual']['type'] = $value;
            } elseif ($type === 'expected' or $type === 'expectedValue') {
                $sub_elements['expected']['value'] = $value;
            } elseif ($type === 'expectedType') {
                $sub_elements['expected']['type'] = $value;
            }
        }
        $new_string .= ">\n";
        // Assert type is very edge-cased, completely different schema
        if ($assert_type === 'assertElementContainsAttribute') {
            // assertElementContainsAttribute type defaulted to string if not present
            if (!isset($sub_elements['expected']['type'])) {
                $sub_elements['expected']['type'] = 'string';
            }
            $value = $sub_elements['expected']['value'] ?? '';
            $type = $sub_elements['expected']['type'];
            $selector = $trimmed_parts['selector'];
            $attribute = $trimmed_parts['attribute'];
            // @codingStandardsIgnoreStart
            $new_string .= "\t\t\t<expectedResult selector=\"{$selector}\" attribute=\"{$attribute}\" type=\"{$type}\">{$value}</expectedResult>\n";
            // @codingStandardsIgnoreEnd
        } else {
            // Set type to const if it's absent, old default
            if (isset($sub_elements['actual']['value']) && !isset($sub_elements['actual']['type'])) {
                $sub_elements['actual']['type'] = 'const';
            }
            if (isset($sub_elements['expected']['value']) && !isset($sub_elements['expected']['type'])) {
                $sub_elements['expected']['type'] = 'const';
            }
            foreach ($sub_elements as $type => $sub_element) {
                if (empty($sub_element)) {
                    continue;
                }
                $value = $sub_element['value'];
                $type_value = $sub_element['type'];
                $new_string .= "\t\t\t<{$type}Result type=\"{$type_value}\">{$value}</{$type}Result>\n";
            }
        }
        return $new_string . "        </{$assert_type}>";
    }
}