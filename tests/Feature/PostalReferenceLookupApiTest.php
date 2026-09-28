<?php

namespace Tests\Feature;

use App\Jobs\Locality\PostalReferenceEnrichmentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostalReferenceLookupApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_lookup_returns_immediately_with_pending_refresh_and_a_single_job(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/localities/postal-references/lookup', ['countryCode' => 'BR', 'postalCode' => '01001-000'])
            ->assertOk()
            ->assertJsonPath('candidates', [])
            ->assertJsonPath('refresh.state', 'PENDING')
            ->assertJsonPath('refresh.pollAfterMilliseconds', 1000)
            ->assertJsonMissingPath('refresh.source');

        Queue::assertPushed(PostalReferenceEnrichmentJob::class, 1);
    }

    public function test_status_polls_without_starting_a_new_job_and_validation_is_safe(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/localities/postal-references/lookup-status?countryCode=BR&postalCode=01001000')
            ->assertOk()
            ->assertJsonPath('refresh.state', 'COMPLETED')
            ->assertJsonPath('refresh.pollAfterMilliseconds', null);
        Queue::assertNothingPushed();

        $this->actingAs($user)->postJson('/api/v1/localities/postal-references/lookup', ['countryCode' => 'BRA', 'postalCode' => '---'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'LOCALITY_LOOKUP_VALIDATION_FAILED');
    }
}
