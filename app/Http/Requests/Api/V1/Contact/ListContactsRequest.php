<?php

namespace App\Http\Requests\Api\V1\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', 'string', Rule::in(Contact::GENDERS)],
            'nationality' => ['sometimes', 'string', 'max:255'],
            'min_age' => ['sometimes', 'integer', 'min:'.Contact::MIN_AGE, 'max:'.Contact::MAX_AGE],
            'max_age' => ['sometimes', 'integer', 'min:'.Contact::MIN_AGE, 'max:'.Contact::MAX_AGE, 'gte:min_age'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::maxPerPage()],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->filled('search') ? $this->string('search')->value() : null,
            'gender' => $this->filled('gender') ? $this->string('gender')->value() : null,
            'nationality' => $this->filled('nationality') ? $this->string('nationality')->value() : null,
            'min_age' => $this->filled('min_age') ? $this->integer('min_age') : null,
            'max_age' => $this->filled('max_age') ? $this->integer('max_age') : null,
        ];
    }

    public function perPage(): int
    {
        $default = (int) config('contacts.pagination.default_per_page', 15);

        return $this->filled('per_page') ? $this->integer('per_page') : $default;
    }

    private static function maxPerPage(): int
    {
        return (int) config('contacts.pagination.max_per_page', 100);
    }
}
