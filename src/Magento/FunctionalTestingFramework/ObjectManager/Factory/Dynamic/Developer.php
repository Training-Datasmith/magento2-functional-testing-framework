<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\ObjectManager\Factory\Dynamic;

/**
 * Class Developer
 */
class Developer implements \Magento\FunctionalTestingFramework\ObjectManager\FactoryInterface
{
    /**
     * Definition list
     */
    protected \Magento\FunctionalTestingFramework\ObjectManager\DefinitionInterface $definitions;

    /**
     * Object creation stack
     *
     * @var array
     */
    protected $creationStack = [];

    /**
     * Developer constructor.
     * @param array                                                                      $globalArguments
     */
    public function __construct(
        /**
         * Object manager config
         */
        protected \Magento\FunctionalTestingFramework\ObjectManager\ConfigInterface $config,
        /**
         * Object manager
         */
        protected ?\Magento\FunctionalTestingFramework\ObjectManagerInterface $objectManager = null,
        ?\Magento\FunctionalTestingFramework\ObjectManager\DefinitionInterface $definitions = null,
        /**
         * Global arguments.
         */
        protected $globalArguments = []
    ) {
        $this->definitions = $definitions ?: new \Magento\FunctionalTestingFramework\ObjectManager\Definition\Runtime();
    }

    /**
     * Set object manager
     */
    public function setObjectManager(\Magento\FunctionalTestingFramework\ObjectManagerInterface $objectManager): void
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Resolve constructor arguments
     *
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * Revisited to reduce cyclomatic complexity, left unrefactored for readability
     */
    protected function resolveArguments(string $requestedType, array $parameters, array $arguments = []): array
    {
        $resolvedArguments = [];
        $arguments = count($arguments)
            ? array_replace($this->config->getArguments($requestedType), $arguments)
            : $this->config->getArguments($requestedType);
        foreach ($parameters as $parameter) {
            [$paramName, $paramType, $paramRequired, $paramDefault] = $parameter;
            $argument = null;
            if (!empty($arguments) && (isset($arguments[$paramName]) || array_key_exists($paramName, $arguments))) {
                $argument = $arguments[$paramName];
            } elseif ($paramRequired) {
                if ($paramType) {
                    $argument = ['instance' => $paramType];
                } else {
                    $this->creationStack = [];
                    throw new \BadMethodCallException(
                        'Missing required argument $' . $paramName . ' of ' . $requestedType . '.'
                    );
                }
            } else {
                $argument = $paramDefault;
            }
            if ($paramType && $argument !== $paramDefault && !is_object($argument)) {
                if (!isset($argument['instance']) || !is_array($argument)) {
                    throw new \UnexpectedValueException(
                        'Invalid parameter configuration provided for $' . $paramName . ' argument of ' . $requestedType
                    );
                }
                $argumentType = $argument['instance'];
                $isShared = ($argument['shared'] ?? $this->config->isShared($argumentType));
                $argument = $isShared
                    ? $this->objectManager->get($argumentType)
                    : $this->objectManager->create($argumentType);
            } elseif (is_array($argument)) {
                if (isset($argument['argument'])) {
                    $argument = $this->globalArguments[$argument['argument']] ?? $paramDefault;
                } elseif (!empty($argument)) {
                    $this->parseArray($argument);
                }
            }
            $resolvedArguments[] = $argument;
        }
        return $resolvedArguments;
    }

    /**
     * Parse array argument
     *
     * @return void
     */
    protected function parseArray(array &$array)
    {
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                if (isset($item['instance'])) {
                    $itemType = $item['instance'];
                    $isShared = $item['shared'] ?? $this->config->isShared($itemType);
                    $array[$key] = $isShared
                        ? $this->objectManager->get($itemType)
                        : $this->objectManager->create($itemType);
                } elseif (isset($item['argument'])) {
                    $array[$key] = $this->globalArguments[$item['argument']] ?? null;
                } else {
                    $this->parseArray($array[$key]);
                }
            }
        }
    }

    /**
     * Create instance with call time arguments
     *
     * @param string $requestedType
     * @return object
     * @throws \Exception
     * @SuppressWarnings(PHPCPD)
     */
    public function create($requestedType, array $arguments = [])
    {
        $type = $this->config->getInstanceType($requestedType);
        $parameters = $this->definitions->getParameters($type);

        if ($parameters === null) {
            return new $type();
        }
        if (isset($this->creationStack[$requestedType])) {
            $lastFound = end($this->creationStack);
            $this->creationStack = [];
            throw new \LogicException("Circular dependency: {$requestedType} depends on {$lastFound} and vice versa.");
        }
        $this->creationStack[$requestedType] = $requestedType;
        try {
            $args = $this->resolveArguments($requestedType, $parameters, $arguments);
            unset($this->creationStack[$requestedType]);
        } catch (\Exception $e) {
            unset($this->creationStack[$requestedType]);
            throw $e;
        }

        $reflection = new \ReflectionClass($type);

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Set global arguments
     *
     * @param array $arguments
     */
    public function setArguments($arguments): void
    {
        $this->globalArguments = $arguments;
    }
}
