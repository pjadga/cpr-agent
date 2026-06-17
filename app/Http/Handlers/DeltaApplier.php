<?php

namespace App\Http\Handlers;

/**
 * Applies dot-notation deltas to an array.
 * Handles special cases: items.+ID (append), items.ID.field (deep update), null values (delete).
 */
class DeltaApplier
{
    /**
     * Apply a set of deltas to the target array in-place.
     */
    public function apply(array &$target, array $deltas): void
    {
        foreach ($deltas as $path => $value) {
            $this->applyPath($target, $path, $value);
        }
    }

    private function applyPath(array &$array, string $path, mixed $value): void
    {
        // Special case: items.+ID (append item to array)
        if (str_starts_with($path, 'items.+')) {
            $this->applyAppendItem($array, substr($path, 7), $value);
            return;
        }

        $keys = explode('.', $path);

        // Special case: items.ID.field... (deep update on existing item)
        if (count($keys) >= 3 && $keys[0] === 'items') {
            $this->applyItemField($array, $keys, $value);
            return;
        }

        // Special case: items.ID (replace/ delete entire item)
        if (count($keys) === 2 && $keys[0] === 'items') {
            $this->applyReplaceItem($array, $keys[1], $value);
            return;
        }

        // Standard dot-notation for non-items paths
        $this->applyDotPath($array, $keys, $value);
    }

    /**
     * Append or update an item in the items array by ID.
     */
    private function applyAppendItem(array &$array, string $itemId, mixed $value): void
    {
        if (! isset($array['items']) || ! is_array($array['items'])) {
            $array['items'] = [];
        }

        // Update existing or append new
        foreach ($array['items'] as $index => &$item) {
            if (isset($item['_id']) && $item['_id'] === $itemId) {
                $array['items'][$index] = $value;
                return;
            }
        }

        $array['items'][] = $value;
    }

    /**
     * Deep update a field on an existing item.
     */
    private function applyItemField(array &$array, array $keys, mixed $value): void
    {
        $itemId = $keys[1];
        $remainingKeys = array_slice($keys, 2);

        foreach ($array['items'] as &$item) {
            if (! isset($item['_id']) || $item['_id'] !== $itemId) {
                continue;
            }

            $ref = &$item;
            foreach ($remainingKeys as $i => $iValue) {
                $key = $iValue;

                if ($i === count($remainingKeys) - 1) {
                    // Last key: set or delete value
                    if ($value === null) {
                        unset($ref[$key]);
                    } else {
                        $ref[$key] = $value;
                    }
                } else {
                    // Navigate deeper, creating arrays as needed
                    if (! isset($ref[$key]) || ! is_array($ref[$key])) {
                        $ref[$key] = [];
                    }
                    $ref = &$ref[$key];
                }
            }

            return;
        }
    }

    /**
     * Replace or delete an entire item by ID.
     */
    private function applyReplaceItem(array &$array, string $itemId, mixed $value): void
    {
        if (! isset($array['items']) || ! is_array($array['items'])) {
            return;
        }

        if ($value === null) {
            // Delete item by ID
            foreach ($array['items'] as $index => $item) {
                if (isset($item['_id']) && $item['_id'] === $itemId) {
                    unset($array['items'][$index]);
                    $array['items'] = array_values($array['items']); // Re-index
                    return;
                }
            }
        } else {
            // Replace or add item
            foreach ($array['items'] as $index => &$item) {
                if (isset($item['_id']) && $item['_id'] === $itemId) {
                    $array['items'][$index] = $value;
                    return;
                }
            }

            // Not found — append
            $array['items'][] = $value;
        }
    }

    /**
     * Standard dot-notation path resolution.
     */
    private function applyDotPath(array &$array, array $keys, mixed $value): void
    {
        $ref = &$array;
        foreach ($keys as $i => $iValue) {
            $key = $iValue;

            if ($i === count($keys) - 1) {
                // Last key: set or delete value
                if ($value === null) {
                    unset($ref[$key]);
                } else {
                    $ref[$key] = $value;
                }
            } else {
                // Navigate deeper, creating arrays as needed
                if (! isset($ref[$key]) || ! is_array($ref[$key])) {
                    $ref[$key] = [];
                }
                $ref = &$ref[$key];
            }
        }
    }
}
