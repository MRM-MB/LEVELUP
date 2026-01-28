<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // already behind the IsAdmin middleware, leave it true
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:100'],
            'surname'           => ['nullable', 'string', 'max:100'],
            'username'          => ['required', 'string', 'max:60', 'unique:users,username'],
            'date_of_birth'     => ['nullable', 'date'],
            'password'          => [
                'required',
                'string',
                'min:16',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^()_+\-=\[\]{};:\'"\\|,.<>\/~`]).+$/'
            ],
            'sitting_position'  => ['nullable', 'integer', 'between:0,65535'],
            'standing_position' => ['nullable', 'integer', 'between:0,65535'],
            'desk_id'           => ['nullable', 'exists:desks,desk_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Password must be at least 16 characters long.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}
