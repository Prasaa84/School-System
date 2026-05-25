<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ModuleCatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var User|null $user */
        $user = request()->attributes->get('auth_user');

        $modules = $this->loadModulesFromDatabase($user);

        if ($modules === null || $modules->isEmpty()) {
            $modules = $this->defaultModulesForRole($user?->role_id);
        }

        return response()->json([
            'modules' => $modules->values()->all(),
        ]);
    }

    private function loadModulesFromDatabase(?User $user): ?Collection
    {
        try {
            $schema = DB::getSchemaBuilder();

            if (!$schema->hasTable('module_tbl')) {
                return null;
            }

            $query = DB::table('module_tbl');

            if ($schema->hasColumn('module_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $rows = $query->get();

            if ($rows->isEmpty()) {
                return collect();
            }

            $normalizedModules = $rows
                ->map(function (object $row): ?array {
                    $item = (array) $row;

                    $key = $this->firstString($item, ['module_key', 'menu_key', 'key', 'slug', 'module_code']);
                    $label = $this->firstString($item, ['module_name', 'menu_name', 'label', 'name', 'title']);
                    $path = $this->firstString($item, ['path', 'route_path', 'route', 'url', 'menu_url']);
                    $sort = $this->firstNumeric($item, ['sort_order', 'display_order', 'order_no', 'position']);

                    if ($label === null) {
                        return null;
                    }

                    if ($key === null) {
                        $key = Str::of($label)->lower()->replace('&', 'and')->slug('-')->value();
                    }

                    if ($key === '') {
                        return null;
                    }

                    return [
                        'key' => $key,
                        'label' => $label,
                        'path' => $path,
                        'sort' => $sort ?? PHP_INT_MAX,
                    ];
                })
                ->filter()
                ->values();

            if ($normalizedModules->isEmpty()) {
                return collect();
            }

            $allowedKeys = $this->loadRoleModuleKeys($user?->role_id);

            if ($allowedKeys !== null) {
                $normalizedModules = $normalizedModules
                    ->filter(fn (array $module): bool => in_array($module['key'], $allowedKeys, true))
                    ->values();
            }

            return $normalizedModules
                ->sortBy([
                    ['sort', 'asc'],
                    ['label', 'asc'],
                ])
                ->values()
                ->map(fn (array $module): array => [
                    'key' => $module['key'],
                    'label' => $module['label'],
                    'path' => $module['path'],
                ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function loadRoleModuleKeys(?int $roleId): ?array
    {
        if ($roleId === null) {
            return null;
        }

        try {
            $schema = DB::getSchemaBuilder();

            $candidateTables = ['role_module_tbl', 'user_role_module_tbl', 'module_role_tbl'];

            foreach ($candidateTables as $table) {
                if (!$schema->hasTable($table)) {
                    continue;
                }

                $query = DB::table($table)->where('role_id', $roleId);

                if ($schema->hasColumn($table, 'is_deleted')) {
                    $query->where('is_deleted', 0);
                }

                if ($schema->hasColumn($table, 'can_view')) {
                    $query->where('can_view', 1);
                }

                $rows = $query->get()->map(fn (object $row): array => (array) $row);
                if ($rows->isEmpty()) {
                    return [];
                }

                $keys = $rows
                    ->map(function (array $row): ?string {
                        $moduleKey = $this->firstString($row, ['module_key', 'menu_key', 'key']);
                        if ($moduleKey !== null) {
                            return $moduleKey;
                        }

                        $moduleId = $this->firstNumeric($row, ['module_id', 'menu_id']);
                        return $moduleId !== null ? "__id__{$moduleId}" : null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                if (empty($keys)) {
                    return [];
                }

                if (str_starts_with((string) $keys[0], '__id__')) {
                    return DB::table('module_tbl')
                        ->whereIn('module_id', collect($keys)->map(fn (string $key): int => (int) str_replace('__id__', '', $key))->all())
                        ->pluck('module_key')
                        ->filter(fn ($key): bool => is_string($key) && $key !== '')
                        ->values()
                        ->all();
                }

                return $keys;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function defaultModulesForRole(?int $roleId): Collection
    {
        $catalog = collect([
            'grades' => ['key' => 'grades', 'label' => 'Grades'],
            'classes' => ['key' => 'classes', 'label' => 'Classes'],
            'staff' => ['key' => 'staff', 'label' => 'Staff'],
            'students' => ['key' => 'students', 'label' => 'Students', 'path' => '/students'],
            'payments' => ['key' => 'payments', 'label' => 'SDS Payments'],
            'reports' => ['key' => 'reports', 'label' => 'Reports'],
        ]);

        $roleMap = [
            1 => ['grades', 'classes', 'students', 'staff', 'payments', 'reports'],
            2 => ['grades', 'classes', 'students', 'staff', 'payments', 'reports'],
            3 => ['grades', 'classes', 'students', 'staff', 'reports'],
            4 => ['payments', 'students', 'grades', 'classes'],
            5 => ['students', 'grades', 'classes'],
            6 => ['students', 'grades', 'classes'],
            7 => ['students', 'payments']
        ];

        $keys = $roleMap[$roleId ?? -1] ?? ['grades', 'grades', 'classes', 'staff', 'payments', 'reports'];

        return collect($keys)
            ->map(fn (string $key): ?array => $catalog->get($key))
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstNumeric(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
