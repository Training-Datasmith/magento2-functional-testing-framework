<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data\Argument\Interpreter;

use Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface;
/**
 * Interpreter of array data type that supports arrays of unlimited depth
 */
class Array_Type implements Interpreter_Interface
{
    /**
     * ArrayType constructor.
     */
    public function __construct(
        /**
         * Interpreter of individual array item
         */
        private readonly Interpreter_Interface $item_interpreter
    )
    {
    }
    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     */
    public function evaluate(array $data): array
    {
        $items = $data['item'] ?? [];
        if (!is_array($items)) {
            throw new \InvalidArgumentException('Array items are expected.');
        }
        $result = [];
        $items = $this->sort_items($items);
        foreach ($items as $item_key => $item_data) {
            $result[$item_key] = $this->item_interpreter->evaluate($item_data);
        }
        return $result;
    }
    /**
     * Sort items by sort order attribute.
     *
     * @return array
     */
    private function sort_items(array $items)
    {
        $sort_order_defined = $this->is_sort_order_defined($items);
        if ($sort_order_defined) {
            $indexed_items = [];
            foreach ($items as $key => $item) {
                $indexed_items[] = ['key' => $key, 'item' => $item];
            }
            uksort($indexed_items, fn($first_item_key, $second_item_key) => $this->compare_items($first_item_key, $second_item_key, $indexed_items));
            // Convert array of sorted items back to initial format
            $items = [];
            foreach ($indexed_items as $indexed_item) {
                $items[$indexed_item['key']] = $indexed_item['item'];
            }
        }
        return $items;
    }
    /**
     * Compare sortOrder of item
     */
    private function compare_items(int $first_item_key, int $second_item_key, array $indexed_items): int
    {
        $first_item = $indexed_items[$first_item_key]['item'];
        $second_item = $indexed_items[$second_item_key]['item'];
        $first_value = 0;
        $second_value = 0;
        if (isset($first_item['sortOrder'])) {
            $first_value = intval($first_item['sortOrder']);
        }
        if (isset($second_item['sortOrder'])) {
            $second_value = intval($second_item['sortOrder']);
        }
        if ($first_value === $second_value) {
            // These keys reflect initial relative position of items.
            // Allows stable sort for items with equal 'sortOrder'
            return $first_item_key < $second_item_key ? -1 : 1;
        }
        return $first_value < $second_value ? -1 : 1;
    }
    /**
     * Determine if a sort order exists for any of the items.
     *
     * @param array $items
     */
    private function is_sort_order_defined($items): bool
    {
        foreach ($items as $item_data) {
            if (isset($item_data['sortOrder'])) {
                return true;
            }
        }
        return false;
    }
}