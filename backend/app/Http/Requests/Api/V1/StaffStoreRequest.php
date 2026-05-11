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
            'nick_name' => ['nullable', 'string', 'max:255'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'nic_no' => ['required', 'string', 'max:13'],
            'dob' => ['nullable', 'date'],
            'gender_id' => ['required', 'integer', 'min:1'],
            'civil_status_id' => ['nullable', 'integer', 'min:1'],
            'ethnic_group_id' => ['nullable', 'integer', 'min:1'],
            'religion_id' => ['nullable', 'integer', 'min:1'],
            'phone_home' => ['nullable', 'string', 'max:20'],
            'phone_mobile1' => ['nullable', 'string', 'max:20'],
            'phone_mobile2' => ['nullable', 'string', 'max:20'],
            'vehicle_no1' => ['nullable', 'string', 'max:50'],
            'vehicle_no2' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'desig_id' => ['required', 'integer', 'min:1'],
            'stf_type_id' => ['nullable', 'integer', 'min:1'],
            'stf_status_id' => ['nullable', 'integer', 'min:1'],
            'service_status_id' => ['nullable', 'integer', 'min:1'],
            'subj_med_id' => ['nullable', 'integer', 'min:1'],
            'app_type_id' => ['nullable', 'integer', 'min:1'],
            'app_subj_id' => ['nullable', 'integer', 'min:1'],
            'first_app_dt' => ['nullable', 'date'],
            'start_dt_this_sch' => ['nullable', 'date'],
            'serv_grd_effective_dt' => ['nullable', 'date'],
            'stf_no' => ['nullable', 'string', 'max:50'],
            'salary_no' => ['nullable', 'string', 'max:50'],
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
