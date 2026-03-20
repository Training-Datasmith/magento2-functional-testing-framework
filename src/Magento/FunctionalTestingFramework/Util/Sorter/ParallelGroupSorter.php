<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Sorter;

use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
class Parallel_Group_Sorter
{
    /**
     * An array of newly split suite object names mapped to their corresponding objects.
     */
    private array $suite_config = [];
    /**
     * ParallelGroupSorter constructor.
     */
    public function __construct()
    {
        // empty constructor
    }
    /**
     * Function which returns tests and suites split according to desired number of lines divided into groups.
     *
     * @param array   $suiteConfiguration
     * @param array   $testNameToSize
     * @param integer $time
     * @throws FastFailException
     */
    public function get_tests_grouped_by_size($suite_configuration, $test_name_to_size, $time): array
    {
        // we must have the lines argument in order to create the test groups
        if ($time == 0) {
            throw new Fast_Fail_Exception("Please provide the argument '--time' to the robo command in order to" . ' generate grouped tests manifests for a parallel execution');
        }
        $test_groups = [];
        $split_suite_names_to_tests = $this->create_groups_within_suites($suite_configuration, $time);
        $split_suite_names_to_size = $this->get_suite_to_size($split_suite_names_to_tests);
        arsort($test_name_to_size);
        arsort($split_suite_names_to_size);
        $test_name_to_size_for_use = $test_name_to_size;
        $node_number = 1;
        foreach ($split_suite_names_to_size as $test_name => $test_size) {
            $test_groups[$node_number] = [$test_name => $test_size];
            $node_number++;
        }
        foreach ($test_name_to_size as $test_name => $test_size) {
            if (!array_key_exists($test_name, $test_name_to_size_for_use)) {
                // skip tests which have already been added to a group
                continue;
            }
            $test_group = $this->create_test_group($time, $test_name, $test_size, $test_name_to_size_for_use);
            $test_groups[$node_number] = $test_group;
            // unset the test which have been used.
            $test_name_to_size_for_use = array_diff_key($test_name_to_size_for_use, $test_group);
            $node_number++;
        }
        return $test_groups;
    }
    /**
     * Function which returns tests and suites split according to desired number of groups.
     *
     * @param array   $suiteConfiguration
     * @param array   $testNameToSize
     * @param integer $groupTotal
     * @return array
     * @throws FastFailException
     */
    public function get_tests_grouped_by_fixed_group_count($suite_configuration, $test_name_to_size, $group_total)
    {
        if (empty($suite_configuration)) {
            return $this->convert_array_index_starting_at_one($this->split_tests_into_groups($test_name_to_size, $group_total));
        }
        $suite_name_to_test_size = $this->get_suite_name_to_test_size($suite_configuration);
        // Calculate suite group totals
        $suite_name_to_group_count = $this->get_suite_group_counts($suite_name_to_test_size, $test_name_to_size, $group_total);
        $suites_group_total = array_sum($suite_name_to_group_count);
        // Calculate minimum required groups
        $min_suite_group_total = count($suite_name_to_test_size);
        $min_test_group_total = empty($test_name_to_size) ? 0 : 1;
        $min_required_group_total = $min_suite_group_total + $min_test_group_total;
        if ($group_total < $min_required_group_total) {
            throw new Fast_Fail_Exception("Invalid parameter 'groupTotal': must be equal or greater than {$min_required_group_total}");
        }
        if ($group_total < $suites_group_total + $min_test_group_total) {
            // Split in savvy mode when $groupTotal requested is very small
            $test_group_total = $min_test_group_total;
            // Reduce suite group total
            $suite_name_to_group_count = $this->reduce_suite_group_total($suite_name_to_group_count, $group_total - $min_test_group_total);
        } else {
            // Calculate test group total
            $test_group_total = $group_total - $suites_group_total;
        }
        // Split tests and suites
        $test_groups = $this->split_tests_into_groups($test_name_to_size, $test_group_total);
        $test_groups = array_merge($test_groups, $this->split_suites_into_groups($suite_name_to_test_size, $suite_name_to_group_count));
        return $this->convert_array_index_starting_at_one($test_groups);
    }
    /**
     * Return suite's group counts from a group total
     *
     * @param array   $suiteNameToTestSize
     * @param array   $testNameToSize
     * @param integer $groupTotal
     * @return array
     */
    private function get_suite_group_counts($suite_name_to_test_size, $test_name_to_size, $group_total)
    {
        if (empty($suite_name_to_test_size)) {
            return [];
        }
        // Calculate the minimum possible group time
        $suite_name_to_size = $this->get_suite_to_size($suite_name_to_test_size);
        $min_group_time = ceil((array_sum($test_name_to_size) + array_sum($suite_name_to_size)) / $group_total);
        // Find maximum suite time
        $max_suite_time = max($suite_name_to_size);
        // Calculate 2 possible suite group times
        $ceil_suite_group_number = (int) ceil($max_suite_time / $min_group_time);
        $ceil_suite_group_time = max(ceil($max_suite_time / $ceil_suite_group_number), $min_group_time);
        $floor_suite_group_number = (int) floor($max_suite_time / $min_group_time);
        if ($floor_suite_group_number != 0) {
            $floor_suite_group_time = max(ceil($max_suite_time / $floor_suite_group_number), $min_group_time);
        }
        // Calculate test group time for ceiling
        $ceil_suite_name_to_group_count = $this->get_suite_group_count_from_group_time($suite_name_to_test_size, $ceil_suite_group_time);
        $ceil_suite_group_total = array_sum($ceil_suite_name_to_group_count);
        $ceil_test_group_total = $group_total - $ceil_suite_group_total;
        if ($ceil_test_group_total == 0) {
            $ceil_test_group_time = 0;
        } else {
            $ceil_test_group_time = ceil(array_sum($test_name_to_size) / $ceil_test_group_total);
        }
        // Set suite group total to ceiling
        $suite_name_to_group_count = $ceil_suite_name_to_group_count;
        if (isset($floor_suite_group_time) && $ceil_suite_group_time != $floor_suite_group_time) {
            // Calculate test group time for floor
            $floor_suite_name_to_group_count = $this->get_suite_group_count_from_group_time($suite_name_to_test_size, $floor_suite_group_time);
            $floor_suite_group_total = array_sum($floor_suite_name_to_group_count);
            $floor_test_group_total = $group_total - $floor_suite_group_total;
            if ($floor_test_group_total == 0) {
                $floor_test_group_time = 0;
            } else {
                $floor_test_group_time = ceil(array_sum($test_name_to_size) / $floor_test_group_total);
            }
            // Choose the closer value between test group time and suite group time
            $ceil_diff = abs($ceil_test_group_time - $ceil_suite_group_time);
            $floor_diff = abs($floor_test_group_time - $floor_suite_group_time);
            if ($ceil_diff > $floor_diff) {
                // Adjust suite group total to floor
                $suite_name_to_group_count = $floor_suite_name_to_group_count;
            }
        }
        return $suite_name_to_group_count;
    }
    /**
     * Reduce total suite groups to a given $total.
     * This method will reduce 1 from a suite that's greater than 1 repeatedly until sum of all groups reaches $total.
     *
     * @param integer $total
     * @throws FastFailException
     */
    private function reduce_suite_group_total(array $suite_name_to_group_count, int|float $total): array
    {
        if (count($suite_name_to_group_count) > $total) {
            throw new Fast_Fail_Exception("Invalid parameter 'total': must be equal or greater than {count({$suite_name_to_group_count})}");
        }
        $done = false;
        while (!$done) {
            foreach ($suite_name_to_group_count as $suite => $count) {
                if (array_sum($suite_name_to_group_count) == $total) {
                    $done = true;
                    break;
                }
                if ($count > 1) {
                    $suite_name_to_group_count[$suite] -= 1;
                }
            }
        }
        return $suite_name_to_group_count;
    }
    /**
     * Return array contains suitename to number of groups to be split based on time.
     *
     * @param array   $suiteNameToTestSize
     * @param integer $time
     */
    private function get_suite_group_count_from_group_time($suite_name_to_test_size, $time): array
    {
        $suite_name_to_group_count = [];
        foreach ($suite_name_to_test_size as $suite_name => $tests) {
            $max_count = count($tests);
            $suite_time = array_sum($tests);
            if ($suite_time <= $time) {
                $suite_name_to_group_count[$suite_name] = 1;
            } else {
                $suite_name_to_group_count[$suite_name] = min((int) ceil($suite_time / $time), $max_count);
            }
        }
        return $suite_name_to_group_count;
    }
    /**
     * Split tests into given number of groups.
     *
     * @param array   $tests
     * @param integer $groupCnt
     */
    private function split_tests_into_groups($tests, $group_cnt): array
    {
        if (empty($tests)) {
            return [];
        }
        // Reverse sort the test array by size
        uasort($tests, fn($a, $b) => $a >= $b ? -1 : 1);
        $groups = array_fill(0, $group_cnt, []);
        $sums = array_fill(0, $group_cnt, 0);
        foreach ($tests as $test => $size) {
            // Always add the next test to the group with the smallest sum
            $key = array_search(min($sums), $sums);
            $groups[$key][$test] = $size;
            $sums[$key] += $size;
        }
        // Filter empty array
        return array_filter($groups);
    }
    /**
     * Split suites into given number of groups.
     *
     * @param array $suiteNameToTestSize
     */
    private function split_suites_into_groups($suite_name_to_test_size, array $suite_name_to_group_count): array
    {
        $groups = [];
        foreach ($suite_name_to_test_size as $suite_name => $suite_tests) {
            $suite_cnt = $suite_name_to_group_count[$suite_name];
            if ($suite_cnt == 1) {
                $groups[][$suite_name] = array_sum($suite_tests);
                $this->add_suite_to_config($suite_name, null, $suite_tests);
            } elseif ($suite_cnt > 1) {
                $suite_groups = $this->split_tests_into_groups($suite_tests, $suite_cnt);
                foreach ($suite_groups as $index => $tests) {
                    $new_suite_name = $suite_name . '_' . strval($index) . '_G';
                    $groups[][$new_suite_name] = array_sum($tests);
                    $this->add_suite_to_config($suite_name, $new_suite_name, $tests);
                }
            }
        }
        return $groups;
    }
    /**
     * Function which returns the newly formed suite objects created as a part of the sort
     *
     * @return array
     */
    public function get_resulting_suite_config()
    {
        if (empty($this->suite_config)) {
            return null;
        }
        return $this->suite_config;
    }
    /**
     * Function which constructs a group of tests to be run together based on the desired number of lines per group,
     * a test to be used as a starting point, the size of a starting test, an array of tests available to be added to
     * the group.
     *
     * @param integer $timeMaximum
     * @param string  $testName
     * @param integer $testSize
     * @return array
     */
    private function create_test_group($time_maximum, int|string $test_name, $test_size, array $test_name_to_size_for_use)
    {
        $group[$test_name] = $test_size;
        if ($test_size < $time_maximum) {
            while (array_sum($group) < $time_maximum && !empty($test_name_to_size_for_use)) {
                $group_size = array_sum($group);
                $line_goal = $time_maximum - $group_size;
                $test_name_for_use = $this->get_closest_line_count($test_name_to_size_for_use, $line_goal);
                if ($test_name_to_size_for_use[$test_name_for_use] < $line_goal) {
                    $test_size_for_use = $test_name_to_size_for_use[$test_name_for_use];
                    $group[$test_name_for_use] = $test_size_for_use;
                }
                unset($test_name_to_size_for_use[$test_name_for_use]);
            }
        }
        return $group;
    }
    /**
     * Function which takes a group of available tests mapped to size and a desired number of lines matching with the
     * test of closest size and returning.
     *
     * @param integer $desiredValue
     * @return string
     */
    private function get_closest_line_count(array $test_group, float|int $desired_value): int|string|null
    {
        $winner = key($test_group);
        $closest_threshold = $desired_value;
        foreach ($test_group as $test_name => $test_value) {
            // find the difference between the desired value and test candidate for the group
            $test_threshold = $desired_value - $test_value;
            // if we see that the gap between the desired value is non-negative and lower than the current closest make
            // the test the winner.
            if ($closest_threshold > $test_threshold && $test_threshold > 0) {
                $closest_threshold = $test_threshold;
                $winner = $test_name;
            }
        }
        return $winner;
    }
    /**
     * Function which takes an array of test names mapped to suite name and a size limitation for each group of tests.
     * The function divides suites that are over the specified limit and returns the resulting suites in an array.
     *
     * @param array   $suiteConfiguration
     * @param integer $lineLimit
     */
    private function create_groups_within_suites($suite_configuration, $line_limit): array
    {
        $suite_name_to_test_size = $this->get_suite_name_to_test_size($suite_configuration);
        $suite_name_to_size = $this->get_suite_to_size($suite_name_to_test_size);
        // divide the suites up within the array
        $suites_for_resize = array_filter($suite_name_to_size, fn($val) => $val > $line_limit);
        // remove the suites for resize from the original list
        $remaining_suites = array_diff_key($suite_name_to_test_size, $suites_for_resize);
        foreach ($remaining_suites as $remaining_suite => $tests) {
            $this->add_suite_to_config($remaining_suite, null, $tests);
        }
        $resulting_groups = [];
        foreach ($suites_for_resize as $suite_name => $suite_size) {
            $resulting_groups = array_merge($resulting_groups, $this->split_test_suite($suite_name, $suite_name_to_test_size[$suite_name], $line_limit));
        }
        // merge the resulting divisions with the appropriately sized suites
        return array_merge($remaining_suites, $resulting_groups);
    }
    /**
     * Function which takes the given suite configuration and returns an array of suite to test size.
     *
     * @param array $suiteConfiguration
     */
    private function get_suite_name_to_test_size($suite_configuration): array
    {
        $suite_name_to_test_size = [];
        foreach ($suite_configuration as $suite => $test) {
            foreach ($test as $test_name) {
                $suite_name_to_test_size[$suite][$test_name] = Test_Object_Handler::get_instance()->get_object($test_name)->get_estimated_duration();
            }
        }
        return $suite_name_to_test_size;
    }
    /**
     * Function which takes a multidimensional array containing a suite name mapped to an array of tests names as keys
     * with their sizes as values. The function returns an array of suite name to size of the corresponding mapped
     * tests.
     *
     * @param array $suiteNamesToTests
     */
    private function get_suite_to_size($suite_names_to_tests): array
    {
        $suite_names_to_size = [];
        foreach ($suite_names_to_tests as $name => $tests) {
            $size = array_sum($tests);
            $suite_names_to_size[$name] = $size;
        }
        return $suite_names_to_size;
    }
    /**
     * Function which takes a suite name, an array of tests affiliated with that suite, and a maximum number of lines.
     * The function uses the limit to split up the oversized suite and returns an array of suites representative of the
     * previously oversized suite.
     *
     * E.g.
     * Input {suitename = 'sample', tests = ['test1' => 100,'test2' => 150, 'test3' => 300], linelimit = 275}
     * Result { ['sample_01_G' => ['test3' => 300], 'sample_02_G' => ['test2' => 150, 'test1' => 100]] }
     *
     * @param string  $suiteName
     * @param array   $tests
     * @param integer $maxTime
     */
    private function split_test_suite(int|string $suite_name, $tests, $max_time): array
    {
        arsort($tests);
        $split_suites = [];
        $available_tests = $tests;
        $split_count = 0;
        foreach ($tests as $test => $size) {
            if (!array_key_exists($test, $available_tests)) {
                continue;
            }
            $group = $this->create_test_group($max_time, $test, $size, $available_tests);
            $split_suites["{$suite_name}_{$split_count}_G"] = $group;
            $this->add_suite_to_config($suite_name, "{$suite_name}_{$split_count}_G", $group);
            $available_tests = array_diff_key($available_tests, $group);
            $split_count++;
        }
        return $split_suites;
    }
    /**
     * Function which takes a new suite, the original suite from which it was sourced, and an array of tests now
     * associated with thew new suite. The function takes this information and creates a new suite object stored in
     * the sorter for later retrieval, copying the pre/post conditions from the original suite.
     *
     * @param string $originalSuiteName
     * @param string $newSuiteName
     * @param array  $tests
     */
    private function add_suite_to_config($original_suite_name, ?string $new_suite_name, $tests): void
    {
        if ($new_suite_name === null) {
            $this->suite_config[$original_suite_name] = array_keys($tests);
            return;
        }
        $this->suite_config[$original_suite_name][$new_suite_name] = array_keys($tests);
    }
    /**
     * Convert array index starting at 1
     *
     * @param array $inArray
     */
    private function convert_array_index_starting_at_one($in_array): array
    {
        $out_array = [];
        $index = 1;
        foreach ($in_array as $value) {
            $out_array[$index] = $value;
            $index += 1;
        }
        return $out_array;
    }
}