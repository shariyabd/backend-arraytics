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
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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
        $user = User::factory()->create();
        $attacker = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/contacts', $this->validPayload([
            'created_by' => $attacker->id,
        ]));

        $response->assertCreated()->assertJsonPath('data.created_by', $user->id);
        $this->assertDatabaseHas('contacts', ['email' => 'ada@example.com', 'created_by' => $user->id]);
    }

    public function test_website_is_optional(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/contacts', $this->validPayload(['website' => null]));

        $response->assertCreated()->assertJsonPath('data.website', null);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/contacts', []);

        $response->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'The given data was invalid.'])
            ->assertJsonValidationErrors(['name', 'phone', 'email', 'gender', 'age', 'nationality']);
    }

    public function test_store_rejects_invalid_field_values(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/contacts', $this->validPayload([
            'email' => 'not-an-email',
            'website' => 'not-a-url',
            'gender' => 'Unknown',
            'age' => 500,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'website', 'gender', 'age']);
    }

    public function test_authenticated_user_can_view_a_contact(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $contact = Contact::factory()->create();

        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk()->assertJsonPath('data.id', $contact->id);
    }

    public function test_viewing_a_missing_contact_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/contacts/999999');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Resource not found.']);
    }

    public function test_authenticated_user_can_update_editable_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $contact = Contact::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", ['name' => 'New Name']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.id', $contact->id);

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'New Name']);
    }

    public function test_update_leaves_created_by_and_created_at_unchanged(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $owner = User::factory()->create();
        $contact = Contact::factory()->create(['created_by' => $owner->id]);
        $originalCreatedAt = $contact->created_at;

        $this->putJson("/api/v1/contacts/{$contact->id}", [
            'name' => 'Changed',
            'created_by' => User::factory()->create()->id,
        ])->assertOk()->assertJsonPath('data.created_by', $owner->id);

        $contact->refresh();
        $this->assertSame($owner->id, $contact->created_by);
        $this->assertEquals($originalCreatedAt->timestamp, $contact->created_at->timestamp);
    }

    public function test_updating_a_missing_contact_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/contacts/999999', ['name' => 'X'])->assertStatus(404);
    }

    public function test_update_rejects_invalid_values(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $contact = Contact::factory()->create();

        $this->putJson("/api/v1/contacts/{$contact->id}", ['gender' => 'Nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('gender');
    }

    public function test_authenticated_user_can_delete_a_contact(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $contact = Contact::factory()->create();

        $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk()->assertJson(['success' => true, 'message' => 'Contact deleted.']);
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_deleting_a_missing_contact_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/contacts/999999')->assertStatus(404);
    }

    public function test_index_returns_paginated_results_with_meta(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Contact::factory()->count(20)->create();

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
        Sanctum::actingAs(User::factory()->create());
        Contact::factory()->count(3)->create();

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', config('contacts.pagination.default_per_page'));
    }

    public function test_index_search_matches_name_email_and_phone_case_insensitively(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Contact::factory()->create(['name' => 'Findable Person', 'email' => 'x@x.test', 'phone' => '+15550009999']);
        Contact::factory()->count(3)->create(['name' => 'Someone Else']);

        $this->getJson('/api/v1/contacts?search=findable')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/v1/contacts?search=5550009999')->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_index_filters_work_individually_and_combined(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Contact::factory()->create(['gender' => 'Female', 'nationality' => 'Canada', 'age' => 30]);
        Contact::factory()->create(['gender' => 'Male', 'nationality' => 'Canada', 'age' => 30]);
        Contact::factory()->create(['gender' => 'Female', 'nationality' => 'France', 'age' => 65]);

        $this->getJson('/api/v1/contacts?gender=Female')->assertOk()->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/v1/contacts?nationality=Canada')->assertOk()->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/v1/contacts?min_age=40')->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/contacts?gender=Female&nationality=Canada&min_age=25&max_age=40')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_index_rejects_an_out_of_range_page_size(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/contacts?per_page=1000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_index_response_exposes_only_defined_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Contact::factory()->create();

        $item = $this->getJson('/api/v1/contacts')->assertOk()->json('data.data.0');

        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'phone', 'email', 'website', 'gender', 'age', 'nationality', 'created_by', 'created_at'],
            array_keys($item),
        );
    }
}
