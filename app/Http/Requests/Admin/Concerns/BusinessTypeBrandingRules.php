<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Validation\Rule;

/**
 * Validation for the per-type branding sections of the Business Type form
 * (theme, branded login page, terminology, modules) — shared by the store
 * and update requests.
 */
trait BusinessTypeBrandingRules
{
    /**
     * @return array<string, mixed>
     */
    protected function brandingRules(): array
    {
        $rules = [
            'icon' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'theme' => ['nullable', 'array'],
            'theme.primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.sidebar_style' => ['nullable', Rule::in(array_keys(config('business_types.sidebar_styles')))],
            'theme.login_headline' => ['nullable', 'string', 'max:120'],
            'theme.login_tagline' => ['nullable', 'string', 'max:300'],
            'theme.login_highlights' => ['nullable', 'string', 'max:1000'],
            'login_image' => ['nullable', 'image', 'max:4096'],
            'remove_login_image' => ['nullable', 'boolean'],
            'terminology' => ['nullable', 'array'],
            'modules' => ['nullable', 'array'],
            'modules.hidden' => ['nullable', 'array'],
            'modules.hidden.*' => [Rule::in(array_keys(config('business_types.hideable_modules')))],
            'modules.enabled' => ['nullable', 'array'],
            'modules.enabled.*' => [Rule::in(array_keys(config('business_types.optional_modules')))],
        ];

        foreach (array_keys(config('business_types.defaults.terminology')) as $key) {
            $rules["terminology.{$key}"] = ['nullable', 'string', 'max:40'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'theme.primary_color.regex' => 'The brand colour must be a hex colour like #16a34a.',
        ];
    }
}
