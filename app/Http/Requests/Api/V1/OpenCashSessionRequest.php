<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('cash_sessions.operate');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $branch = Branch::query()->find($this->integer('branch_id'));

            if (! $branch || $branch->business->client_id !== $user->client_id) {
                $validator->errors()->add('branch_id', 'The selected branch is invalid.');

                return;
            }

            if ($user->business_id !== null && $user->business_id !== $branch->business_id) {
                $validator->errors()->add('branch_id', 'The selected branch is invalid.');
            }

            if ($user->branch_id !== null && $user->branch_id !== $branch->id) {
                $validator->errors()->add('branch_id', 'The selected branch is invalid.');
            }
        });
    }
}
