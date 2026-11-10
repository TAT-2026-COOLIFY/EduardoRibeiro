<?php

namespace App\Http\Requests\Filaments;

use App\Models\Filament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFilamentRequest extends FormRequest
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
            'brand' => 'sometimes|string|max:45',
            'type' => 'sometimes|string|max:20',
            'colorName' => 'sometimes|string|max:45',
            'colorHex' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'pricePerKg' => ['sometimes', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'initialQuantity' => ['sometimes', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,3})?$/'],
            'currentQuantity' => ['sometimes', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,3})?$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->hasAny(array_keys($this->rules()))) {
                $validator->errors()->add('filament', 'Informe ao menos um campo para atualizar.');
            }

            $filament = Filament::find($this->route('filament'));
            $initialQuantity = $this->input('initialQuantity', $filament?->initialQuantity);
            $currentQuantity = $this->input('currentQuantity', $filament?->currentQuantity);

            if ((float) $currentQuantity > (float) $initialQuantity) {
                $validator->errors()->add('currentQuantity', 'O estoque atual nao pode ser maior que o estoque inicial.');
            }
        });
    }
}
