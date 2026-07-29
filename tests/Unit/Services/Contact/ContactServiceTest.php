<?php

namespace Tests\Unit\Services\Contact;

use App\Models\Contact;
use App\Models\User;
use App\Services\Contact\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContactService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ContactService;
    }

    public function test_create_stamps_owner_from_supplied_identity(): void
    {
        $owner = User::factory()->create();

        $contact = $this->service->create([
            'name' => 'Ada Lovelace',
            'phone' => '+1234567890',
            'email' => 'ada@example.com',
            'gender' => 'Female',
            'age' => 36,
            'nationality' => 'United Kingdom',
        ], $owner->id);

        $this->assertSame($owner->id, $contact->created_by);
        $this->assertDatabaseHas('contacts', [
            'email' => 'ada@example.com',
            'created_by' => $owner->id,
        ]);
    }

    public function test_create_ignores_client_supplied_owner(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $contact = $this->service->create([
            'name' => 'Grace Hopper',
            'phone' => '+1234567890',
            'email' => 'grace@example.com',
            'gender' => 'Female',
            'age' => 40,
            'nationality' => 'United States',
            'created_by' => $attacker->id,
        ], $owner->id);

        $this->assertSame($owner->id, $contact->created_by);
    }

    public function test_update_leaves_owner_unchanged(): void
    {
        $owner = User::factory()->create();
        $contact = Contact::factory()->create(['created_by' => $owner->id, 'name' => 'Old Name']);

        $updated = $this->service->update($contact, [
            'name' => 'New Name',
            'created_by' => User::factory()->create()->id,
        ]);

        $this->assertSame('New Name', $updated->name);
        $this->assertSame($owner->id, $updated->created_by);
    }

    public function test_delete_removes_the_contact(): void
    {
        $contact = Contact::factory()->create();

        $this->service->delete($contact);

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_find_returns_the_contact(): void
    {
        $contact = Contact::factory()->create();

        $this->assertTrue($contact->is($this->service->find($contact->id)));
    }

    public function test_list_applies_search_before_pagination(): void
    {
        Contact::factory()->create(['name' => 'Alice Match']);
        Contact::factory()->create(['name' => 'Bob Other', 'email' => 'bob@other.test', 'phone' => '+19999999999']);

        $result = $this->service->list(['search' => 'Match'], 15);

        $this->assertSame(1, $result->total());
        $this->assertSame('Alice Match', $result->items()[0]->name);
    }

    public function test_list_search_is_case_insensitive_and_matches_email_and_phone(): void
    {
        Contact::factory()->create(['name' => 'Zed', 'email' => 'findme@example.com', 'phone' => '+15550001111']);

        $this->assertSame(1, $this->service->list(['search' => 'FINDME'], 15)->total());
        $this->assertSame(1, $this->service->list(['search' => '5550001111'], 15)->total());
    }

    public function test_list_filters_are_combinable(): void
    {
        Contact::factory()->create(['gender' => 'Female', 'nationality' => 'Canada', 'age' => 30]);
        Contact::factory()->create(['gender' => 'Male', 'nationality' => 'Canada', 'age' => 30]);
        Contact::factory()->create(['gender' => 'Female', 'nationality' => 'Canada', 'age' => 70]);

        $result = $this->service->list([
            'gender' => 'Female',
            'nationality' => 'Canada',
            'min_age' => 25,
            'max_age' => 40,
        ], 15);

        $this->assertSame(1, $result->total());
    }

    public function test_list_respects_the_requested_page_size(): void
    {
        Contact::factory()->count(5)->create();

        $result = $this->service->list([], 2);

        $this->assertSame(2, $result->perPage());
        $this->assertCount(2, $result->items());
        $this->assertSame(5, $result->total());
    }
}
