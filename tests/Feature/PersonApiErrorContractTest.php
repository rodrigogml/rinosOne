<?php

namespace Tests\Feature;

use App\Domain\Person\Exception\PersonDocumentConflictException;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Models\User;
use App\Services\Person\PersonAggregateService;
use App\Services\Person\PersonTenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PersonApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('person', function ($table): void {
            $table->id();
        });
    }

    public function test_validation_error_uses_the_people_json_envelope_and_localized_safe_field_feedback(): void
    {
        $user = User::factory()->create();
        $this->authorizePeopleRequests();

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'c4b1deb4-d3b7-4bad-9bdd-2b0d7b3dcb6a')
            ->postJson('/api/v1/tenants/17/people', [
                'personType' => 'PF',
                'name' => 'Nome que não pode aparecer na resposta',
                'alias' => ['valor privado'],
            ]);

        $response
            ->assertBadRequest()
            ->assertJsonPath('error.code', 'PERSON_VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Os dados da Pessoa não são válidos.')
            ->assertJsonPath('error.fields.alias.0', 'O valor informado deve ser um texto.')
            ->assertJsonMissingPath('error.exception')
            ->assertJsonMissingPath('error.trace');
        $response->assertDontSee('Nome que não pode aparecer na resposta');
        $response->assertDontSee('valor privado');
    }

    public function test_not_found_response_is_safe_for_an_authorized_person_request(): void
    {
        $this->authorizePeopleRequests();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/tenants/17/people/999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PERSON_NOT_FOUND')
            ->assertJsonPath('error.message', 'Pessoa não encontrada nesta organização.')
            ->assertJsonMissingPath('error.exception')
            ->assertJsonMissingPath('error.trace')
            ->assertJsonMissingPath('person');
    }

    public function test_document_conflict_never_echoes_the_document_or_internal_exception_message(): void
    {
        $this->authorizePeopleRequests();
        $aggregate = Mockery::mock(PersonAggregateService::class);
        $aggregate->shouldReceive('create')->once()->andThrow(new PersonDocumentConflictException);
        $this->app->instance(PersonAggregateService::class, $aggregate);

        $this->actingAs(User::factory()->create())
            ->withHeader('Idempotency-Key', 'd4b1deb4-d3b7-4bad-9bdd-2b0d7b3dcb6a')
            ->postJson('/api/v1/tenants/17/people', [
                'personType' => 'PF',
                'name' => 'Ana de Teste',
                'cpf' => '529.982.247-25',
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'PERSON_DOCUMENT_CONFLICT')
            ->assertJsonPath('error.message', 'CPF ou CNPJ já pertence a outra Pessoa desta organização.')
            ->assertJsonMissingPath('error.exception')
            ->assertJsonMissingPath('error.trace')
            ->assertDontSee('529.982.247-25')
            ->assertDontSee('A Person with this document already exists in the tenant.');
    }

    private function authorizePeopleRequests(): void
    {
        $this->withoutMiddleware(RequireIdempotencyKey::class);
        $context = Mockery::mock(PersonTenantContext::class);
        $context->shouldReceive('connectionFor')->andReturn(DB::connection());
        $this->app->instance(PersonTenantContext::class, $context);
    }
}
