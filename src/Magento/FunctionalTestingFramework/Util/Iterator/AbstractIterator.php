<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Iterator;

/**
 * Class AbstractIterator
 *
 * @api
 */
abstract class Abstract_Iterator implements \Iterator, \Countable
{
    /**
     * Data
     *
     * @var array
     */
    protected $data = [];
    // @codingStandardsIgnoreStart
    /**
     * Current data element
     *
     * @var mixed
     */
    protected $current;
    /**
     * Get current element
     *
     * @return mixed
     */
    #[\Return_Type_Will_Change]
    abstract public function current();
    // @codingStandardsIgnoreEnd
    /**
     * Key associated with the current row data
     *
     * @var int|string
     */
    protected $key;
    /**
     * Check if current element is valid
     */
    abstract protected function is_valid(): bool;
    /**
     * Initialize Data Array
     */
    public function rewind(): void
    {
        reset($this->data);
        if (!$this->is_valid()) {
            $this->next();
        }
    }
    /**
     * Seek to next valid row
     */
    public function next(): void
    {
        $this->current = next($this->data);
        if ($this->current !== false) {
            if (!$this->is_valid()) {
                $this->next();
            }
        } else {
            $this->key = null;
        }
    }
    /**
     * Check if current position is valid
     */
    public function valid(): bool
    {
        $current = current($this->data);
        if ($current === false || $current === null) {
            return false;
        }
        return true;
    }
    // @codingStandardsIgnoreStart
    /**
     * Get data key of the current data element
     *
     * @return integer|string
     */
    #[\Return_Type_Will_Change]
    public function key()
    {
        return key($this->data);
    }
    // @codingStandardsIgnoreEnd
    /**
     * To make iterator countable
     */
    public function count(): int
    {
        return count($this->data);
    }
    /**
     * Initialize first element
     *
     * @return void
     */
    protected function init_first_element()
    {
        if ($this->data) {
            $this->current = reset($this->data);
            if (!$this->is_valid()) {
                $this->next();
            }
        }
    }
}