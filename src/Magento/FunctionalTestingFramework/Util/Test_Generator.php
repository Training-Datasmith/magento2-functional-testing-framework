<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Data_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Persisted_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Filter\Filter_Interface;
use Magento\Functional_Testing_Framework\Suite\Handlers\Suite_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Handlers\Action_Group_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Group_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Hook_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Object;
use Magento\Functional_Testing_Framework\Test\Util\Action_Merge_Util;
use Magento\Functional_Testing_Framework\Test\Util\Action_Object_Extractor;
use Magento\Functional_Testing_Framework\Test\Util\Base_Object_Extractor;
use Magento\Functional_Testing_Framework\Util\Filesystem\Cest_File_Creator_Util;
use Magento\Functional_Testing_Framework\Util\Filesystem\Dir_Setup_Util;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Manifest\Base_Test_Manifest;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Mustache_Engine;
use Mustache_loader_filesystem_Loader;
/**
 * Class TestGenerator
 * @SuppressWarnings(PHPMD)
 */
class Test_Generator
{
    public const ACTION_GROUP_STEP_KEY_REGEX = "/\\[(?<actionGroupStepKey>.*)\\]/";
    public const ACTION_STEP_KEY_REGEX = "/\\/\\/ stepKey: (?<stepKey>.*)/";
    public const REQUIRED_ENTITY_REFERENCE = 'createDataKey';
    public const GENERATED_DIR = '_generated';
    public const DEFAULT_DIR = 'default';
    public const TEST_SCOPE = 'test';
    public const HOOK_SCOPE = 'hook';
    public const SUITE_SCOPE = 'suite';
    public const PRESSKEY_ARRAY_ANCHOR_KEY = '987654321098765432109876543210';
    public const PERSISTED_OBJECT_NOTATION_REGEX = '/\${1,2}[\w.\[\]]+\${1,2}/';
    public const NO_STEPKEY_ACTIONS = ['comment', 'retrieveEntityField', 'getSecret', 'magentoCLI', 'magentoCron', 'generateDate', 'field'];
    public const RULE_ERROR = 'On step with stepKey "%s", only one of the attributes: "%s" can be use for action "%s"';
    public const STEP_KEY_ANNOTATION = ' // stepKey: %s';
    public const CRON_INTERVAL = 60;
    public const ARRAY_WRAP_OPEN = '[';
    public const ARRAY_WRAP_CLOSE = ']';
    /**
     * Array with helpers classes and methods.
     */
    private array $custom_helpers = [];
    /**
     * Actor name for AcceptanceTest
     *
     * @var string
     */
    private $actor = 'I';
    /**
     * Path to the export dir.
     */
    private readonly string $export_directory;
    /**
     * Export dir name.
     *
     * @var string
     */
    private $export_dir_name;
    /**
     * Symfony console output interface.
     */
    private readonly \Symfony\Component\Console\Output\Console_Output $console_output;
    /**
     * Current generation scope.
     */
    private string $current_generation_scope = Test_Generator::TEST_SCOPE;
    /**
     * Test deprecation messages.
     */
    private array $deprecation_messages = [];
    /**
     * Private constructor for Factory
     *
     * @param string  $exportDir
     * @param array   $tests
     * @param boolean $debug
     * @throws TestFrameworkException
     */
    private function __construct(
        $export_dir,
        /**
         * Array of testObjects to be generated
         */
        private $tests,
        /**
         * Debug flag.
         */
        private $debug = false
    )
    {
        $this->export_dir_name = $export_dir ?? self::DEFAULT_DIR;
        $this->export_directory = File_Path_Formatter::format(TESTS_MODULE_PATH) . self::GENERATED_DIR . DIRECTORY_SEPARATOR . $this->export_dir_name;
        $this->console_output = new \Symfony\Component\Console\Output\Console_Output();
    }
    /**
     * Singleton method to retrieve Test Generator
     *
     * @param string  $dir
     * @param array   $tests
     * @param boolean $debug
     */
    public static function get_instance(?string $dir = null, $tests = [], $debug = false): \Magento\Functional_Testing_Framework\Util\Test_Generator
    {
        return new Test_Generator($dir, $tests, $debug);
    }
    /**
     * Returns the absolute path to the test export director for the generator instance.
     *
     * @return string
     */
    public function get_export_dir()
    {
        return $this->export_directory;
    }
    /**
     * Load all Test files as Objects using the Test Object Handler, additionally validates test references being loaded
     * for validity.
     *
     * @return array
     * @throws TestReferenceException
     * @throws TestFrameworkException
     * @throws FastFailException
     */
    private function load_all_test_objects(array $tests_to_ignore)
    {
        if ($this->tests === null || empty($this->tests)) {
            $test_objects = Test_Object_Handler::get_instance()->get_all_objects();
            return array_diff_key($test_objects, $tests_to_ignore);
        }
        // If we have a custom configuration, we need to check the tests passed in to insure that we can generate
        // them in the current context.
        $invalid_test_objects = array_intersect_key($this->tests, $tests_to_ignore);
        if (!empty($invalid_test_objects)) {
            throw new Test_Reference_Exception('Cannot reference test configuration for generation without accompanying suite.', ['tests' => array_keys($invalid_test_objects)]);
        }
        return $this->tests;
    }
    /**
     * Create a single PHP file containing the $cestPhp using the $filename.
     * If the _generated directory doesn't exist it will be created.
     *
     *
     * @throws TestFrameworkException
     */
    private function create_cest_file(string $test_php, string $filename): void
    {
        Cest_File_Creator_Util::get_instance()->create($filename, $this->export_directory, $test_php);
    }
    /**
     * Assemble ALL PHP strings using the assembleAllTestPhp function. Loop over and pass each array item
     * to the createCestFile function.
     *
     * @param BaseTestManifest $testManifest
     * @param array            $testsToIgnore
     * @throws TestFrameworkException
     * @throws XmlException
     * @throws FastFailException
     * @throws TestReferenceException
     */
    public function create_all_test_files(?Base_Test_Manifest $test_manifest = null, ?array $tests_to_ignore = null): void
    {
        if ($this->tests === null) {
            // no-op if the test configuration is null
            return;
        }
        Dir_Setup_Util::create_group_dir($this->export_directory);
        if ($tests_to_ignore === null) {
            $tests_to_ignore = Suite_Object_Handler::get_instance()->get_all_test_references();
        }
        $test_php_array = $this->assemble_all_test_php($test_manifest, $tests_to_ignore);
        foreach ($test_php_array as $test_php_file) {
            $this->create_cest_file($test_php_file[1], $test_php_file[0]);
        }
    }
    /**
     * Throw exception if duplicate arguments found
     * @param TestObject $testObject
     * @throws TestFrameworkException
     */
    public function throw_exception_if_duplicate_arguments_found($test_object): void
    {
        if (!$test_object instanceof Test_Object) {
            return;
        }
        $file_name = $test_object->get_filename();
        if (!empty($file_name) && file_exists($file_name)) {
            return;
        }
        $file_contents = file_get_contents($file_name);
        $parsed_steps = $test_object->get_unresolved_steps();
        foreach ($parsed_steps as $parsed_step) {
            if ($parsed_step->get_type() !== 'actionGroup' && $parsed_step->get_type() !== 'helper') {
                continue;
            }
            $attributes_actions = $parsed_step->get_custom_action_attributes();
            if (!key_exists('arguments', $attributes_actions)) {
                continue;
            }
            $arguments = $attributes_actions['arguments'];
            $step_key = $parsed_step->get_step_key();
            $file_to_arr = explode("\n", $file_contents);
            $action_group_start = false;
            $argument_array = [];
            foreach ($file_to_arr as $file_val) {
                $file_val = trim($file_val);
                if ((str_contains($file_val, '<actionGroup') || str_contains($file_val, '<helper')) && str_contains($file_val, (string) $step_key)) {
                    $action_group_start = true;
                    continue;
                }
                if (str_contains($file_val, '</actionGroup') || str_contains($file_val, '</helper')) {
                    foreach ($arguments as $argument_name => $argument_value) {
                        $argument_counter = 0;
                        foreach ($argument_array as $raw_argument) {
                            if (str_contains($raw_argument, '<argument') && str_contains($raw_argument, 'name="' . $argument_name . '"')) {
                                $argument_counter++;
                            }
                            if ($argument_counter > 1) {
                                $err[] = sprintf('Duplicate argument(%s) for stepKey: %s in test file: %s', $argument_name, $step_key, $test_object->get_file_name());
                                throw new Test_Framework_Exception(implode(PHP_EOL, $err));
                            }
                        }
                        $action_group_start = false;
                        $argument_array = [];
                    }
                }
                if ($action_group_start) {
                    $argument_array[] = $file_val;
                }
            }
        }
    }
    /**
     * Assemble the entire PHP string for a single Test based on a Test Object.
     * Create all of the PHP strings for a Test. Concatenate the strings together.
     *
     * @param \Magento\FunctionalTestingFramework\Test\Objects\TestObject $testObject
     * @throws TestReferenceException
     * @throws \Exception
     */
    public function assemble_test_php($test_object): string
    {
        if (!empty($test_object->get_filename()) && file_exists($test_object->get_filename())) {
            $file_contents = file_get_contents($test_object->get_filename());
            $this->throw_exception_if_duplicate_arguments_found($file_contents);
        }
        $this->custom_helpers = [];
        $use_php = $this->generate_use_statements_php();
        $class_name = $test_object->get_codeception_name();
        try {
            if (!$test_object->is_skipped() || Mftf_Application_Config::get_config()->allow_skipped()) {
                $hook_php = $this->generate_hooks_php($test_object->get_hooks());
            } else {
                $hook_php = null;
            }
            $tests_php = $this->generate_test_php($test_object);
        } catch (Test_Reference_Exception $e) {
            throw new Test_Reference_Exception($e->get_message() . "\n" . $test_object->get_filename());
        }
        $class_annotations_php = $this->generate_annotations_php($test_object);
        $cest_php = "<?php\n";
        $cest_php .= "namespace Magento\\AcceptanceTest\\_" . $this->export_dir_name . "\\Backend;\n\n";
        $cest_php .= $use_php;
        $cest_php .= $class_annotations_php;
        $cest_php .= sprintf("class %s\n", $class_name);
        $cest_php .= "{\n";
        $cest_php .= "\t/**\n";
        $cest_php .= "\t * @var bool\n";
        $cest_php .= "\t */\n";
        $cest_php .= "\tprivate \$isSuccess = false;\n\n";
        $cest_php .= $this->generate_inject_method();
        $cest_php .= $hook_php;
        $cest_php .= $tests_php;
        return $cest_php . "}\n";
    }
    /**
     * Generates _injectMethod based on $this->customHelpers.
     *
     * @return string
     */
    private function generate_inject_method()
    {
        if (empty($this->custom_helpers)) {
            return '';
        }
        $mustache_engine = new Mustache_Engine(['loader' => new Mustache_loader_filesystem_Loader(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Helper' . DIRECTORY_SEPARATOR . 'views')]);
        $arguments_with_type = [];
        $arguments = [];
        foreach ($this->custom_helpers as $custom_helper_var => $custom_helper_type) {
            $arguments_with_type[] = $custom_helper_type . ' ' . $custom_helper_var;
            $arguments[] = ['type' => $custom_helper_type, 'var' => $custom_helper_var];
        }
        $mustache_data['argumentsWithTypes'] = implode(', ' . PHP_EOL, $arguments_with_type);
        $mustache_data['arguments'] = $arguments;
        return $mustache_engine->render('TestInjectMethod', $mustache_data);
    }
    /**
     * Load ALL Test objects. Loop over and pass each to the assembleTestPhp function.
     *
     * @param BaseTestManifest $testManifest
     * @throws TestFrameworkException
     * @throws TestReferenceException
     * @throws FastFailException
     */
    private function assemble_all_test_php(?\Magento\Functional_Testing_Framework\Util\Manifest\Base_Test_Manifest $test_manifest, array $tests_to_ignore): array
    {
        /** @var TestObject[] $testObjects */
        $test_objects = $this->load_all_test_objects($tests_to_ignore);
        $cest_php_array = [];
        $filters = Mftf_Application_Config::get_config()->get_filter_list()->get_filters();
        /** @var FilterInterface $filter */
        foreach ($filters as $filter) {
            $filter->filter($test_objects);
        }
        foreach ($test_objects as $test) {
            try {
                // Reset flag for new test
                $remove_last_test = false;
                // Do not generate test if it is an extended test and parent does not exist
                if ($test->is_skipped() && !empty($test->get_parent_name())) {
                    try {
                        Test_Object_Handler::get_instance()->get_object($test->get_parent_name());
                    } catch (Test_Reference_Exception) {
                        Test_Object_Handler::get_instance()->sanitize_tests([$test->get_name()]);
                        $err_message = "{$test->get_name()} will not be generated. " . "Parent test {$test->get_parent_name()} not defined in xml.";
                        // There are tests extend from non-existing parent on purpose on certain Magento editions.
                        // To keep backward compatibility, we will skip the test and continue
                        if (Mftf_Application_Config::get_config()->verbose_enabled()) {
                            print "NOTICE: {$err_message}";
                        }
                        Logging_Util::get_instance()->get_logger(self::class)->warning($err_message);
                        continue;
                    }
                }
                $this->debug('<comment>Start creating test: ' . $test->get_codeception_name() . '</comment>');
                $php = $this->assemble_test_php($test);
                $cest_php_array[] = [$test->get_codeception_name(), $php];
                // Set flag in case something goes wrong
                $remove_last_test = true;
                $debug_information = $test->get_debug_information();
                $this->debug($debug_information);
                $this->debug('<comment>Finish creating test: ' . $test->get_codeception_name() . '</comment>' . PHP_EOL);
                // Write to manifest here if manifest is not null
                if ($test_manifest !== null) {
                    $test_manifest->add_test($test);
                }
            } catch (Fast_Fail_Exception $e) {
                throw $e;
            } catch (\Exception $e) {
                Generation_Error_Handler::get_instance()->add_error('test', $test->get_name(), self::class . ': ' . $e->get_message());
                Logging_Util::get_instance()->get_logger(self::class)->error("Failed to generate {$test->get_name()}");
                if ($remove_last_test) {
                    array_pop($cest_php_array);
                }
                Test_Object_Handler::get_instance()->sanitize_tests([$test->get_name()]);
            }
        }
        return $cest_php_array;
    }
    /**
     * Output information in console when debug flag is enabled.
     *
     * @param array|string $messages
     */
    private function debug($messages): void
    {
        if ($this->debug && $messages) {
            $messages = (array) $messages;
            foreach ($messages as $message) {
                $this->console_output->writeln($message);
            }
        }
    }
    /**
     * Creates a PHP string for the necessary Allure and AcceptanceTester use statements.
     * Since we don't support other dependencies at this time, this function takes no parameter.
     */
    private function generate_use_statements_php(): string
    {
        $use_statements_php = "use Magento\\FunctionalTestingFramework\\AcceptanceTester;\n";
        $use_statements_php .= "use \\Codeception\\Util\\Locator;\n";
        $allure_statements = ["Yandex\\Allure\\Adapter\\Annotation\\Features;", "Yandex\\Allure\\Adapter\\Annotation\\Stories;", "Yandex\\Allure\\Adapter\\Annotation\\Title;", "Yandex\\Allure\\Adapter\\Annotation\\Description;", "Yandex\\Allure\\Adapter\\Annotation\\Parameter;", "Yandex\\Allure\\Adapter\\Annotation\\Severity;", "Yandex\\Allure\\Adapter\\Model\\SeverityLevel;", "Yandex\\Allure\\Adapter\\Annotation\\TestCaseId;\n"];
        foreach ($allure_statements as $allure_use_statement) {
            $use_statements_php .= sprintf("use %s\n", $allure_use_statement);
        }
        return $use_statements_php;
    }
    /**
     * Generates Annotations PHP for given object, using given scope to determine indentation and additional output.
     *
     * @param array   $testObject
     */
    private function generate_annotations_php($test_object, bool $is_method = false): string
    {
        $annotations_object = $test_object->get_annotations();
        //TODO: Refactor to deal with PHPMD.CyclomaticComplexity
        if ($is_method) {
            $indent = "\t";
        } else {
            $indent = '';
        }
        $annotations_php = "{$indent}/**\n";
        foreach ($annotations_object as $annotation_type => $annotation_name) {
            //Remove conditional and output useCaseId upon completion of MQE-588
            if ($annotation_type === 'useCaseId') {
                continue;
            }
            if (!$is_method) {
                $annotations_php .= $this->generate_class_annotations($annotation_type, $annotation_name, $test_object);
            } else {
                $annotations_php .= $this->generate_method_annotations($annotation_type, $annotation_name);
            }
        }
        if ($is_method) {
            $annotations_php .= $this->generate_method_annotations();
        }
        return $annotations_php . "{$indent} */\n";
    }
    /**
     * Method which returns formatted method level annotation based on type and name(s).
     *
     * @param string      $annotationType
     * @param string|null $annotationName
     */
    private function generate_method_annotations(?string $annotation_type = null, mixed $annotation_name = null): ?string
    {
        $annotation_to_append = null;
        $indent = "\t";
        switch ($annotation_type) {
            case 'features':
                $features = '';
                foreach ($annotation_name as $name) {
                    $features .= sprintf('"%s"', $name);
                    if (next($annotation_name)) {
                        $features .= ', ';
                    }
                }
                $annotation_to_append .= sprintf("{$indent} * @Features({%s})\n", $features);
                break;
            case 'stories':
                $stories = '';
                foreach ($annotation_name as $name) {
                    $stories .= sprintf('"%s"', $name);
                    if (next($annotation_name)) {
                        $stories .= ', ';
                    }
                }
                $annotation_to_append .= sprintf("{$indent} * @Stories({%s})\n", $stories);
                break;
            case 'severity':
                $annotation_to_append = sprintf("{$indent} * @Severity(level = SeverityLevel::%s)\n", $annotation_name[0]);
                break;
            case null:
                $annotation_to_append = '';
                $annotation_to_append .= sprintf("{$indent} * @param %s \$%s\n", 'AcceptanceTester', 'I');
                $annotation_to_append .= "{$indent} * @return void\n";
                $annotation_to_append .= "{$indent} * @throws \\Exception\n";
                break;
        }
        return $annotation_to_append;
    }
    /**
     * Returs required credentials to configure
     *
     * @param TestObject $testObject
     */
    public function required_credentials($test_object): string
    {
        return !empty($test_object->get_credentials()) ? implode(',', $test_object->get_credentials()) : '';
    }
    /**
     * Method which return formatted class level annotations based on type and name(s).
     *
     * @param string $annotationType
     * @param array  $testObject
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function generate_class_annotations($annotation_type, array $annotation_name, $test_object): ?string
    {
        $annotation_to_append = null;
        if (!$test_object->is_skipped() && !empty($annotation_name['main'])) {
            $required_credentials_message = $this->required_credentials($test_object);
            $cred_msg = "\n\n" . 'This test uses the following credentials:' . "\n";
            $annotation_name = !empty($required_credentials_message) ? ['main' => $annotation_name['main'] . ', ' . $cred_msg . '' . $required_credentials_message, 'test_files' => "\n" . $annotation_name['test_files'], 'deprecated' => $annotation_name['deprecated']] : $annotation_name;
        }
        switch ($annotation_type) {
            case 'title':
                $annotation_to_append = sprintf(" * @Title(\"%s\")\n", $annotation_name[0]);
                break;
            case 'description':
                $template = " * @Description(\"%s\")\n";
                $annotation_to_append = sprintf($template, $this->generate_description_annotation($annotation_name));
                break;
            case 'testCaseId':
                $annotation_to_append = sprintf(" * @TestCaseId(\"%s\")\n", $annotation_name[0]);
                break;
            case 'useCaseId':
                $annotation_to_append = sprintf(" * @UseCaseId(\"%s\")\n", $annotation_name[0]);
                break;
            case 'group':
                foreach ($annotation_name as $group) {
                    $annotation_to_append .= sprintf(" * @group %s\n", $group);
                }
                break;
        }
        return $annotation_to_append;
    }
    /**
     * Generates Description
     */
    private function generate_description_annotation(array $descriptions): string
    {
        $description_text = '';
        $description_text .= $descriptions['main'] ?? '';
        if (!empty($descriptions[Base_Object_Extractor::OBJ_DEPRECATED]) || !empty($this->deprecation_messages)) {
            $deprecated_messages = array_merge($descriptions[Base_Object_Extractor::OBJ_DEPRECATED], $this->deprecation_messages);
            $description_text .= "<h3 class='y-label y-label_status_broken'>Deprecated Notice(s):</h3>";
            $description_text .= '<ul>';
            foreach ($deprecated_messages as $deprecated_message) {
                $description_text .= '<li>' . $deprecated_message . '</li>';
            }
            $description_text .= '</ul>';
        }
        return $description_text . $descriptions['test_files'];
    }
    /**
     * Creates a PHP string for the actions contained withing a <test> block.
     * Since nearly half of all Codeception methods don't share the same signature I had to setup a massive Case
     * statement to handle each unique action. At the bottom of the case statement there is a generic function that can
     * construct the PHP string for nearly half of all Codeception actions.
     *
     * @param array  $actionObjects
     * @param string $actor
     * @throws TestReferenceException
     * @throws \Exception
     * @SuppressWarnings(PHPMD)
     */
    public function generate_steps_php($action_objects, string $generation_scope = Test_Generator::TEST_SCOPE, $actor = 'I'): string
    {
        //TODO: Refactor Method according to PHPMD warnings, remove @SuppressWarnings accordingly.
        $test_steps = '';
        $this->actor = $actor;
        $this->current_generation_scope = $generation_scope;
        $this->deprecation_messages = [];
        foreach ($action_objects as $action_object) {
            $this->deprecation_messages = array_merge($this->deprecation_messages, $action_object->get_deprecated_usages());
            $step_key = $action_object->get_step_key();
            $custom_action_attributes = $action_object->get_custom_action_attributes();
            $attribute = null;
            $selector = null;
            $selector1 = null;
            $selector2 = null;
            $input = null;
            $parameter_array = null;
            $return_variable = null;
            $x = null;
            $y = null;
            $html = null;
            $url = null;
            $function = null;
            $time = null;
            $locale = null;
            $currency = null;
            $username = null;
            $password = null;
            $width = null;
            $height = null;
            $required_action = null;
            $value = null;
            $button = null;
            $parameter = null;
            $dependent_selector = null;
            $visible = null;
            $command = null;
            $cron_groups = '';
            $arguments = null;
            $sort_order = null;
            $store_code = null;
            $format = null;
            $assert_expected = null;
            $assert_actual = null;
            $assert_message = null;
            $assert_is_strict = null;
            $assert_delta = null;
            // Validate action attributes and print notice messages on violation.
            $this->validate_xml_attributes_mutually_exclusive($step_key, $action_object->get_type(), $custom_action_attributes);
            if (isset($custom_action_attributes['command'])) {
                $command = $this->add_uniqueness_function_call($custom_action_attributes['command']);
            }
            if (isset($custom_action_attributes['groups'])) {
                $cron_groups = $this->add_uniqueness_function_call($custom_action_attributes['groups']);
            }
            if (isset($custom_action_attributes['arguments'])) {
                $arguments = $this->add_uniqueness_function_call($custom_action_attributes['arguments']);
            }
            if (isset($custom_action_attributes['attribute'])) {
                $attribute = $custom_action_attributes['attribute'];
            }
            if (isset($custom_action_attributes['sortOrder'])) {
                $sort_order = $custom_action_attributes['sortOrder'];
            }
            if (isset($custom_action_attributes['userInput']) && isset($custom_action_attributes['locale']) && isset($custom_action_attributes['currency'])) {
                $input = $this->parse_user_input($custom_action_attributes['userInput']);
            } elseif (isset($custom_action_attributes['userInput']) && isset($custom_action_attributes['url'])) {
                $input = $this->add_uniqueness_function_call($custom_action_attributes['userInput']);
                $url = $this->add_uniqueness_function_call($custom_action_attributes['url']);
            } elseif (isset($custom_action_attributes['userInput'])) {
                $input = $this->add_uniqueness_function_call($custom_action_attributes['userInput']);
            } elseif (isset($custom_action_attributes['url'])) {
                $input = $this->add_uniqueness_function_call($custom_action_attributes['url']);
                $url = $this->add_uniqueness_function_call($custom_action_attributes['url']);
            } elseif (isset($custom_action_attributes['regex'])) {
                $input = $this->add_uniqueness_function_call($custom_action_attributes['regex']);
            }
            if (isset($custom_action_attributes['date']) && isset($custom_action_attributes['format'])) {
                $input = $this->add_uniqueness_function_call($custom_action_attributes['date']);
                if ($input === '') {
                    $input = '"Now"';
                }
                $format = $this->add_uniqueness_function_call($custom_action_attributes['format']);
                if ($format === '') {
                    $format = '"r"';
                }
            }
            if (isset($custom_action_attributes['expected'])) {
                $assert_expected = $this->resolve_value_by_type($custom_action_attributes['expected'], $custom_action_attributes['expectedType'] ?? null);
            }
            if (isset($custom_action_attributes['actual'])) {
                $assert_actual = $this->resolve_value_by_type($custom_action_attributes['actual'], $custom_action_attributes['actualType'] ?? null);
            }
            if (isset($custom_action_attributes['message'])) {
                $assert_message = $this->add_uniqueness_function_call($custom_action_attributes['message']);
            }
            if (isset($custom_action_attributes['delta'])) {
                $assert_delta = $this->resolve_value_by_type($custom_action_attributes['delta'], 'float');
            }
            if (isset($custom_action_attributes['strict'])) {
                $assert_is_strict = $this->resolve_value_by_type($custom_action_attributes['strict'], 'bool');
            }
            if (isset($custom_action_attributes['time'])) {
                $time = $custom_action_attributes['time'];
            }
            if (isset($custom_action_attributes['timeout'])) {
                $time = $custom_action_attributes['timeout'];
            }
            if (in_array($action_object->get_type(), Action_Object::COMMAND_ACTION_ATTRIBUTES)) {
                $time ??= Action_Object::get_default_magento_cli_wait_timeout();
            } else {
                $time ??= Action_Object::get_default_wait_timeout();
            }
            if (isset($custom_action_attributes['parameterArray']) && $action_object->get_type() !== 'pressKey') {
                // validate the param array is in the correct format
                $this->validate_parameter_array($custom_action_attributes['parameterArray']);
                $parameter_array = $this->wrap_parameter_array($this->add_uniqueness_to_param_array($custom_action_attributes['parameterArray']));
            }
            if (isset($custom_action_attributes['requiredAction'])) {
                $required_action = $custom_action_attributes['requiredAction'];
            }
            if (isset($custom_action_attributes['selectorArray'])) {
                $selector = $custom_action_attributes['selectorArray'];
            } elseif (isset($custom_action_attributes['selector'])) {
                $selector = $this->add_uniqueness_function_call($custom_action_attributes['selector']);
                $selector = $this->resolve_locator_function_in_attribute($selector);
            }
            if (isset($custom_action_attributes['count'])) {
                $count_click_value = $custom_action_attributes['count'];
                $count_value = $this->add_uniqueness_function_call($count_click_value);
                $count_value = $this->resolve_locator_function_in_attribute($count_value);
            }
            if (isset($custom_action_attributes['selector1']) || isset($custom_action_attributes['filterSelector'])) {
                $selector_one_value = $custom_action_attributes['selector1'] ?? $custom_action_attributes['filterSelector'];
                $selector1 = $this->add_uniqueness_function_call($selector_one_value);
                $selector1 = $this->resolve_locator_function_in_attribute($selector1);
            }
            if (isset($custom_action_attributes['selector2']) || isset($custom_action_attributes['optionSelector'])) {
                $selector_two_value = $custom_action_attributes['selector2'] ?? $custom_action_attributes['optionSelector'];
                $selector2 = $this->add_uniqueness_function_call($selector_two_value);
                $selector2 = $this->resolve_locator_function_in_attribute($selector2);
            }
            if (isset($custom_action_attributes['x'])) {
                $x = $custom_action_attributes['x'];
            }
            if (isset($custom_action_attributes['y'])) {
                $y = $custom_action_attributes['y'];
            }
            if (isset($custom_action_attributes['function'])) {
                $function = $this->add_uniqueness_function_call($custom_action_attributes['function']);
                if (in_array($action_object->get_type(), Action_Object::FUNCTION_CLOSURE_ACTIONS)) {
                    // Argument must be a closure function, not a string.
                    $function = trim($function, '"');
                }
                // turn $javaVariable => \$javaVariable but not {$mftfVariable}
                if ($action_object->get_type() === 'executeJS') {
                    $function = preg_replace('/(?<!{)(\$[A-Za-z._]+)(?![A-z.]*+\$)/', '\\\\$1', $function);
                }
            }
            if (isset($custom_action_attributes['html'])) {
                $html = $this->add_uniqueness_function_call($custom_action_attributes['html']);
            }
            if (isset($custom_action_attributes['locale'])) {
                $locale = $this->wrap_with_double_quotes($custom_action_attributes['locale']);
            }
            if (isset($custom_action_attributes['currency'])) {
                $currency = $this->wrap_with_double_quotes($custom_action_attributes['currency']);
            }
            if (isset($custom_action_attributes['username'])) {
                $username = $this->wrap_with_double_quotes($custom_action_attributes['username']);
            }
            if (isset($custom_action_attributes['password'])) {
                $password = $this->wrap_with_double_quotes($custom_action_attributes['password']);
            }
            if (isset($custom_action_attributes['width'])) {
                $width = $custom_action_attributes['width'];
            }
            if (isset($custom_action_attributes['height'])) {
                $height = $custom_action_attributes['height'];
            }
            if (isset($custom_action_attributes['value'])) {
                $value = $this->wrap_with_double_quotes($custom_action_attributes['value']);
            }
            if (isset($custom_action_attributes['button'])) {
                $button = $this->wrap_with_double_quotes($custom_action_attributes['button']);
            }
            if (isset($custom_action_attributes['parameter'])) {
                $parameter = $this->wrap_with_double_quotes($custom_action_attributes['parameter']);
            }
            if (isset($custom_action_attributes['dependentSelector'])) {
                $dependent_selector = $this->add_uniqueness_function_call($custom_action_attributes['dependentSelector']);
            }
            if (isset($custom_action_attributes['visible'])) {
                $visible = $custom_action_attributes['visible'];
            }
            if (isset($custom_action_attributes['storeCode'])) {
                $store_code = $custom_action_attributes['storeCode'];
            }
            switch ($action_object->get_type()) {
                case 'helper':
                    if (!in_array($custom_action_attributes['class'], $this->custom_helpers)) {
                        $this->custom_helpers['$' . $step_key] = $custom_action_attributes['class'];
                    }
                    $arguments = [];
                    $class_reader = new \Magento\Functional_Testing_Framework\Helper\Code\Class_Reader();
                    $parameters = $class_reader->get_parameters($custom_action_attributes['class'], $custom_action_attributes['method']);
                    $errors = [];
                    foreach ($parameters as $parameter) {
                        if (array_key_exists($parameter['variableName'], $custom_action_attributes)) {
                            $value = $custom_action_attributes[$parameter['variableName']];
                            $arguments[] = $this->add_uniqueness_function_call($value, $parameter['type'] === 'string' || $parameter['type'] === null);
                        } elseif ($parameter['isOptional']) {
                            $value = $parameter['optionalValue'];
                            $arguments[] = str_replace(PHP_EOL, '', var_export($value, true));
                        } else {
                            $errors[] = 'Argument \'' . $parameter['variableName'] . '\' for method ' . $custom_action_attributes['class'] . '::' . $custom_action_attributes['method'] . ' is not found.';
                        }
                    }
                    if (!empty($errors)) {
                        throw new Test_Framework_Exception(implode(PHP_EOL, $errors));
                    }
                    $test_steps .= sprintf("\t\t\$%s->comment('[%s] %s()');" . PHP_EOL, $actor, $step_key, $custom_action_attributes['class'] . '::' . $custom_action_attributes['method']);
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $arguments);
                    break;
                case 'createData':
                    $entity = $custom_action_attributes['entity'];
                    $this->entity_exists_check($entity, $step_key);
                    //TODO refactor entity field override to not be individual actionObjects
                    $custom_entity_fields = $custom_action_attributes[Action_Object_Extractor::ACTION_OBJECT_PERSISTENCE_FIELDS] ?? [];
                    $required_entity_keys = [];
                    foreach ($action_object->get_custom_action_attributes() as $action_attribute) {
                        if (is_array($action_attribute) && $action_attribute['nodeName'] === 'requiredEntity') {
                            //append ActionGroup if provided
                            $required_entity_action_group = $action_attribute['actionGroup'] ?? null;
                            $required_entity_keys[] = $action_attribute['createDataKey'] . $required_entity_action_group;
                        }
                    }
                    // Build array of requiredEntities
                    $required_entity_keys_array = '';
                    if (!empty($required_entity_keys)) {
                        $required_entity_keys_array = '"' . implode('", "', $required_entity_keys) . '"';
                    }
                    $scope = $this->get_object_scope($generation_scope);
                    $create_entity_function_call = "\t\t\${$actor}->createEntity(";
                    $create_entity_function_call .= "\"{$step_key}\",";
                    $create_entity_function_call .= " \"{$scope}\",";
                    $create_entity_function_call .= " \"{$entity}\",";
                    $create_entity_function_call .= " [{$required_entity_keys_array}],";
                    if (count($custom_entity_fields) > 1) {
                        $create_entity_function_call .= " \${$step_key}Fields";
                    } else {
                        $create_entity_function_call .= ' []';
                    }
                    if ($store_code !== null) {
                        $create_entity_function_call .= ", \"{$store_code}\"";
                    }
                    $create_entity_function_call .= ');';
                    $test_steps .= $create_entity_function_call;
                    break;
                case 'deleteData':
                    if (isset($custom_action_attributes['createDataKey'])) {
                        $key = $this->resolve_step_key_references($custom_action_attributes['createDataKey'], $action_object->get_action_origin(), true);
                        $action_group = $action_object->get_custom_action_attributes()['actionGroup'] ?? null;
                        $key .= $action_group;
                        $scope = $this->get_object_scope($generation_scope);
                        $delete_entity_function_call = "\t\t\${$actor}->deleteEntity(";
                        $delete_entity_function_call .= "\"{$key}\",";
                        $delete_entity_function_call .= " \"{$scope}\"";
                        $delete_entity_function_call .= ');';
                        $test_steps .= $delete_entity_function_call;
                    } else {
                        $url = $this->resolve_all_runtime_references([$url])[0];
                        $url = $this->resolve_test_variable([$url], null)[0];
                        $output = sprintf("\t\t\$%s->deleteEntityByUrl(%s);", $actor, $url);
                        $test_steps .= $output;
                    }
                    break;
                case 'updateData':
                    $key = $this->resolve_step_key_references($custom_action_attributes['createDataKey'], $action_object->get_action_origin(), true);
                    $update_entity = $custom_action_attributes['entity'];
                    $action_group = $action_object->get_custom_action_attributes()['actionGroup'] ?? null;
                    $key .= $action_group;
                    // Build array of requiredEntities
                    $required_entity_keys = [];
                    foreach ($action_object->get_custom_action_attributes() as $action_attribute) {
                        if (is_array($action_attribute) && $action_attribute['nodeName'] === 'requiredEntity') {
                            //append ActionGroup if provided
                            $required_entity_action_group = $action_attribute['actionGroup'] ?? null;
                            $required_entity_keys[] = $action_attribute['createDataKey'] . $required_entity_action_group;
                        }
                    }
                    $required_entity_keys_array = '';
                    if (!empty($required_entity_keys)) {
                        $required_entity_keys_array = '"' . implode('", "', $required_entity_keys) . '"';
                    }
                    $scope = $this->get_object_scope($generation_scope);
                    $update_entity_function_call = "\t\t\${$actor}->updateEntity(";
                    $update_entity_function_call .= "\"{$key}\",";
                    $update_entity_function_call .= " \"{$scope}\",";
                    $update_entity_function_call .= " \"{$update_entity}\",";
                    $update_entity_function_call .= "[{$required_entity_keys_array}]";
                    if ($store_code !== null) {
                        $update_entity_function_call .= ", \"{$store_code}\"";
                    }
                    $update_entity_function_call .= ');';
                    $test_steps .= $update_entity_function_call;
                    break;
                case 'getData':
                    $entity = $custom_action_attributes['entity'];
                    $index = null;
                    if (isset($custom_action_attributes['index'])) {
                        $index = (int) $custom_action_attributes['index'];
                    }
                    // Build array of requiredEntities
                    $required_entity_keys = [];
                    foreach ($action_object->get_custom_action_attributes() as $action_attribute) {
                        if (is_array($action_attribute) && $action_attribute['nodeName'] === 'requiredEntity') {
                            $required_entity_action_group = $action_attribute['actionGroup'] ?? null;
                            $required_entity_keys[] = $action_attribute['createDataKey'] . $required_entity_action_group;
                        }
                    }
                    $required_entity_keys_array = '';
                    if (!empty($required_entity_keys)) {
                        $required_entity_keys_array = '"' . implode('", "', $required_entity_keys) . '"';
                    }
                    $scope = $this->get_object_scope($generation_scope);
                    //Create Function
                    $get_entity_function_call = "\t\t\${$actor}->getEntity(";
                    $get_entity_function_call .= "\"{$step_key}\",";
                    $get_entity_function_call .= " \"{$scope}\",";
                    $get_entity_function_call .= " \"{$entity}\",";
                    $get_entity_function_call .= " [{$required_entity_keys_array}],";
                    if ($store_code !== null) {
                        $get_entity_function_call .= " \"{$store_code}\"";
                    } else {
                        $get_entity_function_call .= ' null';
                    }
                    if ($index !== null) {
                        $get_entity_function_call .= ", {$index}";
                    }
                    $get_entity_function_call .= ');';
                    $test_steps .= $get_entity_function_call;
                    break;
                case 'assertArrayIsSorted':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $parameter_array, $this->wrap_with_double_quotes($sort_order));
                    break;
                case 'seeCurrentUrlEquals':
                case 'seeCurrentUrlMatches':
                case 'dontSeeCurrentUrlEquals':
                case 'dontSeeCurrentUrlMatches':
                case 'seeInPopup':
                case 'saveSessionSnapshot':
                case 'seeInTitle':
                case 'seeInCurrentUrl':
                case 'switchToIFrame':
                case 'switchToWindow':
                case 'typeInPopup':
                case 'dontSee':
                case 'see':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $input, $selector);
                    break;
                case 'switchToNextTab':
                case 'switchToPreviousTab':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $input);
                    break;
                case 'clickWithLeftButton':
                case 'clickWithRightButton':
                case 'moveMouseOver':
                case 'scrollTo':
                    if (!$selector) {
                        $selector = 'null';
                    }
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $x, $y);
                    break;
                case 'dontSeeCookie':
                case 'resetCookie':
                case 'seeCookie':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $input, $parameter_array);
                    break;
                case 'grabCookieAttributes':
                case 'grabCookie':
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $input, $parameter_array);
                    break;
                case 'dontSeeElement':
                case 'dontSeeElementInDOM':
                case 'dontSeeInFormFields':
                case 'seeElement':
                case 'seeElementInDOM':
                case 'seeInFormFields':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $parameter_array);
                    break;
                case 'pressKey':
                    $parameter_array = $custom_action_attributes['parameterArray'] ?? null;
                    if ($parameter_array) {
                        $parameter_array = $this->process_press_key($parameter_array);
                    }
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $input, $parameter_array);
                    break;
                case 'selectOption':
                case 'unselectOption':
                case 'seeNumberOfElements':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $input, $parameter_array);
                    break;
                case 'submitForm':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $parameter_array, $button);
                    break;
                case 'dragAndDrop':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector1, $selector2, $x, $y);
                    break;
                case 'rapidClick':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $count_value);
                    break;
                case 'selectMultipleOptions':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector1, $selector2, $input, $parameter_array);
                    break;
                case 'executeJS':
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $function);
                    break;
                case 'waitForElementChange':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $function, $time);
                    break;
                case 'waitForJS':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $function, $time);
                    break;
                case 'wait':
                case 'waitForAjaxLoad':
                case 'waitForElement':
                case 'waitForElementVisible':
                case 'waitForElementNotVisible':
                case 'waitForPwaElementVisible':
                case 'waitForPwaElementNotVisible':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $time);
                    break;
                case 'waitForPageLoad':
                case 'waitForText':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $input, $time, $selector);
                    break;
                case 'return':
                    $action_origin = $action_object->get_action_origin();
                    $action_origin_step_key = $action_origin[Action_Group_Object::ACTION_GROUP_ORIGIN_TEST_REF];
                    $test_steps .= $this->wrap_function_call_with_return_value($action_origin_step_key, $actor, $action_object, $value);
                    break;
                case 'formatCurrency':
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $input, $locale, $currency);
                    break;
                case 'mSetLocale':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $input, $locale);
                    break;
                case 'grabAttributeFrom':
                case 'grabMultiple':
                case 'grabFromCurrentUrl':
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $selector, $input);
                    break;
                case 'grabTextFrom':
                case 'grabValueFrom':
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $selector);
                    break;
                case 'grabPageSource':
                case 'getOTP':
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $input);
                    break;
                case 'resizeWindow':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $width, $height);
                    break;
                case 'searchAndMultiSelectOption':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $input, $parameter_array, $required_action);
                    break;
                case 'seeLink':
                case 'dontSeeLink':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $input, $url);
                    break;
                case 'setCookie':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $input, $value, $parameter_array);
                    break;
                case 'amOnPage':
                case 'amOnSubdomain':
                case 'amOnUrl':
                case 'appendField':
                case 'attachFile':
                case 'click':
                case 'dontSeeInField':
                case 'dontSeeInCurrentUrl':
                case 'dontSeeInTitle':
                case 'dontSeeOptionIsSelected':
                case 'fillField':
                case 'loadSessionSnapshot':
                case 'seeInField':
                case 'seeOptionIsSelected':
                case 'seeInSecretField':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $input);
                    break;
                case 'seeInPageSource':
                case 'dontSeeInPageSource':
                case 'seeInSource':
                case 'dontSeeInSource':
                    //TODO: Deprecate allowed usage of userInput in dontSeeInPageSource
                    if ($html === null && $input !== null) {
                        $html = $input;
                    }
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $html);
                    break;
                case 'conditionalClick':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $dependent_selector, $visible);
                    break;
                case 'assertGreaterOrEquals':
                case 'assertGreaterThan':
                case 'assertGreaterThanOrEqual':
                case 'assertLessOrEquals':
                case 'assertLessThan':
                case 'assertLessThanOrEqual':
                case 'assertInstanceOf':
                case 'assertNotInstanceOf':
                case 'assertNotRegExp':
                case 'assertNotSame':
                case 'assertRegExp':
                case 'assertSame':
                case 'assertStringStartsNotWith':
                case 'assertStringStartsWith':
                case 'assertArrayHasKey':
                case 'assertArrayNotHasKey':
                case 'assertCount':
                case 'assertContains':
                case 'assertNotContains':
                case 'assertStringContainsString':
                case 'assertStringContainsStringIgnoringCase':
                case 'assertStringNotContainsString':
                case 'assertStringNotContainsStringIgnoringCase':
                case 'expectException':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $assert_expected, $assert_actual, $assert_message, $assert_delta);
                    break;
                case 'assertEquals':
                case 'assertNotEquals':
                case 'assertEqualsIgnoringCase':
                case 'assertNotEqualsIgnoringCase':
                case 'assertEqualsCanonicalizing':
                case 'assertNotEqualsCanonicalizing':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $assert_expected, $assert_actual, $assert_message);
                    break;
                case 'assertEqualsWithDelta':
                case 'assertNotEqualsWithDelta':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $assert_expected, $assert_actual, $assert_delta, $assert_message);
                    break;
                case 'assertElementContainsAttribute':
                    // If a blank string or null is passed in we need to pass a blank string to the function.
                    if (empty($assert_expected)) {
                        $assert_expected = '""';
                    }
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $this->wrap_with_double_quotes($attribute), $assert_expected);
                    break;
                case 'assertEmpty':
                case 'assertFalse':
                case 'assertFileExists':
                case 'assertFileNotExists':
                case 'assertIsEmpty':
                case 'assertNotEmpty':
                case 'assertNotNull':
                case 'assertNull':
                case 'assertTrue':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $assert_actual, $assert_message);
                    break;
                case 'fail':
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $assert_message);
                    break;
                case 'magentoCLI':
                case 'magentoCLISecret':
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $command, $time, $arguments);
                    $test_steps .= sprintf(self::STEP_KEY_ANNOTATION, $step_key) . PHP_EOL;
                    $test_steps .= sprintf("\t\t\$%s->comment(\$%s);", $actor, $step_key);
                    break;
                case 'magentoCron':
                    $test_steps .= $this->wrap_function_call_with_return_value($step_key, $actor, $action_object, $cron_groups, self::CRON_INTERVAL + $time, $arguments);
                    $test_steps .= sprintf(self::STEP_KEY_ANNOTATION, $step_key) . PHP_EOL;
                    $test_steps .= sprintf("\t\t\$%s->comment(\$%s);", $actor, $step_key);
                    break;
                case 'field':
                    $field_key = $action_object->get_custom_action_attributes()['key'];
                    $input = $this->resolve_step_key_references($input, $action_object->get_action_origin());
                    $input = $this->resolve_test_variable([$input], $action_object->get_action_origin())[0];
                    $arg_ref = "\t\t\$";
                    $input = $this->resolve_all_runtime_references([$input])[0];
                    $input = isset($action_object->get_custom_action_attributes()['unique']) ? $this->get_unique_id_for_input($action_object->get_custom_action_attributes()['unique'], $input) : $input;
                    $arg_ref .= str_replace(ucfirst((string) $field_key), '', $step_key) . "Fields['{$field_key}'] = {$input};";
                    $test_steps .= $arg_ref;
                    break;
                case 'generateDate':
                    $timezone = getenv('DEFAULT_TIMEZONE');
                    if (isset($custom_action_attributes['timezone'])) {
                        $timezone = $custom_action_attributes['timezone'];
                    }
                    $date_generate_code = "\t\t\$date = new \\DateTime();\n";
                    $date_generate_code .= "\t\t\$date->setTimestamp(strtotime({$input}));\n";
                    $date_generate_code .= "\t\t\$date->setTimezone(new \\DateTimeZone(\"{$timezone}\"));\n";
                    $date_generate_code .= "\t\t\${$step_key} = \$date->format({$format});\n";
                    $test_steps .= $date_generate_code;
                    break;
                case 'pause':
                    $pause_attr = $action_object->get_custom_action_attributes(Action_Object::PAUSE_ACTION_INTERNAL_ATTRIBUTE);
                    if ($pause_attr) {
                        $test_steps .= sprintf("\t\t\$%s->%s(%s);", $actor, $action_object->get_type(), 'true');
                    } else {
                        $test_steps .= sprintf("\t\t\$%s->%s();", $actor, $action_object->get_type());
                    }
                    break;
                case 'comment':
                    $input ??= strtr($value, ['$' => '\$', '{' => '\{', '}' => '\}']);
                // Combining userInput from native XML comment and <comment/> action to fall-through 'default' case
                // no break
                default:
                    $test_steps .= $this->wrap_function_call($actor, $action_object, $selector, $input, $parameter);
            }
            if (!in_array($action_object->get_type(), self::NO_STEPKEY_ACTIONS)) {
                $test_steps .= sprintf(self::STEP_KEY_ANNOTATION, $step_key);
            }
            $test_steps .= PHP_EOL;
        }
        return $test_steps;
    }
    /**
     * Get unique value appended to input string
     *
     * @param string $uniqueValue
     * @param string $input
     */
    public function get_unique_id_for_input($unique_value, $input): string
    {
        return $unique_value == 'prefix' ? '"' . uniqid() . str_replace('"', '', $input) . '"' : '"' . str_replace('"', '', $input) . uniqid() . '"';
    }
    /**
     * Resolves Locator:: in given $attribute if it is found.
     *
     * @param string $attribute
     * @return string
     */
    private function resolve_locator_function_in_attribute($attribute)
    {
        if (str_contains($attribute, 'Locator::')) {
            $attribute = $this->strip_wrapped_quotes($attribute);
            $attribute = $this->wrap_function_args_with_quotes("/Locator::[\\w]+\\(([\\s\\S]+)\\)/", $attribute);
        }
        return $attribute;
    }
    /**
     * Resolves replacement of $input$ and $$input$$ in given function, recursing and replacing individual arguments
     * Also determines if each argument requires any quote replacement.
     *
     * @param array $args
     * @param array $actionOrigin
     * @throws \Exception
     */
    private function resolve_test_variable($args, $action_origin): array
    {
        $new_args = [];
        foreach ($args as $key => $arg) {
            if ($arg === null) {
                continue;
            }
            $output_arg = $arg;
            // Math on $data.key$ and $$data.key$$
            preg_match_all(self::PERSISTED_OBJECT_NOTATION_REGEX, $output_arg, $matches);
            $this->replace_matches_into_arg($matches[0], $output_arg);
            //trim "{$variable}" into $variable
            $output_arg = $this->trim_variable_if_needed($output_arg);
            $output_arg = $this->resolve_step_key_references($output_arg, $action_origin);
            $new_args[$key] = $output_arg;
        }
        return $new_args;
    }
    /**
     * Trims given $input of "{$var}" to $var if needed. Returns $input if format fails.
     */
    private function trim_variable_if_needed(string $input): string
    {
        preg_match('/"{\$[a-z][a-zA-Z\d]+}"/', $input, $match);
        if (isset($match[0])) {
            return trim($input, '{}"');
        }
        return $input;
    }
    /**
     * Replaces all matches into given outputArg with. Variable scope determined by delimiter given.
     *
     * @param string $outputArg
     * @throws \Exception
     */
    private function replace_matches_into_arg(array $matches, &$output_arg): void
    {
        // Remove Duplicate $matches from array. Duplicate matches are replaced all in one go.
        $matches = array_unique($matches);
        foreach ($matches as $match) {
            $replacement = null;
            $delimiter = '$';
            $variable = $this->strip_and_split_reference($match, $delimiter);
            if (count($variable) !== 2) {
                throw new \Exception("Invalid Persisted Entity Reference: {$match}.\n                Test persisted entity references must follow {$delimiter}entityStepKey.field{$delimiter} format.");
            }
            $actor = '$' . $this->actor;
            if ($this->current_generation_scope === Test_Generator::SUITE_SCOPE) {
                $actor = 'PersistedObjectHandler::getInstance()';
            }
            $replacement = "{$actor}->retrieveEntityField";
            $replacement .= "('{$variable[0]}', '{$variable[1]}', '{$this->current_generation_scope}')";
            //Determine if quoteBreak check is necessary. Assume replacement is surrounded in quotes, then override
            if (str_contains($output_arg, '"')) {
                $output_arg = $this->process_quote_breaks($match, $output_arg, $replacement);
            } else {
                $output_arg = str_replace($match, $replacement, $output_arg);
            }
        }
    }
    /**
     * Processes an argument for $data.key$ and determines if it needs quote breaks on either ends.
     * Returns an output with quote breaks and replacement already done.
     *
     * @param string $match
     * @param string $argument
     * @return string
     */
    private function process_quote_breaks($match, $argument, string $replacement): string|array|null
    {
        $output_arg = str_replace($match, '" . ' . $replacement . ' . "', $argument);
        //Sanitize string of any unnecessary '"" .' and '. ""'.
        //Regex means: Search for '"" . ' but not '\"" . '  and ' . ""'.
        //Matches on '"" . ' and ' . ""', but not on '\"" . ' and ' . "\"'.
        $output_arg = preg_replace('/(?(?<![\\\\])"" \. )| \. ""/', '', $output_arg);
        return $output_arg;
    }
    /**
     * Replaces any occurrences of stepKeys in input, if they are found within the given actionGroup.
     * Necessary to allow for use of grab/createData actions in actionGroups.
     * @param string $input
     * @return string
     */
    private function resolve_step_key_references($input, array $action_group_origin, bool $match_all = false)
    {
        if ($action_group_origin === null) {
            return $input;
        }
        $output = $input;
        $action_group = Action_Group_Object_Handler::get_instance()->get_object($action_group_origin[Action_Group_Object::ACTION_GROUP_ORIGIN_NAME]);
        $step_keys = $action_group->extract_step_keys();
        $test_invocation_key = ucfirst((string) $action_group_origin[Action_Group_Object::ACTION_GROUP_ORIGIN_TEST_REF]);
        foreach ($step_keys as $step_key) {
            // MQE-1011
            $step_key_var_ref = '$' . $step_key;
            $actor = '$' . $this->actor;
            if ($this->current_generation_scope === Test_Generator::SUITE_SCOPE) {
                $actor = 'PersistedObjectHandler::getInstance()';
            }
            $persisted_var_ref = "{$actor}->retrieveEntityField('{$step_key}'" . ", 'field', 'test')";
            $persisted_var_ref_invoked = "{$actor}->retrieveEntityField('" . $step_key . $test_invocation_key . "', 'field', 'test')";
            // only replace when whole word matches exactly
            // e.g. testVar => $testVar but not $testVar2
            if (str_contains((string) $output, $step_key_var_ref)) {
                $output = preg_replace('/\B\\' . $step_key_var_ref . '\b/', $step_key_var_ref . $test_invocation_key, (string) $output);
            }
            if (str_contains((string) $output, $persisted_var_ref)) {
                $output = str_replace($persisted_var_ref, $persisted_var_ref_invoked, $output);
            }
            if ($match_all && str_contains((string) $output, $step_key)) {
                $output = str_replace($step_key, $step_key . $test_invocation_key, $output);
            }
        }
        return $output;
    }
    /**
     * Wraps all args inside function give with double quotes. Uses regex to locate arguments of function.
     *
     * @param string $input
     * @return string
     */
    private function wrap_function_args_with_quotes(string $function_regex, $input)
    {
        $output = $input;
        preg_match_all($function_regex, $input, $matches);
        //If no Arguments were passed in
        if (!isset($matches[1][0])) {
            return $input;
        }
        $all_arguments = explode(',', $matches[1][0]);
        foreach ($all_arguments as $argument) {
            $argument = trim($argument);
            if ($argument[0] === self::ARRAY_WRAP_OPEN) {
                $replacement = $this->wrap_parameter_array($this->add_uniqueness_to_param_array($argument));
            } elseif (is_numeric($argument)) {
                $replacement = $argument;
            } else {
                $replacement = $this->add_uniqueness_function_call($argument);
            }
            //Replace only first occurrence of argument with "argument"
            $pos = strpos($output, $argument);
            $output = substr_replace($output, $replacement, $pos, strlen($argument));
        }
        return $output;
    }
    /**
     * Performs str_replace on variable reference, dependent on delimiter and returns exploded array.
     *
     * @param string $reference
     */
    private function strip_and_split_reference($reference, string $delimiter): array
    {
        $stripped_reference = str_replace($delimiter, '', $reference);
        return explode('.', $stripped_reference);
    }
    /**
     * Creates a PHP string for the _before/_after methods if the Test contains an <before> or <after> block.
     *
     * @param TestHookObject[] $hookObjects
     * @throws TestReferenceException
     * @throws \Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function generate_hooks_php(array $hook_objects): string
    {
        $hooks = '';
        if (!isset($hook_objects['after'])) {
            $hook_objects['after'] = new Test_Hook_Object('after', '', []);
        }
        foreach ($hook_objects as $hook_object) {
            $type = $hook_object->get_type();
            $dependencies = 'AcceptanceTester $I';
            $hooks .= "\t/**\n";
            $hooks .= "\t  * @param AcceptanceTester \$I\n";
            $hooks .= "\t  * @throws \\Exception\n";
            $hooks .= "\t  */\n";
            try {
                $steps = $this->generate_steps_php($hook_object->get_actions(), Test_Generator::HOOK_SCOPE);
            } catch (Test_Reference_Exception $e) {
                throw new Test_Reference_Exception($e->get_message() . ' in Element "' . $type . '"');
            }
            if ($type === 'before' && $steps) {
                $steps = sprintf("\t\t\$%s->comment('[%s]');" . PHP_EOL, 'I', 'START BEFORE HOOK') . $steps;
                $steps = $steps . sprintf("\t\t\$%s->comment('[%s]');" . PHP_EOL, 'I', 'END BEFORE HOOK');
            }
            if ($type === 'after' && $steps) {
                $steps = sprintf("\t\t\$%s->comment('[%s]');" . PHP_EOL, 'I', 'START AFTER HOOK') . $steps;
                $steps = $steps . sprintf("\t\t\$%s->comment('[%s]');" . PHP_EOL, 'I', 'END AFTER HOOK');
            }
            $hooks .= sprintf("\tpublic function _{$type}(%s)\n", $dependencies);
            $hooks .= "\t{\n";
            $hooks .= $steps;
            if ($type === 'after') {
                $hooks .= "\t\t" . 'if ($this->isSuccess) {' . "\n";
                $hooks .= "\t\t\t" . 'unlink(__FILE__);' . "\n";
                $hooks .= "\t\t" . '}' . "\n";
            }
            $hooks .= "\t}\n\n";
        }
        return $hooks;
    }
    /**
     * Creates a PHP string based on a <test> block.
     * Concatenates the Test Annotations PHP and Test PHP for a single Test.
     *
     * @param TestObject $test
     * @throws TestReferenceException
     * @throws \Exception
     */
    private function generate_test_php($test): string
    {
        $test_php = '';
        $test_name = $test->get_name();
        $test_name = str_replace(' ', '', $test_name);
        $test_annotations = $this->generate_annotations_php($test, true);
        $dependencies = 'AcceptanceTester $I';
        if (!$test->is_skipped() || Mftf_Application_Config::get_config()->allow_skipped()) {
            try {
                $steps = $this->generate_steps_php($test->get_ordered_actions());
            } catch (\Exception $e) {
                throw new Test_Reference_Exception($e->get_message() . ' in Test "' . $test->get_name() . '"');
            }
        } else {
            $skip_string = 'This test is skipped due to the following issues:\n';
            $issues = $test->get_annotations()['skip'] ?? null;
            if (isset($issues)) {
                $skip_string .= implode('\n', $issues);
            } else {
                $skip_string .= 'No issues have been specified.';
            }
            $steps = "\t\t" . 'unlink(__FILE__);' . "\n";
            $steps .= "\t\t" . '$scenario->skip("' . $skip_string . '");' . "\n";
            $dependencies .= ', \Codeception\Scenario $scenario';
        }
        $test_php .= $test_annotations;
        $test_php .= sprintf("\tpublic function %s(%s)\n", $test_name, $dependencies);
        $test_php .= "\t{\n";
        $test_php .= $steps;
        $test_php .= "\t}\n";
        if (!isset($skip_string)) {
            $test_php .= PHP_EOL;
            $test_php .= sprintf("\tpublic function _passed(%s)\n", $dependencies);
            $test_php .= "\t{\n";
            $test_php .= "\t\t// Test passed successfully." . PHP_EOL;
            $test_php .= "\t\t\$this->isSuccess = true;" . PHP_EOL;
            $test_php .= "\t}\n";
        }
        return $test_php;
    }
    /**
     * Detects uniqueness function calls on given attribute, and calls addUniquenessFunctionCall on matches.
     *
     * @param string $input
     */
    private function add_uniqueness_to_param_array($input): string
    {
        $temp_input = trim($input, '[]');
        $param_array = explode(',', $temp_input);
        $result = [];
        foreach ($param_array as $param) {
            // Determine if param has key/value array notation
            if (preg_match_all('/(.+)=>(.+)/', trim($param), $param_matches)) {
                $param1 = $this->add_uniqueness_to_param_array($param_matches[1][0]);
                $param2 = $this->add_uniqueness_to_param_array($param_matches[2][0]);
                $result[] = trim($param1) . ' => ' . trim($param2);
                continue;
            }
            // Matches strings wrapped in ', we assume these are string literals
            if (preg_match('/^(["\']).*\1$/m', trim($param))) {
                $result[] = $param;
                continue;
            }
            $replacement = $this->add_uniqueness_function_call(trim($param));
            $result[] = $replacement;
        }
        return implode(', ', $result);
    }
    /**
     * Process pressKey parameterArray attribute for uniqueness function call and necessary data resolutions
     *
     * @param string $input
     * @return string
     */
    private function process_press_key($input): string|array
    {
        // validate the param array is in the correct format
        $input = trim($input);
        $this->validate_parameter_array($input);
        // trim off the outer braces
        $input = substr($input, 1, strlen($input) - 2);
        $result = [];
        $array_result = [];
        $count = 0;
        // matches all arrays and replaces them with placeholder to prevent later param manipulation
        preg_match_all('/[\[][^\]]*?[\]]/', $input, $param_input);
        foreach ($param_input[0] as $param) {
            $array_result[self::PRESSKEY_ARRAY_ANCHOR_KEY . $count] = $this->wrap_parameter_array(trim($this->add_uniqueness_to_param_array($param)));
            $input = str_replace($param, self::PRESSKEY_ARRAY_ANCHOR_KEY . $count, $input);
            $count++;
        }
        $param_array = explode(',', $input);
        foreach ($param_array as $param) {
            // matches strings wrapped in ', we assume these are string literals
            if (preg_match('/^[\s]*(\'.*?\')[\s]*$/', $param)) {
                $result[] = trim($param);
                continue;
            }
            // matches \ for Facebook WebDriverKeys classes
            if (str_starts_with(trim($param), '\\')) {
                $result[] = trim($param);
                continue;
            }
            // matches numbers
            if (preg_match('/^[\s]*(\d+?)[\s]*$/', $param)) {
                $result[] = $param;
                continue;
            }
            $replacement = $this->add_uniqueness_function_call(trim($param));
            $result[] = $replacement;
        }
        $result = implode(',', $result);
        // reinsert arrays into result
        foreach ($array_result as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        return $result;
    }
    /**
     * Add uniqueness function call to input string based on regex pattern.
     *
     * @param string  $input
     * @return string
     */
    private function add_uniqueness_function_call($input, bool $wrap_with_double_quotes = true): ?string
    {
        if ($wrap_with_double_quotes) {
            $output = $this->wrap_with_double_quotes($input);
        } else {
            $output = $input;
        }
        //Match on msq(\"entityName\")
        preg_match_all('/' . Entity_Data_Object::CEST_UNIQUE_FUNCTION . '\(\\\\"[\w]+\\\\"\)/', $output, $matches);
        foreach (array_unique($matches[0]) as $match) {
            preg_match('/\\\\"([\w]+)\\\\"/', $match, $entity_match);
            $entity = $entity_match[1];
            $output = str_replace($match, '" . msq("' . $entity . '") . "', $output);
        }
        // trim unnecessary "" . and . ""
        return preg_replace('/(?(?<![\\\\])"" \. )| \. ""/', '', $output);
    }
    /**
     * Wrap input string with double quotes, and replaces " with \" to prevent broken PHP when generated.
     *
     * @param string $input
     */
    private function wrap_with_double_quotes($input): string
    {
        if ($input === null || $input === '') {
            return '';
        }
        //Only replace &quot; with \" so that it doesn't break outer string.
        $input = str_replace('"', '\"', $input);
        return sprintf('"%s"', $input);
    }
    /**
     * Strip beginning and ending double quotes of input string.
     */
    private function strip_wrapped_quotes(string $input): string
    {
        if (empty($input)) {
            return '';
        }
        return trim($input, '"');
    }
    /**
     * Add dollar sign at the beginning of input string.
     */
    private function add_dollar_sign(string $input): string
    {
        return sprintf('$%s', ltrim($this->strip_quotes($input), '$'));
    }
    /**
     * Check if the entity exists
     *
     * @throws TestReferenceException
     */
    public function entity_exists_check(string $entity, string $step_key): void
    {
        $retrieved_entity = Data_Object_Handler::get_instance()->get_object($entity);
        if ($retrieved_entity === null) {
            throw new Test_Reference_Exception('Test generation failed as entity "' . $entity . '" does not exist. at stepkey ' . $step_key);
        }
    }
    /**
     * Wrap parameters into a function call.
     *
     * @param actionObject $action
     * @param array        ...$args
     * @throws \Exception
     */
    private function wrap_function_call(string $actor, $action, ...$args): string
    {
        $output = sprintf("\t\t\$%s->%s(", $actor, $action->get_type());
        for ($i = 0; $i < count($args); $i++) {
            if (null === $args[$i]) {
                continue;
            }
            if ($args[$i] === '') {
                $args[$i] = '""';
            }
        }
        if (!is_array($args)) {
            $args = [$args];
        }
        $args = $this->resolve_all_runtime_references($args);
        $args = $this->resolve_test_variable($args, $action->get_action_origin());
        return $output . (implode(', ', array_filter($args, $this->filter_null_callback())) . ');');
    }
    /**
     * Wrap parameters into a function call with a return value.
     *
     * @param string       $actor
     * @param actionObject $action
     * @param array        ...$args
     * @throws \Exception
     */
    private function wrap_function_call_with_return_value(string $return_variable, $actor, $action, ...$args): string
    {
        $action_type = $action->get_type();
        if ($action_type === 'helper') {
            $actor = "this->helperContainer->get('" . $action->get_custom_action_attributes()['class'] . "')";
            $args = $args[0];
            $action_type = $action->get_custom_action_attributes()['method'];
        }
        $output = sprintf("\t\t\$%s = \$%s->%s(", $return_variable, $actor, $action_type);
        for ($i = 0; $i < count($args); $i++) {
            if (null === $args[$i]) {
                continue;
            }
            if ($args[$i] === '') {
                $args[$i] = '""';
            }
        }
        if (!is_array($args)) {
            $args = [$args];
        }
        $args = $this->resolve_all_runtime_references($args);
        $args = $this->resolve_test_variable($args, $action->get_action_origin());
        return $output . (implode(', ', array_filter($args, $this->filter_null_callback())) . ');');
    }
    /**
     * Closure returned is used as a callable for array_filter to remove null values from array
     *
     * @return callable
     */
    private function filter_null_callback()
    {
        return fn($value) => $value !== null;
    }
    /**
     * Resolves {{_ENV.variable}} into getenv("variable") for test-runtime ENV referencing.
     *
     * @param array  $args
     */
    private function resolve_runtime_reference($args, string $regex, string $func): array
    {
        $new_args = [];
        foreach ($args as $key => $arg) {
            $new_args[$key] = $arg;
            if ($arg !== null) {
                preg_match_all($regex, $arg, $matches);
                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match_key => $full_match) {
                        $ref_variable = $matches[1][$match_key];
                        $replacement = $this->get_replacement($func, $ref_variable);
                        $output_arg = $this->process_quote_breaks($full_match, $new_args[$key], $replacement);
                        $new_args[$key] = $output_arg;
                    }
                    unset($matches);
                    continue;
                }
            }
        }
        // override passed in args for use later.
        return $new_args;
    }
    /**
     * Takes a predefined list of potentially matching special paramts and they needed function replacement and performs
     * replacements on the tests args.
     *
     * @return array
     */
    private function resolve_all_runtime_references(array $args)
    {
        $runtime_reference_regex = ["/{{_ENV\\.([\\w]+)}}/" => 'getenv', Action_Merge_Util::CREDS_REGEX => "\${$this->actor}->getSecret"];
        $arg_result = $args;
        foreach ($runtime_reference_regex as $regex => $func) {
            $arg_result = $this->resolve_runtime_reference($arg_result, $regex, $func);
        }
        return $arg_result;
    }
    /**
     * Validates parameter array format, making sure user has enclosed string with square brackets.
     *
     * @param string $paramArray
     * @throws TestReferenceException
     */
    private function validate_parameter_array($param_array): void
    {
        if (!$this->is_wrapped_array($param_array)) {
            throw new Test_Reference_Exception(sprintf('parameterArray must begin with `%s` and end with `%s`', self::ARRAY_WRAP_OPEN, self::ARRAY_WRAP_CLOSE));
        }
    }
    /**
     * Verifies whether we have correctly wrapped array syntax
     */
    private function is_wrapped_array(string $param_array): bool
    {
        return str_starts_with($param_array, self::ARRAY_WRAP_OPEN) && substr($param_array, -1) === self::ARRAY_WRAP_CLOSE;
    }
    /**
     * Resolve value based on type.
     *
     * @return string|null
     * @throws TestReferenceException
     */
    private function resolve_value_by_type(?string $value = null, ?string $type = null)
    {
        if (null === $value) {
            return null;
        }
        if (null === $type) {
            $type = 'const';
        }
        switch ($type) {
            case 'string':
                return $this->add_uniqueness_function_call($value);
            case 'bool':
                return $this->to_boolean($value) ? 'true' : 'false';
            case 'int':
            case 'float':
                return $this->to_number($value);
            case 'array':
                $this->validate_parameter_array($value);
                return $this->wrap_parameter_array($this->add_uniqueness_to_param_array($value));
            case 'variable':
                return $this->add_dollar_sign($value);
        }
        return $value;
    }
    /**
     * Determines correct scope based on parameter
     */
    private function get_object_scope(string $generation_scope): string
    {
        return match ($generation_scope) {
            Test_Generator::SUITE_SCOPE => Persisted_Object_Handler::SUITE_SCOPE,
            Test_Generator::HOOK_SCOPE => Persisted_Object_Handler::HOOK_SCOPE,
            default => Persisted_Object_Handler::TEST_SCOPE,
        };
    }
    /**
     * Convert input string to boolean equivalent.
     */
    private function to_boolean(string $in_str): bool
    {
        return boolval($this->strip_quotes($in_str));
    }
    /**
     * Convert input string to number equivalent.
     */
    private function to_number(string $in_str): float|int
    {
        $out_str = $this->strip_quotes($in_str);
        if ($this->has_decimal_point($out_str)) {
            return floatval($out_str);
        }
        return intval($out_str);
    }
    /**
     * Strip single or double quotes from begin and end of input string.
     *
     * @return string
     */
    private function strip_quotes(string $in_str): ?string
    {
        return preg_replace('/^(\'(.*)\'|"(.*)")$/', '$2$3', $in_str);
    }
    /**
     * Validate action attributes are either not set at all or only one is set for a given rule.
     *
     * @param string $key
     * @param string $tagName
     */
    private function validate_xml_attributes_mutually_exclusive($key, $tag_name, array $attributes): void
    {
        $rules = [['attributes' => ['selector', 'selectorArray']], ['attributes' => ['url', 'userInput', 'variable'], 'excludes' => ['dontSeeLink', 'seeLink']], ['attributes' => ['userInput', 'parameterArray', 'variable'], 'excludes' => ['dontSeeCookie', 'grabCookie', 'grabCookieAttributes', 'resetCookie', 'seeCookie', 'setCookie']]];
        foreach ($rules as $rule) {
            if (isset($rule['excludes']) && in_array($tag_name, $rule['excludes'])) {
                continue;
            }
            $count = 0;
            foreach ($rule['attributes'] as $attribute) {
                if (isset($attributes[$attribute])) {
                    $count++;
                }
            }
            if ($count > 1) {
                $this->print_rule_error_to_console($key, $tag_name, $rule['attributes']);
            }
        }
    }
    /**
     * Print rule violation message to console.
     *
     * @param string $key
     * @param string $tagName
     */
    private function print_rule_error_to_console($key, $tag_name, array $attributes): void
    {
        if (empty($tag_name) || empty($attributes)) {
            return;
        }
        printf(self::RULE_ERROR, $key, implode('", "', $attributes), $tag_name);
    }
    /**
     * Wraps parameters array with opening and closing symbol.
     */
    private function wrap_parameter_array(string $value): string
    {
        return sprintf('%s%s%s', self::ARRAY_WRAP_OPEN, $value, self::ARRAY_WRAP_CLOSE);
    }
    /**
     * Determines whether string provided contains decimal point characteristic for current locale
     */
    private function has_decimal_point(string $out_str): bool
    {
        return str_contains($out_str, (string) localeconv()['decimal_point']);
    }
    /**
     * Parse action attribute `userInput`
     *
     * @param string $userInput
     * @return string
     */
    private function parse_user_input($user_input)
    {
        $float_pattern = '/^\s*([+-]?[0-9]*\.?[0-9]+)\s*$/';
        preg_match($float_pattern, $user_input, $float);
        if (isset($float[1])) {
            return $float[1];
        }
        $int_pattern = '/^\s*([+-]?[0-9]+)\s*$/';
        preg_match($int_pattern, $user_input, $int);
        return $int[1] ?? $this->add_uniqueness_function_call($user_input);
    }
    /**
     * Supports fallback for BACKEND URL
     */
    private function get_replacement(string $func, string $ref_variable): string
    {
        if ($ref_variable === 'MAGENTO_BACKEND_BASE_URL') {
            return "({$func}(\"{$ref_variable}\") ? rtrim({$func}(\"{$ref_variable}\"), \"/\") : \"\")";
        }
        return "{$func}(\"{$ref_variable}\")";
    }
}