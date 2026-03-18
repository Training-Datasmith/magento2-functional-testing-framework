<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Page\Objects;

use Magento\FunctionalTestingFramework\Exceptions\XmlException;

/**
 * Class ElementObject
 */
class ElementObject
{
    public const DEFAULT_TIMEOUT_SYMBOL = '-';

    /**
     * Section element name
     *
     * @var string
     */
    private $name;

    /**
     * Section element locator
     *
     * @var string
     */
    private $selector;

    /**
     * Section element locatorFunction
     */
    private string $locatorFunction;

    /**
     * ElementObject constructor.
     * @param string  $name
     * @param string  $type
     * @param string  $selector
     * @param string  $timeout
     * @param boolean $parameterized
     * @throws XmlException
     * @param string $deprecated
     */
    public function __construct($name, /**
     * Section element type
     */
        private $type, $selector, string $locatorFunction, /**
     * Section element timeout
     */
        private $timeout, /**
     * Section element locator is parameterized
     */
        private $parameterized, /**
     * Deprecated message.
     */
        private $deprecated = null)
    {
        if ($selector !== null && $locatorFunction !== null) {
            throw new XmlException("Element '{$name}' cannot have both a selector and a locatorFunction.");
        }
        if ($selector === null && $locatorFunction === null) {
            throw new XmlException("Element '{$name}' must have either a selector or a locatorFunction.'");
        }

        $this->name = $name;
        $this->selector = $selector;
        $this->locatorFunction = $locatorFunction;
        if (!str_contains($locatorFunction, 'Locator::')) {
            $this->locatorFunction = 'Locator::' . $locatorFunction;
        }
    }

    /**
     * Getter for the deprecated attr
     *
     * @return string
     */
    public function getDeprecated()
    {
        return $this->deprecated;
    }

    /**
     * Getter for the name of the element
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Getter for the name of the element type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Getter for the selector of an element
     *
     * @return string
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * Getter for the locatorFunction of an element
     *
     * @return string
     */
    public function getLocatorFunction()
    {
        return $this->locatorFunction;
    }

    /**
     * Returns selector if not null, otherwise returns locatorFunction
     *
     * @return string
     */
    public function getPrioritizedSelector()
    {
        return $this->selector ?: $this->locatorFunction;
    }

    /**
     * Returns an integer representing an element's timeout
     */
    public function getTimeout(): ?int
    {
        if ($this->timeout === ElementObject::DEFAULT_TIMEOUT_SYMBOL) {
            return null;
        }

        return (int) $this->timeout;
    }

    /**
     * Determines if the element's selector is parameterized. Based on $parameterized property.
     *
     * @return boolean
     */
    public function isParameterized()
    {
        return $this->parameterized;
    }
}
