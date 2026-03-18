<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Data\Argument\Interpreter;

use Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface;

/**
 * Interpreter that aggregates named interpreters and delegates every evaluation to one of them
 */
class Composite implements InterpreterInterface
{
    /**
     * Format: array('<name>' => <instance>, ...)
     *
     * @var InterpreterInterface[]
     */
    private array $interpreters;

    /**
     * Composite constructor.
     * @param string $discriminator
     * @throws \InvalidArgumentException
     */
    public function __construct(array $interpreters, /**
     * Data key that holds name of an interpreter to be used for that data
     */
    private $discriminator)
    {
        foreach ($interpreters as $interpreterName => $interpreterInstance) {
            if (!$interpreterInstance instanceof InterpreterInterface) {
                throw new \InvalidArgumentException(
                    "Interpreter named '{$interpreterName}' is expected to be an argument interpreter instance."
                );
            }
        }
        $this->interpreters = $interpreters;
    }

    /**
     * {@inheritdoc}
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function evaluate(array $data)
    {
        if (!isset($data[$this->discriminator])) {
            throw new \InvalidArgumentException(
                sprintf('Value for key "%s" is missing in the argument data.', $this->discriminator)
            );
        }
        $interpreterName = $data[$this->discriminator];
        unset($data[$this->discriminator]);
        $interpreter = $this->getInterpreter($interpreterName);
        return $interpreter->evaluate($data);
    }

    /**
     * Register interpreter instance under a given unique name
     *
     * @param string               $name
     * @throws \InvalidArgumentException
     */
    public function addInterpreter($name, InterpreterInterface $instance): void
    {
        if (isset($this->interpreters[$name])) {
            throw new \InvalidArgumentException("Argument interpreter named '{$name}' has already been defined.");
        }
        $this->interpreters[$name] = $instance;
    }

    /**
     * Retrieve interpreter instance by its unique name
     *
     * @param string $name
     * @return InterpreterInterface
     * @throws \InvalidArgumentException
     */
    protected function getInterpreter($name)
    {
        if (!isset($this->interpreters[$name])) {
            throw new \InvalidArgumentException("Argument interpreter named '{$name}' has not been defined.");
        }
        return $this->interpreters[$name];
    }
}
