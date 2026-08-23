<?php

namespace App\Http\Requests\Admin;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Client $client */
        $client = $this->route('client');

        return $this->user()->can('update', $client);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'suspended', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
