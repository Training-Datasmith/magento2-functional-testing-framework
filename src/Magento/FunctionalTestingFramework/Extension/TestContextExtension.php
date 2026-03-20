<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Extension;

use Codeception\Events;
use Codeception\Step;
use Codeception\Test\Test;
use Magento\Functional_Testing_Framework\Allure\Allure_Helper;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Persisted_Object_Handler;
use Magento\Functional_Testing_Framework\Suite\Handlers\Suite_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Group_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
use Qameta\Allure\Allure;
use Qameta\Allure\Model\Step_Result;
use Qameta\Allure\Model\Test_Result;
/**
 * Class TestContextExtension
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Test_Context_Extension extends Base_Extension
{
    private const STEP_PASSED = 'passed';
    /**
     * Test files cache.
     */
    private array $test_files = [];
    /**
     * Action group step key.
     *
     * @var null|string
     */
    private $action_group_step_key;
    /**
     * Boolean value to indicate if steps are invisible steps
     */
    private bool $at_invisible_steps = false;
    public const TEST_PHASE_AFTER = '_after';
    public const TEST_PHASE_BEFORE = '_before';
    public const TEST_FAILED_FILE = 'failed';
    public const TEST_HOOKS = [self::TEST_PHASE_AFTER => 'AfterHook', self::TEST_PHASE_BEFORE => 'BeforeHook'];
    /**
     * Codeception Events Mapping to methods
     * @var array
     */
    public static $events;
    /**
     * The name of the currently running test
     * @var string
     */
    public $current_test;
    /**
     * Initialize local vars
     *
     * @throws \Exception
     */
    public function _initialize(): void
    {
        $events = [Events::TEST_START => 'testStart', Events::STEP_AFTER => 'afterStep', Events::TEST_END => 'testEnd', Events::RESULT_PRINT_AFTER => 'saveFailed'];
        self::$events = array_merge(parent::$events, $events);
    }
    /**
     * Codeception event listener function, triggered on test start.
     * @throws \Exception
     */
    public function test_start(\Codeception\Event\Test_Event $e): void
    {
        if (getenv('ENABLE_CODE_COVERAGE') === 'true') {
            // Curl against test.php and pass in the test name. Used when gathering code coverage.
            $this->current_test = $e->get_test()->get_metadata()->get_name();
            $c_url_connection = curl_init();
            curl_setopt_array($c_url_connection, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_URL => getenv('MAGENTO_BASE_URL') . '/test.php?test=' . $this->current_test]);
            curl_exec($c_url_connection);
        }
        Persisted_Object_Handler::get_instance()->clear_hook_objects();
        Persisted_Object_Handler::get_instance()->clear_test_objects();
    }
    /**
     * Codeception event listener function, triggered on test ending naturally or by errors/failures.
     * @throws \Exception
     */
    public function test_end(\Codeception\Event\Test_Event $e): void
    {
        $cest = $e->get_test();
        //Access private TestResultObject to find stack and if there are any errors/failures
        $test_result_object = call_user_func(\Closure::bind(fn() => $cest->get_result_aggregator(), $cest));
        // check for errors in all test hooks and attach in allure
        if (!empty($test_result_object->errors())) {
            foreach ($test_result_object->errors() as $error) {
                if ($error->get_test()->get_test_method() === $cest->get_test_method()) {
                    $this->attach_exception_to_allure($error->get_fail(), $cest->get_test_method());
                }
            }
        }
        // check for failures in all test hooks and attach in allure
        if (!empty($test_result_object->failures())) {
            foreach ($test_result_object->failures() as $failure) {
                if ($failure->get_test()->get_test_method() === $cest->get_test_method()) {
                    $this->attach_exception_to_allure($failure->get_fail(), $cest->get_test_method());
                }
            }
        }
        // Reset Session and Cookies after all Test Runs, workaround due to functional.suite.yml restart: true
        $this->get_driver()->_run_after($e->get_test());
        $lifecycle = Allure::get_lifecycle();
        $lifecycle->update_test(function (Test_Result $test_result): void {
            $this->get_formatted_steps($test_result);
        });
        $this->add_tests_in_suites($lifecycle, $cest);
    }
    /**
     * Function to add test under the suites.
     *
     * @param object $lifecycle
     * @param object $cest
     */
    private function add_tests_in_suites($lifecycle, $cest): void
    {
        $group_name = null;
        if ($this->options['groups'] !== null) {
            $group = $this->options['groups'][0];
            $group_name = $this->sanitize_group_name($group);
        }
        $lifecycle->update_test(function (Test_Result $test_result) use ($group_name, $cest): void {
            $labels = $test_result->get_labels();
            foreach ($labels as $label) {
                if ($group_name !== null && $label->get_name() === 'parentSuite') {
                    $label->set_value(sprintf('%s\%s', $label->get_value(), $group_name));
                }
                if ($label->get_name() === 'package') {
                    $class_name = $cest->get_report_fields()['class'];
                    $class_name = preg_replace('{_[0-9]*_G}', '', $class_name);
                    $label->set_value($class_name);
                }
            }
        });
    }
    /**
     * Function which santizes any group names changed by the framework for execution in order to consolidate reporting.
     *
     * @param string $group
     */
    private function sanitize_group_name($group): string
    {
        $suite_names = array_keys(Suite_Object_Handler::get_instance()->get_all_objects());
        $exact_match = in_array($group, $suite_names);
        // if this is an existing suite name we dont' need to worry about changing it
        if ($exact_match || !str_contains($group, '_')) {
            return $group;
        }
        // if we can't find this group in the generated suites we have to assume that the group was split for generation
        $group_name_split = explode('_', $group);
        array_pop($group_name_split);
        array_pop($group_name_split);
        $original_name = implode('_', $group_name_split);
        // confirm our original name is one of the existing suite names otherwise just return the original group name
        $original_name = in_array($original_name, $suite_names) ? $original_name : $group;
        return $original_name;
    }
    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * Revisited to reduce cyclomatic complexity, left unrefactored for readability
     */
    private function get_formatted_steps(Test_Result $test_result): void
    {
        $steps = $test_result->get_steps();
        $formatted_steps = [];
        $action_group_key = null;
        foreach ($steps as $key => $step) {
            if (str_contains((string) $step->get_name(), 'start before hook') || str_contains((string) $step->get_name(), 'end before hook') || str_contains((string) $step->get_name(), 'start after hook') || str_contains((string) $step->get_name(), 'end after hook')) {
                $step->set_name(strtoupper((string) $step->get_name()));
            }
            // Remove all parameters from step because parameters already added in formatted step
            call_user_func(\Closure::bind(function () use ($step): void {
                $step->parameters = [];
            }, null, $step));
            if (str_contains((string) $step->get_name(), Action_Group_Object::ACTION_GROUP_CONTEXT_START)) {
                $step->set_name(str_replace(Action_Group_Object::ACTION_GROUP_CONTEXT_START, '', $step->get_name()));
                $action_group_key = $key;
                $formatted_steps[$action_group_key] = $step;
                continue;
            }
            if (stripos((string) $step->get_name(), Action_Group_Object::ACTION_GROUP_CONTEXT_END) !== false) {
                $action_group_key = null;
                continue;
            }
            if ($action_group_key !== null) {
                if ($step->get_name() !== null) {
                    $formatted_steps[$action_group_key]->add_steps($step);
                    if ($step->get_status()->jsonSerialize() !== self::STEP_PASSED) {
                        $formatted_steps[$action_group_key]->set_status($step->get_status());
                        $action_group_key = null;
                    }
                }
            } else if ($step->get_name() !== null) {
                $formatted_steps[$key] = $step;
            }
        }
        /** @var StepResult[] $formattedSteps*/
        $formatted_steps = array_values($formatted_steps);
        // No public function for setting the testResult steps
        call_user_func(\Closure::bind(function () use ($test_result, $formatted_steps): void {
            $test_result->steps = $formatted_steps;
        }, null, $test_result));
    }
    /**
     * Extracts hook method from trace, looking specifically for the cest class given.
     * @param array  $trace
     * @param string $class
     * @return string
     */
    public function extract_context($trace, $class)
    {
        foreach ($trace as $entry) {
            $trace_class = $entry['class'] ?? null;
            if (!str_starts_with($trace_class, $class)) {
                return $entry['function'];
            }
        }
        return null;
    }
    /**
     * Attach stack trace of exceptions thrown in each test hook to allure.
     * @param  \Exception $exception
     * @param  string     $testMethod
     */
    public function attach_exception_to_allure($exception, $test_method): void
    {
        if (is_subclass_of($exception, \Php_Unit\Framework\Exception::class)) {
            $trace = $exception->get_serializable_trace();
        } else {
            $trace = $exception->get_trace();
        }
        $context = $this->extract_context($trace, $test_method);
        if (isset(self::TEST_HOOKS[$context])) {
            $context = self::TEST_HOOKS[$context];
        } else {
            $context = 'TestMethod';
        }
        Allure_Helper::add_attachment_to_current_step($exception, $context . 'Exception');
        $previous_exception = null;
        if ($exception instanceof \Php_Unit\Framework\Exception_Wrapper) {
            $previous_exception = $exception->get_previous_wrapped();
        } elseif ($exception instanceof \Throwable) {
            $previous_exception = $exception->get_previous();
        }
        if ($previous_exception !== null) {
            $this->attach_exception_to_allure($previous_exception, $test_method);
        }
    }
    /**
     * Codeception event listener function, triggered before step.
     * Check if it's a new page.
     *
     * @throws \Exception
     */
    public function before_step(\Codeception\Event\Step_Event $e): void
    {
        if ($this->page_changed()) {
            $this->get_driver()->clean_js_error();
        }
    }
    /**
     * @return string|void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * Revisited to reduce cyclomatic complexity, left unrefactored for readability
     */
    public function step_name(\Codeception\Event\Step_Event $e)
    {
        $step_action = $e->get_step()->get_action();
        if (in_array($step_action, Action_Object::INVISIBLE_STEP_ACTIONS)) {
            $this->at_invisible_steps = true;
            return;
        }
        // Set back atInvisibleSteps flag
        if ($this->at_invisible_steps && !in_array($step_action, Action_Object::INVISIBLE_STEP_ACTIONS)) {
            $this->at_invisible_steps = false;
        }
        //Hard set to 200; we don't expose this config in MFTF
        $arguments_length = 200;
        $step_key = null;
        if (!$e->get_step() instanceof Comment) {
            $step_key = $this->retrieve_step_key_for_allure($e->get_step(), $e->get_test()->get_metadata()->get_filename());
            $is_action_group = str_contains((string) $e->get_step()->__toString(), Action_Group_Object::ACTION_GROUP_CONTEXT_START);
            if ($is_action_group) {
                preg_match(Test_Generator::ACTION_GROUP_STEP_KEY_REGEX, (string) $e->get_step()->__toString(), $matches);
                if (!empty($matches['actionGroupStepKey'])) {
                    $this->action_group_step_key = ucfirst($matches['actionGroupStepKey']);
                }
            }
        }
        // DO NOT alter action if actionGroup is starting, need the exact actionGroup name for good logging
        if (!str_contains((string) $step_action, Action_Group_Object::ACTION_GROUP_CONTEXT_START)) {
            $step_action = $e->get_step()->get_humanized_action_without_arguments();
        }
        $step_args = $e->get_step()->get_arguments_as_string($arguments_length);
        $step_name = '';
        $step_name .= '[' . $step_key . '] ';
        if (empty($step_key)) {
            $step_name = '';
        }
        $step_name .= $step_action . ' ' . $step_args;
        // Strip control characters so that report generation does not fail
        $step_name = preg_replace('/[[:cntrl:]]/', '', $step_name);
        if (stripos((string) $step_name, "\\mftf\\helper")) {
            preg_match("/\\[(.*?)\\]/", (string) $step_name, $matches);
            $step_key_data = preg_split('/\s+/', ucwords($matches[1]));
            if (count($step_key_data) > 0) {
                $this->action_group_step_key ??= '';
                $step_key_helper = str_replace($this->action_group_step_key, '', lcfirst(implode('', $step_key_data)));
                $step_name = '[' . $step_key_helper . '] ' . preg_replace('#\[.*\]#', '', (string) $step_name);
            }
        }
        return ucfirst($step_name);
    }
    /**
     * Codeception event listener function, triggered after step.
     * Calls ErrorLogger to log JS errors encountered.
     * @throws \Exception
     */
    public function after_step(\Codeception\Event\Step_Event $e): void
    {
        $lifecycle = Allure::get_lifecycle();
        $step_name = $this->step_name($e);
        $lifecycle->update_step(function (Step_Result $step) use ($step_name): void {
            $step->set_name($step_name);
        });
        $browser_log = [];
        try {
            $browser_log = $this->get_driver()->web_driver->manage()->get_log('browser');
        } catch (\Exception) {
        }
        if (getenv('ENABLE_BROWSER_LOG') === 'true') {
            foreach (explode(',', getenv('BROWSER_LOG_BLOCKLIST')) as $source) {
                $browser_log = Browser_Log_Util::filter_logs_of_type($browser_log, $source);
            }
            if (!empty($browser_log)) {
                Allure_Helper::add_attachment_to_current_step(json_encode($browser_log, JSON_PRETTY_PRINT), 'Browser Log');
            }
        }
        Browser_Log_Util::log_errors($browser_log, $this->get_driver(), $e);
    }
    /**
     * Saves failed tests from last codecept run command into a file in _output directory
     * Removes file if there were no failures in last run command
     */
    public function save_failed(\Codeception\Event\Print_Result_Event $e): void
    {
        $file = $this->get_log_dir() . self::TEST_FAILED_FILE;
        $result = $e->get_result();
        $output = [];
        // Remove previous file regardless if we're writing a new file
        if (is_file($file)) {
            unlink($file);
        }
        foreach ($result->failures() as $fail) {
            $output[] = $this->localize_path(\Codeception\Test\Descriptor::get_test_full_name($fail->get_test()));
        }
        foreach ($result->errors() as $fail) {
            $output[] = $this->localize_path(\Codeception\Test\Descriptor::get_test_full_name($fail->get_test()));
        }
        foreach ($result->incomplete() as $fail) {
            $output[] = $this->localize_path(\Codeception\Test\Descriptor::get_test_full_name($fail->get_test()));
        }
        if (empty($output)) {
            return;
        }
        file_put_contents($file, implode("\n", $output));
    }
    /**
     * Returns localized path to string, for writing failed file.
     * @param string $path
     * @return string
     */
    protected function localize_path($path)
    {
        $root = realpath($this->get_root_dir()) . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }
        return $path;
    }
    /**
     * Reading stepKey from file.
     *
     * @return string|null
     */
    private function retrieve_step_key_for_allure(Step $step, string $file_path): string|array|null
    {
        $step_key = null;
        $step_line = $step->get_line_number();
        $step_line = $step_line - 1;
        //If the step's filepath is different from the test, it's a comment action.
        if ($this->get_root_dir() . $step->get_file_path() != $file_path) {
            return '';
        }
        if (!array_key_exists($file_path, $this->test_files)) {
            $this->test_files[$file_path] = explode(PHP_EOL, file_get_contents($file_path));
        }
        preg_match(Test_Generator::ACTION_STEP_KEY_REGEX, (string) $this->test_files[$file_path][$step_line], $matches);
        if (!empty($matches['stepKey'])) {
            $step_key = $matches['stepKey'];
        }
        if ($this->action_group_step_key !== null) {
            $step_key = str_replace($this->action_group_step_key, '', $step_key);
        }
        return $step_key === '[]' ? null : $step_key;
    }
}