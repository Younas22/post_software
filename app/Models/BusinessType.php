<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class BusinessType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'theme',
        'terminology',
        'modules',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'theme' => 'array',
            'terminology' => 'array',
            'modules' => 'array',
        ];
    }

    /**
     * @return HasMany<Business, $this>
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    /**
     * Theme merged over config('business_types.defaults.theme') — never
     * missing a key, and blank strings fall back to the default.
     *
     * @return array<string, mixed>
     */
    public function resolvedTheme(): array
    {
        $theme = self::mergeFilled(config('business_types.defaults.theme', []), $this->theme ?? []);
        $theme['login_highlights'] = array_values(array_filter((array) ($theme['login_highlights'] ?? []), fn ($line) => trim((string) $line) !== ''));
        $theme['login_image_url'] = ! empty($theme['login_image']) ? Storage::disk('public')->url($theme['login_image']) : null;

        return $theme;
    }

    /**
     * @return array<string, string>
     */
    public function resolvedTerminology(): array
    {
        return self::mergeFilled(config('business_types.defaults.terminology', []), $this->terminology ?? []);
    }

    /**
     * @return array{hidden: list<string>, enabled: list<string>}
     */
    public function resolvedModules(): array
    {
        $modules = $this->modules ?? [];

        return [
            'hidden' => array_values(array_intersect((array) ($modules['hidden'] ?? []), array_keys(config('business_types.hideable_modules', [])))),
            'enabled' => array_values(array_intersect((array) ($modules['enabled'] ?? []), array_keys(config('business_types.optional_modules', [])))),
        ];
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->resolvedModules()['enabled'], true);
    }

    /**
     * Everything a client app needs to dress itself up for this trade —
     * returned by GET /config (logged in) and GET /public/branding/{slug}
     * (the branded login page).
     *
     * @return array<string, mixed>
     */
    public function brandingPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'description' => $this->description,
            'theme' => $this->resolvedTheme(),
            'terminology' => $this->resolvedTerminology(),
            'modules' => $this->resolvedModules(),
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function mergeFilled(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
                continue;
            }
            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
