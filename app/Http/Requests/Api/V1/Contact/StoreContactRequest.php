<?php

namespace App\Http\Requests\Api\V1\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?(?=(?:.*\d){7,})[0-9\s\-()]{7,}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'url', 'max:255'],
            'gender' => ['required', 'string', Rule::in(Contact::GENDERS)],
            'age' => ['required', 'integer', 'min:'.Contact::MIN_AGE, 'max:'.Contact::MAX_AGE],
            'nationality' => ['required', 'string', 'max:255'],
        ];
    }
}
