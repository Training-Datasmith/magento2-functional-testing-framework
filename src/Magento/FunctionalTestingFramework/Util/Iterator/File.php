<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Iterator;

/**
 * Class File
 *
 * @api
 */
class File extends Abstract_Iterator
{
    /**
     * Cached files content
     *
     * @var array
     */
    protected $cached = [];
    /**
     * File constructor.
     */
    public function __construct(array $paths)
    {
        $this->data = $paths;
        $this->init_first_element();
    }
    /**
     * Return filename of current file object
     *
     * @return string
     */
    public function get_filename()
    {
        return $this->data[$this->key()];
    }
    /**
     * Get file content
     *
     * @return string
     */
    public function current()
    {
        if (!isset($this->cached[$this->current])) {
            $this->cached[$this->current] = file_get_contents($this->current);
        }
        return $this->cached[$this->current];
    }
    /**
     * Check if current element is valid
     */
    protected function is_valid(): bool
    {
        return true;
    }
}