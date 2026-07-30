<?php

namespace App\Services\Contact;

use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ContactService
{
    public function list(array $filters, int $perPage, int $ownerId): LengthAwarePaginator
    {
        $query = Contact::query()->where('created_by', $ownerId);

        if (! empty($filters['search'])) {
            $search = addcslashes($filters['search'], '%_\\');
            $like = "%{$search}%";
            // Explicit ESCAPE so the escaping works on SQLite (tests) as well as MySQL.
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw("name LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("email LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("phone LIKE ? ESCAPE '\\'", [$like]);
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
