<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StorePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'base_price'         => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'included_units'     => ['required', 'integer', 'min:0', 'max:18446744073709551615'],
            'overage_rate'       => ['required', 'numeric', 'min:0', 'max:9999.9999'],
            'billing_cycle_days' => ['required', 'integer', 'min:1', 'max:65535'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }
}
