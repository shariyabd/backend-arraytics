<?php

namespace Tests\Feature\Api\V1\Contact;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user, authenticate as them, and return them.
     */
    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'phone' => '+1234567890',
            'email' => 'ada@example.com',
            'website' => 'https://ada.example.com',
            'gender' => 'Female',
            'age' => 36,
            'nationality' => 'United Kingdom',
        ], $overrides);
    }

    public function test_all_contact_endpoints_require_authentication(): void
    {
        $contact = Contact::factory()->create();

        $this->getJson('/api/v1/contacts')->assertStatus(401);
        $this->postJson('/api/v1/contacts', $this->validPayload())->assertStatus(401);
        $this->getJson("/api/v1/contacts/{$contact->id}")->assertStatus(401);
        $this->putJson("/api/v1/contacts/{$contact->id}", $this->validPayload())->assertStatus(401);
        $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertStatus(401);
    }

    public function test_authenticated_user_can_create_a_contact(): void
    {
        $user = $this->actingUser();

        $response = $this->postJson('/api/v1/contacts', $this->validPayload());

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Contact created.',
                'data' => [
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                    'created_by' => $user->id,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'phone', 'email', 'website', 'gender', 'age', 'nationality', 'created_by', 'created_at'],
            ]);

        $this->assertDatabaseHas('contacts', ['email' => 'ada@example.com', 'created_by' => $user->id]);
    }

    public function test_created_by_is_taken_from_the_token_and_client_value_is_ignored(): void
    {
        $user = $this->actingUser();
        $attacker = User::factory()->create();

        $response = $this->postJson('/api/v1/contacts', $this->validPayload([
            'created_by' => $attacker->id,
        ]));

        $response->assertCreated()->assertJsonPath('data.created_by', $user->id);
        $this->assertDatabaseHas('contacts', ['email' => 'ada@example.com', 'created_by' => $user->id]);
    }

    public function test_website_is_optional(): void
    {
        $this->actingUser();

        $response = $this->postJson('/api/v1/contacts', $this->validPayload(['website' => null]));

        $response->assertCreated()->assertJsonPath('data.website', null);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingUser();

        $response = $this->postJson('/api/v1/contacts', []);

        $response->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'The given data was invalid.'])
            ->assertJsonValidationErrors(['name', 'phone', 'email', 'gender', 'age', 'nationality']);
    }

    public function test_store_rejects_invalid_field_values(): void
    {
        $this->actingUser();

        $response = $this->postJson('/api/v1/contacts', $this->validPayload([
            'email' => 'not-an-email',
            'website' => 'not-a-url',
            'gender' => 'Unknown',
            'age' => 500,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'website', 'gender', 'age']);
    }

    public function test_authenticated_user_can_view_their_own_contact(): void
    {
        $user = $this->actingUser();
        $contact = Contact::factory()->create(['created_by' => $user->id]);

        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk()->assertJsonPath('data.id', $contact->id);
    }

    public function test_viewing_a_missing_contact_returns_404(): void
    {
        $this->actingUser();

        $response = $this->getJson('/api/v1/contacts/999999');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Resource not found.']);
    }

    public function test_authenticated_user_can_update_their_own_editable_fields(): void
    {
        $user = $this->actingUser();
        $contact = Contact::factory()->create(['created_by' => $user->id, 'name' => 'Old Name']);

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", ['name' => 'New Name']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.id', $contact->id);

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'New Name']);
    }

    public function test_update_leaves_created_by_and_created_at_unchanged(): void
    {
        $user = $this->actingUser();
        $contact = Contact::factory()->create(['created_by' => $user->id]);
        $originalCreatedAt = $contact->created_at;

        $this->putJson("/api/v1/contacts/{$contact->id}", [
            'name' => 'Changed',
            'created_by' => User::factory()->create()->id,
        ])->assertOk()->assertJsonPath('data.created_by', $user->id);

        $contact->refresh();
        $this->assertSame($user->id, $contact->created_by);
        $this->assertEquals($originalCreatedAt->timestamp, $contact->created_at->timestamp);
    }

    public function test_updating_a_missing_contact_returns_404(): void
    {
        $this->actingUser();

        $this->putJson('/api/v1/contacts/999999', ['name' => 'X'])->assertStatus(404);
    }

    public function test_update_rejects_invalid_values(): void
    {
        $user = $this->actingUser();
        $contact = Contact::factory()->create(['created_by' => $user->id]);

        $this->putJson("/api/v1/contacts/{$contact->id}", ['gender' => 'Nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('gender');
    }

    public function test_authenticated_user_can_delete_their_own_contact(): void
    {
        $user = $this->actingUser();
        $contact = Contact::factory()->create(['created_by' => $user->id]);

        $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk()->assertJson(['success' => true, 'message' => 'Contact deleted.']);
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_deleting_a_missing_contact_returns_404(): void
    {
        $this->actingUser();

        $this->deleteJson('/api/v1/contacts/999999')->assertStatus(404);
    }

    public function test_index_only_returns_the_authenticated_users_contacts(): void
    {
        $user = $this->actingUser();
        Contact::factory()->count(3)->create(['created_by' => $user->id]);
        Contact::factory()->count(5)->create(['created_by' => User::factory()->create()->id]);

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonCount(3, 'data.data');
    }

    public function test_a_new_user_with_no_contacts_sees_an_empty_list(): void
    {
        Contact::factory()->count(10)->create(['created_by' => User::factory()->create()->id]);

        $this->actingUser();

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonCount(0, 'data.data');
    }

    public function test_a_user_cannot_view_another_users_contact(): void
    {
        $this->actingUser();
        $othersContact = Contact::factory()->create(['created_by' => User::factory()->create()->id]);

        $this->getJson("/api/v1/contacts/{$othersContact->id}")->assertStatus(404);
    }

    public function test_a_user_cannot_update_another_users_contact(): void
    {
        $this->actingUser();
        $othersContact = Contact::factory()->create(['created_by' => User::factory()->create()->id, 'name' => 'Untouched']);

        $this->putJson("/api/v1/contacts/{$othersContact->id}", ['name' => 'Hacked'])->assertStatus(404);
        $this->assertDatabaseHas('contacts', ['id' => $othersContact->id, 'name' => 'Untouched']);
    }

    public function test_a_user_cannot_delete_another_users_contact(): void
    {
        $this->actingUser();
        $othersContact = Contact::factory()->create(['created_by' => User::factory()->create()->id]);

        $this->deleteJson("/api/v1/contacts/{$othersContact->id}")->assertStatus(404);
        $this->assertDatabaseHas('contacts', ['id' => $othersContact->id]);
    }

    public function test_index_returns_paginated_results_with_meta(): void
    {
        $user = $this->actingUser();
        Contact::factory()->count(20)->create(['created_by' => $user->id]);

        $response = $this->getJson('/api/v1/contacts?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [['id', 'name', 'email', 'created_by', 'created_at']],
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ])
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.total', 20)
            ->assertJsonPath('data.meta.last_page', 4)
            ->assertJsonCount(5, 'data.data');
    }

    public function test_index_uses_the_configured_default_page_size(): void
    {
        $user = $this->actingUser();
        Contact::factory()->count(3)->create(['created_by' => $user->id]);

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', config('contacts.pagination.default_per_page'));
    }

    public function test_index_search_matches_name_email_and_phone_case_insensitively(): void
    {
        $user = $this->actingUser();
        Contact::factory()->create(['created_by' => $user->id, 'name' => 'Findable Person', 'email' => 'x@x.test', 'phone' => '+15550009999']);
        Contact::factory()->count(3)->create(['created_by' => $user->id, 'name' => 'Someone Else']);

        $this->getJson('/api/v1/contacts?search=findable')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/v1/contacts?search=5550009999')->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_index_filters_work_individually_and_combined(): void
    {
        $user = $this->actingUser();
        Contact::factory()->create(['created_by' => $user->id, 'gender' => 'Female', 'nationality' => 'Canada', 'age' => 30]);
        Contact::factory()->create(['created_by' => $user->id, 'gender' => 'Male', 'nationality' => 'Canada', 'age' => 30]);
        Contact::factory()->create(['created_by' => $user->id, 'gender' => 'Female', 'nationality' => 'France', 'age' => 65]);

        $this->getJson('/api/v1/contacts?gender=Female')->assertOk()->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/v1/contacts?nationality=Canada')->assertOk()->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/v1/contacts?min_age=40')->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/contacts?gender=Female&nationality=Canada&min_age=25&max_age=40')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_index_rejects_an_out_of_range_page_size(): void
    {
        $this->actingUser();

        $this->getJson('/api/v1/contacts?per_page=1000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_index_response_exposes_only_defined_fields(): void
    {
        $user = $this->actingUser();
        Contact::factory()->create(['created_by' => $user->id]);

        $item = $this->getJson('/api/v1/contacts')->assertOk()->json('data.data.0');

        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'phone', 'email', 'website', 'gender', 'age', 'nationality', 'created_by', 'created_at'],
            array_keys($item),
        );
    }
}
