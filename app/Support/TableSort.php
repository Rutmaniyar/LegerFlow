<?php

declare(strict_types=1);

namespace App\Support;

final class TableSort
{
    /**
     * @param array<string, array{column: string, default: 'asc'|'desc', nulls_last?: bool}> $columns
     * @return array{key: string, column: string, direction: 'asc'|'desc', nulls_last: bool}
     */
    public static function resolve(mixed $requestedKey, mixed $requestedDirection, array $columns, string $defaultKey): array
    {
        if (!isset($columns[$defaultKey])) {
            throw new \LogicException('The default sort key must exist in the allowed columns.');
        }

        $hasRequestedKey = is_string($requestedKey) && $requestedKey !== '';
        $candidateDirection = strtolower((string) $requestedDirection);
        $hasRequestedDirection = $requestedDirection !== null && $requestedDirection !== '';
        $validKey = $hasRequestedKey && isset($columns[$requestedKey]);
        $validDirection = in_array($candidateDirection, ['asc', 'desc'], true);

        if (($hasRequestedKey && !$validKey) || ($hasRequestedDirection && !$validDirection)) {
            $key = $defaultKey;
            $direction = $columns[$defaultKey]['default'];
        } else {
            $key = $validKey ? $requestedKey : $defaultKey;
            $direction = $validDirection ? $candidateDirection : $columns[$key]['default'];
        }

        return [
            'key' => $key,
            'column' => $columns[$key]['column'],
            'direction' => $direction,
            'nulls_last' => (bool) ($columns[$key]['nulls_last'] ?? false),
        ];
    }
}
