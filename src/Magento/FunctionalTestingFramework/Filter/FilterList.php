<?php

/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Filter;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
/**
 * Class FilterList has a list of filters.
 */
class Filter_List
{
    /**
     * List of filters
     * @var \Magento\FunctionalTestingFramework\Filter\FilterInterface[]
     */
    private array $filters = [];
    /**
     * Constructor for Filter list.
     *
     * @throws \Exception
     */
    public function __construct(array $filters = [])
    {
        foreach ($filters as $filter_type => $filter_value) {
            $class_name = "Magento\\FunctionalTestingFramework\\Filter\\Test\\" . ucfirst((string) $filter_type);
            if (!class_exists($class_name)) {
                throw new Test_Framework_Exception("Filter type '" . $filter_type . "' do not exist.");
            }
            $this->filters[$filter_type] = new $class_name($filter_value);
        }
    }
    public function get_filters(): array
    {
        return $this->filters;
    }
    public function get_filter(string $filter_type): Filter_Interface
    {
        return $this->filters[$filter_type];
    }
}