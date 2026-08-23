<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Setting;

/**
 * Reads/writes settings against the registry in config/settings.php, and
 * resolves a value by cascading scope: branch -> business -> client ->
 * system -> the registry's own default. This is the "scalable settings
 * architecture" the platform builds every future per-tenant configuration
 * screen on top of — a new setting is a registry entry, never a migration.
 */
class SettingsService
{
    /**
     * @return array<string, array{type: string, default: mixed, scopes: list<string>}>
     */
    public function registry(): array
    {
        return config('settings', []);
    }

    /**
     * Resolve a setting's effective value for the most specific scope
     * available, falling back progressively, then to the registry default.
     */
    public function resolve(string $key, ?Branch $branch = null, ?Business $business = null, ?Client $client = null): mixed
    {
        [$branchId, $businessId, $clientId] = $this->deriveScopeIds($branch, $business, $client);

        if ($branchId !== null) {
            $value = Setting::get($key, $clientId, $businessId, $branchId);
            if ($value !== null) {
                return $value;
            }
        }

        if ($businessId !== null) {
            $value = Setting::get($key, $clientId, $businessId, null);
            if ($value !== null) {
                return $value;
            }
        }

        if ($clientId !== null) {
            $value = Setting::get($key, $clientId, null, null);
            if ($value !== null) {
                return $value;
            }
        }

        $value = Setting::get($key, null, null, null);
        if ($value !== null) {
            return $value;
        }

        return $this->registry()[$key]['default'] ?? null;
    }

    /**
     * Write a setting at an exact scope (no cascading on write — a value is
     * always set at precisely the scope you name). Uses the same scope-id
     * derivation as resolve(), so a value written via `business: $business`
     * lands on the exact row a later `resolve(business: $business)` reads.
     */
    public function set(string $key, mixed $value, ?Client $client = null, ?Business $business = null, ?Branch $branch = null): Setting
    {
        $type = $this->registry()[$key]['type'] ?? 'string';
        [$branchId, $businessId, $clientId] = $this->deriveScopeIds($branch, $business, $client);

        return Setting::set($key, $value, $clientId, $businessId, $branchId, $type);
    }

    /**
     * Every registry key applicable to the given scope name
     * ('system'|'client'|'business'|'branch').
     *
     * @return array<string, array{type: string, default: mixed, scopes: list<string>}>
     */
    public function keysForScope(string $scope): array
    {
        return array_filter($this->registry(), fn (array $definition) => in_array($scope, $definition['scopes'], true));
    }

    /**
     * Fills in business/client from branch/business when only the more
     * specific model was passed in, so callers never need to pass every
     * level explicitly — and so set() and resolve() always agree on which
     * row a given ($branch, $business, $client) combination refers to.
     *
     * @return array{0: ?int, 1: ?int, 2: ?int} [branchId, businessId, clientId]
     */
    private function deriveScopeIds(?Branch $branch, ?Business $business, ?Client $client): array
    {
        $business ??= $branch?->business;
        $client ??= $business?->client;

        return [$branch?->id, $business?->id, $client?->id];
    }
}
