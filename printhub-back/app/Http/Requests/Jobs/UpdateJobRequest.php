<?php

namespace App\Http\Requests\Jobs;

use App\Rules\ValidStlFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateJobRequest extends FormRequest
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
            'fileName' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:BACKLOG,TODO,INPROGRESS,APPROVAL,COMPLETED',
            'estimatedTimeMinutes' => 'sometimes|integer|min:0',
            'estimatedCost' => ['sometimes','numeric','regex:/^\d+(\.\d{1,2})?$/'],
            'quantity' => 'sometimes|integer|min:1',
            'filament_id' => 'sometimes|integer|exists:filaments,id',
            'materialWeightGrams' => ['sometimes', 'numeric', 'gt:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'filaments' => 'sometimes|array|min:1',
            'filaments.*.filament_id' => 'required_with:filaments|integer|distinct|exists:filaments,id',
            'filaments.*.weight_grams' => ['required_with:filaments', 'numeric', 'gt:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'layers' => 'sometimes|integer|min:1',
            'stl_file' => ['sometimes', 'file', 'max:51200', new ValidStlFile],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasSingleFilament = $this->has('filament_id');
            $hasWeight = $this->has('materialWeightGrams');

            if ($hasSingleFilament !== $hasWeight) {
                $validator->errors()->add('filament_id', 'filament_id e materialWeightGrams devem ser enviados juntos.');
            }

            if ($this->has('filaments') && ($hasSingleFilament || $hasWeight)) {
                $validator->errors()->add('filaments', 'Use filaments ou filament_id, nunca os dois formatos juntos.');
            }
        });
    }
}
