<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NormalizePersistentAuthenticationSessionTest extends TestCase
{
    public function test_legacy_non_numeric_persistent_authentication_identifier_is_removed_from_session(): void
    {
        Route::get('/legacy-persistent-authentication-session-probe', static fn (Request $request) => response()->json([
            'persistentAuthenticationId' => $request->session()->get('persistentAuthenticationId'),
        ]))->middleware('web');

        $this->withSession(['persistentAuthenticationId' => '01M3A939DZRAGC5PQC79584WMN'])
            ->get('/legacy-persistent-authentication-session-probe')
            ->assertOk()
            ->assertJsonPath('persistentAuthenticationId', null);
    }

    public function test_numeric_persistent_authentication_identifier_is_preserved_in_session(): void
    {
        Route::get('/numeric-persistent-authentication-session-probe', static fn (Request $request) => response()->json([
            'persistentAuthenticationId' => $request->session()->get('persistentAuthenticationId'),
        ]))->middleware('web');

        $this->withSession(['persistentAuthenticationId' => '42'])
            ->get('/numeric-persistent-authentication-session-probe')
            ->assertOk()
            ->assertJsonPath('persistentAuthenticationId', '42');
    }
}
