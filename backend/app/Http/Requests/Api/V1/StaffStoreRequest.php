<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StaffStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('service_status_is_current');

        if ($value === null) {
            return;
        }

        $normalized = match (strtolower(trim((string) $value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $value,
        };

        $this->merge([
            'service_status_is_current' => $normalized,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'],
            'census_id' => ['nullable', 'string', 'regex:/^[0-9]{4,7}$/'],
            'full_name' => ['required', 'string', 'max:255'],
            'name_with_ini' => ['required', 'string', 'max:255'],
            'nick_name' => ['nullable', 'string', 'max:30'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'nic_no' => ['required', 'string', 'max:12'],
            'dob' => ['nullable', 'date'],
            'gender_id' => ['required', 'integer', 'min:1'],
            'civil_status_id' => ['nullable', 'integer', 'min:1'],
            'ethnic_group_id' => ['nullable', 'integer', 'min:1'],
            'religion_id' => ['nullable', 'integer', 'min:1'],
            'phone_home' => ['nullable', 'string', 'max:10'],
            'phone_mobile1' => ['nullable', 'string', 'max:10'],
            'phone_mobile2' => ['nullable', 'string', 'max:10'],
            'vehicle_no1' => ['nullable', 'string', 'max:15'],
            'vehicle_no2' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:60'],
            'edu_q_id' => ['nullable', 'integer', 'min:1'],
            'prof_q_id' => ['nullable', 'integer', 'min:1'],
            'desig_id' => ['required', 'integer', 'min:1'],
            'serv_grd_id' => ['nullable', 'integer', 'min:1'],
            'sec_id' => ['nullable', 'integer', 'min:1'],
            'sec_role_id' => ['nullable', 'integer', 'min:1'],
            'stf_type_id' => ['nullable', 'integer', 'min:1'],
            'stf_status_id' => ['nullable', 'integer', 'min:1'],
            'service_status_id' => ['nullable', 'integer', 'min:1'],
            'subj_med_id' => ['nullable', 'integer', 'min:1'],
            'app_type_id' => ['nullable', 'integer', 'min:1'],
            'app_subj_id' => ['nullable', 'integer', 'min:1'],
            'first_app_dt' => ['nullable', 'date'],
            'start_dt_this_sch' => ['nullable', 'date'],
            'serv_grd_effective_dt' => ['nullable', 'date'],
            'sal_incr_dt' => ['nullable', 'date'],
            'stf_no' => ['nullable', 'integer', 'min:1'],
            'salary_no' => ['nullable', 'integer', 'min:1'],
            'main_task_id' => ['nullable', 'integer', 'min:1'],
            'main_task_section_id' => ['nullable', 'integer', 'min:1'],
            'main_task_subject_id' => ['nullable', 'integer', 'min:1'],
            'second_task_id' => ['nullable', 'integer', 'min:1'],
            'second_task_section_id' => ['nullable', 'integer', 'min:1'],
            'second_task_subject_id' => ['nullable', 'integer', 'min:1'],
            'service_status_institute' => ['nullable', 'string', 'max:255'],
            'service_status_province_id' => ['nullable', 'integer', 'min:1'],
            'service_status_zone_id' => ['nullable', 'integer', 'min:1'],
            'service_status_school_census_id' => ['nullable', 'string', 'regex:/^[0-9]{4,7}$/'],
            'service_status_custom_institute' => ['nullable', 'string', 'max:255'],
            'service_status_effective_date' => ['nullable', 'date'],
            'service_status_period' => ['nullable', 'string', 'max:30'],
            'service_status_is_current' => ['nullable', 'boolean'],
            'create_user_login' => ['nullable', 'boolean'],
            'login_role_id' => ['nullable', 'integer', 'min:1'],
            'profile_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'full_name.required' => 'Full name is required.',
            'name_with_ini.required' => 'Name with initials is required.',
            'nic_no.required' => 'NIC is required.',
            'gender_id.required' => 'Gender is required.',
            'desig_id.required' => 'Designation is required.',
            'census_id.regex' => 'Selected school is invalid.',
            'service_status_school_census_id.regex' => 'Selected attached school is invalid.',
        ];
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        $response = response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }
}
