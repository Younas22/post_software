<?php

namespace App\Http\Requests\Admin;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class ExtendSubscriptionRequest extends FormRequest
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
            'ends_at' => ['required', 'date', 'after:today'],
        ];
    }
}
