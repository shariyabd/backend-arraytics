<?php

namespace App\Services\Contact;

use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ContactService
{
    /**
     * @param  array{search?: ?string, gender?: ?string, nationality?: ?string, min_age?: ?int, max_age?: ?int}  $filters
     * @return LengthAwarePaginator<int, Contact>
     */
    public function list(array $filters, int $perPage, int $ownerId): LengthAwarePaginator
    {
        $query = Contact::query()->where('created_by', $ownerId);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (! empty($filters['nationality'])) {
            $query->where('nationality', $filters['nationality']);
        }

        if (isset($filters['min_age'])) {
            $query->where('age', '>=', $filters['min_age']);
        }

        if (isset($filters['max_age'])) {
            $query->where('age', '<=', $filters['max_age']);
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, int $ownerId): Contact
    {
        $contact = new Contact($attributes);
        $contact->created_by = $ownerId;
        $contact->save();

        Log::info('Contact created.', ['contact_id' => $contact->id, 'owner_id' => $ownerId]);

        return $contact;
    }

    public function find(int $id, int $ownerId): Contact
    {
        return Contact::query()->where('created_by', $ownerId)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Contact $contact, array $attributes): Contact
    {
        $contact->fill($attributes);
        $contact->save();

        Log::info('Contact updated.', ['contact_id' => $contact->id, 'owner_id' => $contact->created_by]);

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $ownerId = $contact->created_by;
        $contactId = $contact->id;

        $contact->delete();

        Log::info('Contact deleted.', ['contact_id' => $contactId, 'owner_id' => $ownerId]);
    }
}
