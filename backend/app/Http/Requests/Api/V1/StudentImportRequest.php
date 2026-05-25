<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'census_id' => ['nullable', 'string', 'regex:/^[0-9]{4,7}$/'],
            'create_user_login' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('messages.students.import_file_required'),
            'file.file' => __('messages.students.import_file_required'),
            'file.mimes' => __('messages.students.import_file_invalid'),
        ];
    }
}
