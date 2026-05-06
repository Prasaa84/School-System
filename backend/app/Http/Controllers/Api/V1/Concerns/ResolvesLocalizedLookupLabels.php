<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ResolvesLocalizedLookupLabels
{
    /**
     * @param  array<int, string>  $candidates
     */
    protected function buildLocalizedLabelSelect(string $tableAlias, array $candidates, string $alias = 'label'): string
    {
        $orderedColumns = $this->orderedLookupColumns($candidates);
        if ($orderedColumns === []) {
            return "'' as {$alias}";
        }

        $parts = array_map(
            fn (string $column): string => "NULLIF(TRIM({$tableAlias}.{$column}), '')",
            $orderedColumns,
        );

        return 'COALESCE(' . implode(', ', $parts) . ") as {$alias}";
    }

    /**
     * @param  array<int, string>  $labelColumns
     * @return array<string, int>
     */
    protected function buildLookupMap(string $table, string $idColumn, array $labelColumns): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $existingLabelColumns = array_values(array_filter(
            $labelColumns,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));

        if ($existingLabelColumns === []) {
            return [];
        }

        $query = DB::table($table)->select(array_merge([$idColumn], $existingLabelColumns));
        if (Schema::hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->get()->reduce(function (array $carry, object $row) use ($idColumn, $existingLabelColumns): array {
            $id = $row->{$idColumn} ?? null;

            if (!is_numeric($id)) {
                return $carry;
            }

            foreach ($existingLabelColumns as $labelColumn) {
                $label = $this->normalizeLookupValue($row->{$labelColumn} ?? '');
                if ($label !== '') {
                    $carry[$label] = (int) $id;
                }
            }

            return $carry;
        }, []);
    }

    /**
     * @param  array<int, string>  $candidates
     */
    protected function resolveLookupLabelColumn(string $table, array $candidates): ?string
    {
        foreach ($this->orderedLookupColumns($candidates) as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        $fallbacks = [
            'label',
            'name',
            'title',
            str_replace('_id', '', basename($table)),
        ];

        foreach ($fallbacks as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    protected function resolveRequestLanguage(): string
    {
        $raw = trim((string) request()->header('X-App-Language', 'en'));

        return in_array($raw, ['en', 'si', 'ta'], true) ? $raw : 'en';
    }

    protected function normalizeLookupValue(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
        $text = preg_replace('/[\s_]+/u', ' ', $text) ?? $text;

        return function_exists('mb_strtolower')
            ? mb_strtolower($text, 'UTF-8')
            : strtolower($text);
    }

    /**
     * @param  array<int, string>  $candidates
     * @return array<int, string>
     */
    private function orderedLookupColumns(array $candidates): array
    {
        $language = $this->resolveRequestLanguage();

        $preferred = match ($language) {
            'si' => array_values(array_filter($candidates, fn (string $column): bool => str_ends_with($column, '_si'))),
            'ta' => array_values(array_filter($candidates, fn (string $column): bool => str_ends_with($column, '_ta'))),
            default => array_values(array_filter($candidates, fn (string $column): bool => str_ends_with($column, '_en'))),
        };

        $fallback = array_values(array_filter(
            $candidates,
            fn (string $column): bool => !in_array($column, $preferred, true),
        ));

        return array_values(array_unique(array_merge($preferred, $fallback)));
    }
}
