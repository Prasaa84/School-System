<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StudentStoreRequest extends FormRequest
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
            'index_no' => ['required', 'string', 'regex:/^[0-9]{4,5}$/'],
            'full_name' => ['required', 'string', 'max:255'],
            'name_with_initials' => ['required', 'string', 'max:255'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'phone_no' => ['nullable', 'string', 'max:12'],
            'whatsapp_no' => ['nullable', 'string', 'max:12'],
            'phone_home' => ['nullable', 'string', 'max:12'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'dob' => ['nullable', 'date'],
            'd_o_admission' => ['nullable', 'date'],
            'gender_id' => ['required', 'integer', 'in:1,2'],
            'ethnic_group_id' => ['nullable', 'integer'],
            'religion_id' => ['nullable', 'integer'],
            'grade_id' => ['nullable', 'integer', 'exists:grade_tbl,grade_id'],
            'class_id' => ['nullable', 'integer', 'exists:class_tbl,class_id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'census_id' => ['nullable', 'integer'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_job' => ['nullable', 'string', 'max:255'],
            'father_mobile' => ['nullable', 'string', 'max:12'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_job' => ['nullable', 'string', 'max:255'],
            'mother_mobile' => ['nullable', 'string', 'max:12'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_job' => ['nullable', 'string', 'max:255'],
            'guardian_mobile' => ['nullable', 'string', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'index_no.required' => __('messages.students.validation.index_no_required'),
            'index_no.regex' => __('messages.students.validation.index_no_format'),
            'full_name.required' => __('messages.students.validation.full_name_required'),
            'name_with_initials.required' => __('messages.students.validation.name_with_initials_required'),
            'gender_id.required' => __('messages.students.validation.gender_required'),
            'gender_id.in' => __('messages.students.validation.gender_required'),
            'grade_id.exists' => __('messages.students.validation.grade_invalid'),
            'class_id.exists' => __('messages.students.validation.class_invalid'),
            'year.required' => __('messages.students.validation.year_invalid'),
            'year.between' => __('messages.students.validation.year_invalid'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $gradeId = $this->input('grade_id');
            $classId = $this->input('class_id');

            $hasGrade = is_numeric($gradeId);
            $hasClass = is_numeric($classId);

            if ($hasGrade xor $hasClass) {
                $validator->errors()->add('grade_id', __('messages.students.validation.grade_class_required'));
                $validator->errors()->add('class_id', __('messages.students.validation.grade_class_required'));
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        $response = response()->json([
            'message' => __('messages.request.validation_failed'),
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }
}





