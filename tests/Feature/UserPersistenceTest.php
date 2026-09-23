<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_receives_a_ulid_and_uses_the_approved_columns(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Str::isUlid($user->id));
        $this->assertDatabaseHas('user', [
            'id' => $user->id,
            'email' => $user->email,
            'displayName' => $user->displayName,
        ]);
    }

    public function test_email_is_normalized_and_password_is_only_persisted_as_a_hash(): void
    {
        $plainPassword = 'S3nh@-de-teste';

        $user = User::query()->create([
            'email' => '  MEMBER@EXAMPLE.TEST  ',
            'displayName' => 'Member',
            'passwordHash' => $plainPassword,
        ]);

        $storedPassword = $user->refresh()->passwordHash;

        $this->assertSame('member@example.test', $user->email);
        $this->assertNotSame($plainPassword, $storedPassword);
        $this->assertTrue(Hash::check($plainPassword, $storedPassword));
        $this->assertSame('passwordHash', $user->getAuthPasswordName());
        $this->assertSame($storedPassword, $user->getAuthPassword());
    }
}
