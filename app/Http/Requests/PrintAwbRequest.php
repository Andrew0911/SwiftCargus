<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintAwbRequest extends FormRequest
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
            'awb' => 'required|string|size:10|exists:awb',
            'isAdmin' => 'required|in:true,false'
        ];
    }
}
