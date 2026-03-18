<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework;

use Magento\FunctionalTestingFramework\ObjectManager\Factory;
use Magento\FunctionalTestingFramework\Stdlib\BooleanUtils;

/**
 * Object Manager Factory.
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
// @codingStandardsIgnoreFile
class ObjectManagerFactory
{
    /**
     * Object Manager class name.
     *
     * @var string
     */
    protected $locatorClassName = \Magento\FunctionalTestingFramework\ObjectManager::class;

    /**
     * DI Config class name.
     *
     * @var string
     */
    protected $configClassName = \Magento\FunctionalTestingFramework\ObjectManager\Config::class;

    /**
     * Create Object Manager.
     *
     * @return ObjectManager
     */
    public function create(array $sharedInstances = [])
    {
        /** @var \Magento\FunctionalTestingFramework\ObjectManager\Config $diConfig */
        $diConfig = new $this->configClassName();

        $factory = new Factory($diConfig);
        $argInterpreter = $this->createArgumentInterpreter(new BooleanUtils());
        $argumentMapper = new \Magento\FunctionalTestingFramework\ObjectManager\Config\Mapper\Dom($argInterpreter);

        $sharedInstances[\Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface::class] = $argInterpreter;
        $sharedInstances[\Magento\FunctionalTestingFramework\ObjectManager\Config\Mapper\Dom::class] = $argumentMapper;

        /** @var \Magento\FunctionalTestingFramework\ObjectManager $objectManager */
        $objectManager = new $this->locatorClassName($factory, $diConfig, $sharedInstances);

        $factory->setObjectManager($objectManager);
        ObjectManager::setInstance($objectManager);

        self::configure($objectManager);

        return $objectManager;
    }

    /**
     * Return newly created instance on an argument interpreter, suitable for processing DI arguments.
     *
     * @return \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
     */
    protected function createArgumentInterpreter(
        \Magento\FunctionalTestingFramework\Stdlib\BooleanUtils $booleanUtils
    ): \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\Composite {
        $constInterpreter = new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\Constant();
        $result = new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\Composite(
            [
                'boolean' => new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\Boolean($booleanUtils),
                'string' => new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\StringUtils($booleanUtils),
                'number' => new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\Number(),
                'null' => new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\NullType(),
                'const' => $constInterpreter,
                'object' => new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\DataObject($booleanUtils),
                'init_parameter' => new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\Argument($constInterpreter),
            ],
            \Magento\FunctionalTestingFramework\ObjectManager\Config\Reader\Dom::TYPE_ATTRIBUTE
        );
        // Add interpreters that reference the composite
        $result->addInterpreter('array', new \Magento\FunctionalTestingFramework\Data\Argument\Interpreter\ArrayType($result));
        return $result;
    }

    /**
     * Get Object Manager instance.
     *
     * @return ObjectManager
     */
    public static function getObjectManager()
    {
        if (!$objectManager = ObjectManager::getInstance()) {
            $objectManagerFactory = new self();
            $objectManager = $objectManagerFactory->create();
        }

        return $objectManager;
    }

    /**
     * Configure Object Manager.
     * This method is static to have the ability to configure multiple instances of Object manager when needed.
     */
    public static function configure(\Magento\FunctionalTestingFramework\ObjectManagerInterface $objectManager): void
    {
        $objectManager->configure(
            $objectManager->get(\Magento\FunctionalTestingFramework\ObjectManager\ConfigLoader\Primary::class)->load()
        );
    }
}
