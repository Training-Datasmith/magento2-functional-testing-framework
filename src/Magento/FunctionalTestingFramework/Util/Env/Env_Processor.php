<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Util\Env;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
/**
 * Helper class EnvProcessor for reading and writing .env files.
 *
 * @package Magento\FunctionalTestingFramework\Util\Env
 */
class Env_Processor
{
    /**
     * File .env.example location.
     *
     * @var string
     */
    private $env_example_file = '';
    /**
     * Array of environment variables form file.
     */
    private array $env = [];
    /**
     * Boolean indicating existence of env file
     */
    private readonly bool $env_exists;
    /**
     * EnvProcessor constructor.
     * @throws TestFrameworkException
     */
    public function __construct(
        /**
         * File .env location.
         */
        private readonly string $env_file = ''
    )
    {
        $this->env_exists = file_exists($this->env_file);
        $this->env_example_file = realpath(File_Path_Formatter::format(FW_BP) . 'etc/config/.env.example');
    }
    /**
     * Serves for parsing '.env' file into associative array.
     */
    private function parse_env_file(): array
    {
        $env_example_file = file($this->env_example_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env_contents = [];
        if ($this->env_exists) {
            $env_file = file($this->env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $env_contents = $this->parse_env_file_lines($env_file);
        }
        return array_merge($this->parse_env_file_lines($env_example_file), $env_contents);
    }
    /**
     * Iterates through env and returns array of file contents.
     */
    private function parse_env_file_lines(array $file): array
    {
        $file_array = [];
        foreach ($file as $line) {
            // do not use commented out lines
            if (!str_starts_with((string) $line, '#')) {
                [$key, $value] = explode('=', (string) $line);
                $file_array[$key] = $value;
            }
        }
        return $file_array;
    }
    /**
     * Serves for putting array with environment variables into .env file or appending new variables we introduce
     */
    public function put_env_file(array $config = []): void
    {
        $env_data = '';
        foreach ($config as $key => $value) {
            $env_data .= $key . '=' . $value . PHP_EOL;
        }
        file_put_contents($this->env_file, $env_data);
    }
    /**
     * Retrieves '.env.example' file as associative array.
     */
    public function get_env(): array
    {
        if (empty($this->env)) {
            $this->env = $this->parse_env_file();
        }
        return $this->env;
    }
}