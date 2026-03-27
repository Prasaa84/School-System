<?php

return [
    'auth' => [
        'unauthorized' => 'Unauthorized.',
        'forbidden' => 'Forbidden.',
        'invalid_credentials' => 'Invalid username or password.',
        'user_not_found' => 'User not found.',
    ],
    'request' => [
        'validation_failed' => 'Input Validation failed.',
        'invalid_payload' => 'Invalid request payload.',
    ],
    'students' => [
        'list_failed' => 'Unable to load students list.',
        'create_failed' => 'Unable to add student.',
        'create_success' => 'Student added successfully.',
        'update_failed' => 'Unable to update student.',
        'update_success' => 'Student updated successfully.',
        'delete_failed' => 'Unable to delete student.',
        'delete_success' => 'Student deleted successfully.',
        'not_found' => 'Student not found.',
        'already_exists' => 'Student with admission number :index_no already exists.',
        'census_required' => 'Unable to determine school census ID.',
        'grade_class_mismatch' => 'Selected grade and class do not match.',
        'validation' => [
            'index_no_required' => 'Admission number is required.',
            'index_no_format' => 'Admission number must be 4 to 5 digits.',
            'full_name_required' => 'Full name is required.',
            'name_with_initials_required' => 'Name with initials is required.',
            'gender_required' => 'Gender is required.',
            'grade_invalid' => 'Selected grade is invalid.',
            'class_invalid' => 'Selected class is invalid.',
            'year_invalid' => 'Academic year must be between 2000 and 2100.',
            'grade_class_required' => 'Please select both grade and class, or leave both empty.',
        ],
    ],
    'staff' => [
        'list_failed' => 'Unable to load staff list.',
        'summary_failed' => 'Unable to load staff report summary.',
    ],
    'grades' => [
        'not_found' => 'Grade row not found.',
        'invalid_year' => 'Invalid target year.',
        'init_failed' => 'Failed to initialize year data.',
    ],
    'classes' => [
        'not_found' => 'Class row not found.',
    ],
    'generic' => [
        'not_found' => 'Resource not found.',
        'server_error' => 'Something went wrong. Please try again.',
    ],
];




