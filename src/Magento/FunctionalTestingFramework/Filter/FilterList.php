<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\FunctionalTestingFramework\Filter;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;

/**
 * Class FilterList has a list of filters.
 */
class FilterList
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
        foreach ($filters as $filterType => $filterValue) {
            $className = "Magento\FunctionalTestingFramework\Filter\Test\\" . ucfirst((string) $filterType);
            if (!class_exists($className)) {
                throw new TestFrameworkException("Filter type '" . $filterType . "' do not exist.");
            }
            $this->filters[$filterType] = new $className($filterValue);
        }
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFilter(string $filterType): FilterInterface
    {
        return $this->filters[$filterType];
    }
}
