<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('expenses.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
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

                return;
            }

            if ($user->branch_id !== null && $user->branch_id !== $branch->id) {
                $validator->errors()->add('branch_id', 'The selected branch is invalid.');
            }
        });
    }
}
