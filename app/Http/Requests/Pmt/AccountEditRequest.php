<?php

namespace App\Http\Requests\Pmt;

use Illuminate\Foundation\Http\FormRequest;

class AccountEditRequest extends FormRequest
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
            'userId'             => 'required|exists:users,id',
            'roleId'             => 'required|exists:roles,id',
            'active'             => 'required|boolean',
            'office_id_assign'   => 'nullable|array',
            'office_id_assign.*' => 'nullable|exists:offices,id',
            'prefix'             =>  'nullable|string',
            'suffix'             =>  'nullable|string',
        ];
    }
}
