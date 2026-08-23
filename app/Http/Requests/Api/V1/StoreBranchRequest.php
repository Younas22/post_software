<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesBusinessScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Token-authed equivalent of Admin\StoreBranchRequest — that one is nested
 * under a Business route parameter; this one resolves the business from the
 * actor the same way every other Api/V1 store request does (see
 * ValidatesBusinessScope), since a web POS settings screen has no
 * Admin-panel-style business selector in its URL.
 */
class StoreBranchRequest extends FormRequest
{
    use ValidatesBusinessScope;

    public function authorize(): bool
    {
        return $this->user()->is_super_admin || $this->user()->hasPermission('businesses.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_id' => $this->businessIdRules(),
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_main' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateResolvedBusiness($validator));
    }
}
