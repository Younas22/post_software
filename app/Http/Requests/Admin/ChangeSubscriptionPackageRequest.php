<?php

namespace App\Http\Requests\Admin;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class ChangeSubscriptionPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Subscription $subscription */
        $subscription = $this->route('subscription');

        return $this->user()->can('manage', $subscription);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ];
    }
}
