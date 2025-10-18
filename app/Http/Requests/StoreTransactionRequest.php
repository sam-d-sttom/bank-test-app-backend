<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'wallet_id' => ['required','integer','exists:wallets,id'],
            'type' => ['required', Rule::in(['credit','debit'])],
            'amount' => ['required','numeric','gt:0'],
            'reference' => ['required','string','max:255','unique:transactions,reference'],
            'idempotency_key' => ['required','string','max:255'],
        ];
    }
}
