<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\FunctionalTestingFramework\Util\Env;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\Path\FilePathFormatter;

/**
 * Helper class EnvProcessor for reading and writing .env files.
 *
 * @package Magento\FunctionalTestingFramework\Util\Env
 */
class EnvProcessor
{
    /**
     * File .env.example location.
     *
     * @var string
     */
    private $envExampleFile = '';

    /**
     * Array of environment variables form file.
     */
    private array $env = [];

    /**
     * Boolean indicating existence of env file
     */
    private readonly bool $envExists;

    /**
     * EnvProcessor constructor.
     * @throws TestFrameworkException
     */
    public function __construct(
        /**
         * File .env location.
         */
        private readonly string $envFile = ''
    ) {
        $this->envExists = file_exists($this->envFile);
        $this->envExampleFile = realpath(FilePathFormatter::format(FW_BP) . 'etc/config/.env.example');
    }

    /**
     * Serves for parsing '.env' file into associative array.
     */
    private function parseEnvFile(): array
    {
        $envExampleFile = file(
            $this->envExampleFile,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        $envContents = [];
        if ($this->envExists) {
            $envFile = file(
                $this->envFile,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            $envContents = $this->parseEnvFileLines($envFile);
        }

        return array_merge($this->parseEnvFileLines($envExampleFile), $envContents);
    }

    /**
     * Iterates through env and returns array of file contents.
     */
    private function parseEnvFileLines(array $file): array
    {
        $fileArray = [];
        foreach ($file as $line) {
            // do not use commented out lines
            if (!str_starts_with((string) $line, '#')) {
                [$key, $value] = explode('=', (string) $line);
                $fileArray[$key] = $value;
            }
        }
        return $fileArray;
    }

    /**
     * Serves for putting array with environment variables into .env file or appending new variables we introduce
     */
    public function putEnvFile(array $config = []): void
    {
        $envData = '';
        foreach ($config as $key => $value) {
            $envData .= $key . '=' . $value . PHP_EOL;
        }

        file_put_contents($this->envFile, $envData);
    }

    /**
     * Retrieves '.env.example' file as associative array.
     */
    public function getEnv(): array
    {
        if (empty($this->env)) {
            $this->env = $this->parseEnvFile();
        }
        return $this->env;
    }
}
