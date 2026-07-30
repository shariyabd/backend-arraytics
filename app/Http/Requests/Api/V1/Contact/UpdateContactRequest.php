<?php

namespace App\Http\Requests\Api\V1\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30', 'regex:/^\+?(?=(?:.*\d){7,})[0-9\s\-()]{7,}$/'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'url', 'max:255'],
            'gender' => ['sometimes', 'required', 'string', Rule::in(Contact::GENDERS)],
            'age' => ['sometimes', 'required', 'integer', 'min:'.Contact::MIN_AGE, 'max:'.Contact::MAX_AGE],
            'nationality' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
