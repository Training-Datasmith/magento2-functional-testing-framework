<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace tests\verification\Tests;

use Exception;
use Magento\FunctionalTestingFramework\StaticCheck\PauseActionUsageCheck;
use Magento\FunctionalTestingFramework\StaticCheck\StaticChecksList;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use tests\util\MftfStaticTestCase;

class PauseActionStaticCheckTest extends MftfStaticTestCase
{
    public const LOG_FILE = self::STATIC_RESULTS_DIR .
        DIRECTORY_SEPARATOR .
        PauseActionUsageCheck::ERROR_LOG_FILENAME .
        '.txt';

    public const TEST_MODULE_PATH = TESTS_MODULE_PATH .
        DIRECTORY_SEPARATOR .
        'PauseCheckModule'.
        DIRECTORY_SEPARATOR;

    /**
     * test static-check PauseActionUsageCheck.
     *
     * @throws Exception
     */
    public function testPauseActionUsageCheck()
    {
        $staticCheck = new PauseActionUsageCheck();

        $input = $this->mockInputInterface(self::TEST_MODULE_PATH);
        $reflectionClass = new ReflectionClass(StaticChecksList::class);
        $reflectionClass->setStaticPropertyValue('errorFilesPath', self::STATIC_RESULTS_DIR);

        /** @var InputInterface $input */
        $staticCheck->execute($input);

        $this->assertTrue(file_exists(self::LOG_FILE));
        $this->assertFileEquals(
            self::RESOURCES_PATH.
            DIRECTORY_SEPARATOR .
            PauseActionUsageCheck::ERROR_LOG_FILENAME .
            '.txt',
            self::LOG_FILE
        );
    }

    /**
     * @inheritdoc
     */
    public static function tearDownAfterClass(): void
    {
        $reflectionClass = new ReflectionClass(StaticChecksList::class);
        $reflectionClass->setStaticPropertyValue('errorFilesPath', null);
    }
}
