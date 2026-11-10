<?php

namespace App\Http\Requests\Filaments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFilamentRequest extends FormRequest
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
            'brand' => 'required|string|max:45',
            'type' => 'required|string|max:20',
            'colorName' => 'required|string|max:45',
            'colorHex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'pricePerKg' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'initialQuantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,3})?$/'],
            'currentQuantity' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,3})?$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ((float) $this->input('currentQuantity', 0) > (float) $this->input('initialQuantity', 0)) {
                $validator->errors()->add('currentQuantity', 'O estoque atual nao pode ser maior que o estoque inicial.');
            }
        });
    }
}
