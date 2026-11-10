<?php

namespace App\Http\Requests\Jobs;

use App\Rules\ValidStlFile;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // #rules
    public function rules(): array
    {
        return [
            'fileName'             => 'required|string|max:255',
            'status'               => 'required|string|in:BACKLOG,TODO,INPROGRESS,APPROVAL,COMPLETED',
            'filament_id'          => 'required_without:filaments|integer|exists:filaments,id',
            'materialWeightGrams'  => ['required_with:filament_id', 'numeric', 'gt:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'filaments'            => 'required_without:filament_id|array|min:1',
            'filaments.*.filament_id' => 'required|integer|distinct|exists:filaments,id',
            'filaments.*.weight_grams' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'estimatedTimeMinutes' => 'required|integer|min:1',
            'estimatedCost'        => 'nullable|numeric|min:0',
            'quantity'             => 'required|integer|min:1',
            'layers'               => 'required|integer|min:1',
            'stl_file'             => ['required', 'file', 'max:51200', new ValidStlFile],
        ];
    }

    // #messages
    public function messages(): array
    {
        return [
            'filament_id.exists'         => 'O filamento selecionado não foi encontrado no sistema.',
            'materialWeightGrams.min'    => 'O peso utilizado deve ser maior que zero.',
            'stl_file.required'          => 'Você precisa enviar o arquivo STL da impressão.',
            'stl_file.file'              => 'O formato enviado é inválido.',
            'stl_file.extensions'        => 'O arquivo enviado deve possuir a extensão .stl.',
            'stl_file.max'               => 'O arquivo STL não pode ser maior que 50MB.'
        ];
    }
}
