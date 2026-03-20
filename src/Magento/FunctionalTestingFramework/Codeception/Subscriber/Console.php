<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Codeception\Subscriber;

use Codeception\Event\Step_Event;
use Codeception\Event\Test_Event;
use Codeception\Lib\Console\Message;
use Codeception\Step;
use Codeception\Step\Comment;
use Codeception\Test\Interfaces\Scenario_Driven;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Group_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
use Symfony\Component\Console\Formatter\Output_Formatter;
/**
 * @SuppressWarnings(PHPMD)
 */
class Console extends \Codeception\Subscriber\Console
{
    /**
     * Regular expresion to find deprecated notices.
     */
    public const DEPRECATED_NOTICE = '/<li>(?<deprecatedMessage>.*?)<\/li>/m';
    /**
     * Test files cache.
     */
    private array $test_files = [];
    /**
     * Action group step key.
     */
    private ?string $action_group_step_key = null;
    /**
     * Boolean value to indicate if steps are invisible steps
     */
    private bool $at_invisible_steps = false;
    /**
     * Console constructor. Parent constructor requires codeception CLI options, and does not have its own configs.
     * Constructor is only different than parent due to the way Codeception instantiates Extensions.
     *
     * @param array $extensionOptions
     * @param array $options
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct($extension_options = [], $options = [])
    {
        parent::__construct($options);
    }
    /**
     * Triggered event before each test.
     *
     * @throws \Exception
     */
    public function start_test(Test_Event $e): void
    {
        $test = $e->get_test();
        $test_reflection = new \ReflectionClass($test);
        try {
            $test_reflection = new \ReflectionClass($test);
            $is_deprecated = preg_match_all(self::DEPRECATED_NOTICE, $test_reflection->get_doc_comment(), $match);
            if ($is_deprecated) {
                $this->message('DEPRECATION NOTICE(S): ')->style('debug')->writeln();
                foreach ($match['deprecatedMessage'] as $deprecated_message) {
                    $this->message(' - ' . $deprecated_message)->style('debug')->writeln();
                }
            }
        } catch (\Reflection_Exception $e) {
            Logging_Util::get_instance()->get_logger(self::class)->error($e->get_message(), $e->get_trace());
        }
        parent::start_test($e);
    }
    /**
     * Printing stepKey in before step action.
     */
    public function before_step(Step_Event $e): void
    {
        if ($this->silent or !$this->steps or !$e->get_test() instanceof Scenario_Driven) {
            return;
        }
        $step_action = $e->get_step()->get_action();
        // Set atInvisibleSteps flag and return if step is in INVISIBLE_STEP_ACTIONS
        if (in_array($step_action, Action_Object::INVISIBLE_STEP_ACTIONS)) {
            $this->at_invisible_steps = true;
            return;
        }
        // Set back atInvisibleSteps flag
        if ($this->at_invisible_steps && !in_array($step_action, Action_Object::INVISIBLE_STEP_ACTIONS)) {
            $this->at_invisible_steps = false;
        }
        $meta_step = $e->get_step()->get_meta_step();
        if ($meta_step and $this->meta_step !== $meta_step) {
            $this->message(' ' . $meta_step->get_prefix())->style('bold')->append($meta_step->__toString())->writeln();
        }
        $this->meta_step = $meta_step;
        $this->print_step_keys($e->get_step());
    }
    /**
     * If step failed we move back from action group to test scope
     */
    public function after_step(Step_Event $e): void
    {
        // Do usual after step if step is not INVISIBLE_STEP_ACTIONS
        if (!$this->at_invisible_steps) {
            parent::after_step($e);
        }
        if ($e->get_step()->has_failed()) {
            $this->action_group_step_key = null;
            $this->at_invisible_steps = false;
        }
    }
    /**
     * Print output to cli with stepKey.
     *
     * @SuppressWarnings(PHPMD)
     */
    private function print_step_keys(Step $step): void
    {
        if ($step instanceof Comment and $step->__toString() === '') {
            return;
            // don't print empty comments
        }
        $step_key = $this->retrieve_step_key($step);
        $is_action_group = str_contains($step->__toString(), Action_Group_Object::ACTION_GROUP_CONTEXT_START);
        if ($is_action_group) {
            preg_match(Test_Generator::ACTION_GROUP_STEP_KEY_REGEX, $step->__toString(), $matches);
            if (!empty($matches['actionGroupStepKey'])) {
                $this->action_group_step_key = ucfirst($matches['actionGroupStepKey']);
            }
        }
        if (str_contains($step->__toString(), Action_Group_Object::ACTION_GROUP_CONTEXT_END)) {
            $this->action_group_step_key = null;
            return;
        }
        $msg = $this->message();
        if ($this->meta_step || $this->action_group_step_key !== null && !$is_action_group) {
            $msg->append('  ');
        }
        if ($step_key !== null) {
            $msg->append(Output_Formatter::escape('[' . $step_key . '] '));
            $msg->style('bold');
        }
        if (!$this->meta_step) {
            $msg->style('bold');
        }
        $step_string = str_replace([Action_Group_Object::ACTION_GROUP_CONTEXT_START, Action_Group_Object::ACTION_GROUP_CONTEXT_END], '', $step->to_string(1000));
        $msg->append(Output_Formatter::escape($step_string));
        if ($is_action_group) {
            $msg->style('comment');
        }
        if ($this->meta_step || $this->action_group_step_key !== null && !$is_action_group) {
            $msg->style('info');
        }
        $msg->writeln();
    }
    /**
     * Message instance.
     *
     * @return Message
     */
    private function message(string $string = '')
    {
        return $this->message_factory->message($string);
    }
    /**
     * Reading stepKey from file.
     *
     * @return string|null
     */
    private function retrieve_step_key(Step $step): string|array|null
    {
        $step_key = null;
        $step_line = $step->get_line_number();
        $file_path = $step->get_file_path();
        $step_line = $step_line - 1;
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