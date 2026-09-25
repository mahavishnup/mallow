<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class RecordUsageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * API-key authentication is enforced by the ResolveApiKey middleware;
     * requests reaching validation are already merchant-authenticated.
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
            'customer_id'     => ['required', 'integer', 'exists:customers,id'],
            'usage_date'      => ['required', 'date', 'before_or_equal:today'],
            'quantity'        => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'type'            => ['required', 'string', 'max:64', 'alpha_dash'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
