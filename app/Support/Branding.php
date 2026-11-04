<?php

namespace App\Support;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Storage;

/**
 * Platform (company) branding set by the Super Admin on the System Settings
 * page — name, logo, favicon, office address and support contacts — used
 * by every Blade layout (admin panel, login pages) and the dashboard.
 * Bound as a singleton, so settings are read at most once per request.
 */
class Branding
{
    private ?array $values = null;

    public function __construct(private readonly SettingsService $settings) {}

    public function name(): string
    {
        return (string) ($this->get('app_name') ?: config('app.name', 'Universal POS SaaS'));
    }

    public function logoUrl(): ?string
    {
        return $this->fileUrl($this->get('app_logo'));
    }

    public function faviconUrl(): ?string
    {
        return $this->fileUrl($this->get('app_favicon'));
    }

    public function officeAddress(): ?string
    {
        return $this->get('office_address') ?: null;
    }

    public function supportEmail(): ?string
    {
        return $this->get('support_email') ?: null;
    }

    public function supportPhone(): ?string
    {
        return $this->get('support_phone') ?: null;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name())) ?: [];
        $first = mb_substr($words[0] ?? 'P', 0, 1);
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    private function get(string $key): mixed
    {
        if ($this->values === null) {
            $this->values = [];
            foreach (['app_name', 'app_logo', 'app_favicon', 'office_address', 'support_email', 'support_phone'] as $k) {
                try {
                    $this->values[$k] = $this->settings->resolve($k);
                } catch (\Throwable) {
                    // DB not migrated yet (fresh install / error pages) — fall back to defaults.
                    $this->values[$k] = null;
                }
            }
        }

        return $this->values[$key] ?? null;
    }

    private function fileUrl(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
