<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailpitEmailRoundtripTest extends TestCase
{
    use RefreshDatabase;

    private string $mailpitApiUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailpitApiUrl = rtrim((string) env('MAILPIT_API_URL'), '/');
        if ($this->mailpitApiUrl === '') {
            $this->markTestSkipped('Defina MAILPIT_API_URL para executar a integração SMTP com Mailpit.');
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => env('MAIL_HOST', '127.0.0.1'),
            'mail.mailers.smtp.port' => (int) env('MAIL_PORT', 1025),
            'mail.mailers.smtp.scheme' => null,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'queue.default' => 'database',
        ]);
        app('mail.manager')->purge('smtp');
        Http::baseUrl($this->mailpitApiUrl)->delete('/api/v1/messages')->throw();
    }

    public function test_verification_email_is_delivered_over_smtp_and_its_code_completes_access(): void
    {
        $this->postJson('/api/v1/auth/registrations', ['email' => 'mailpit-registration@example.test'])
            ->assertAccepted();

        $this->processQueuedMessage();
        $email = $this->latestEmail();
        $this->assertSame('Confirme seu e-mail', $email['Subject']);
        $this->assertSame('mailpit-registration@example.test', $email['To'][0]['Email']);

        [$code, $link] = $this->extractCodeAndLink($email['HTML']);
        $this->assertSame('/access/email-verification', parse_url($link, PHP_URL_PATH));
        parse_str((string) parse_url($link, PHP_URL_QUERY), $parameters);
        $this->assertArrayHasKey('challengeId', $parameters);

        $this->postJson('/api/v1/auth/email-verifications', [
            'challengeId' => $parameters['challengeId'],
            'code' => $code,
            'displayName' => 'Teste Mailpit',
        ])->assertCreated();

        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $parameters['challengeId']]);
    }

    public function test_passwordless_email_is_delivered_over_smtp_and_its_code_authenticates_user(): void
    {
        $user = User::factory()->create(['email' => 'mailpit-passwordless@example.test']);

        $this->postJson('/api/v1/auth/passwordless-sessions', ['email' => $user->email])
            ->assertAccepted();

        $this->processQueuedMessage();
        $email = $this->latestEmail();
        $this->assertSame('Acesse sua conta', $email['Subject']);
        $this->assertSame($user->email, $email['To'][0]['Email']);

        [$code, $link] = $this->extractCodeAndLink($email['HTML']);
        $this->assertSame('/access/passwordless', parse_url($link, PHP_URL_PATH));
        parse_str((string) parse_url($link, PHP_URL_QUERY), $parameters);
        $this->assertArrayHasKey('challengeId', $parameters);

        $this->postJson('/api/v1/auth/passwordless-sessions/confirmations', [
            'challengeId' => $parameters['challengeId'],
            'code' => $code,
        ])->assertCreated()->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $parameters['challengeId']]);
    }

    private function processQueuedMessage(): void
    {
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work database --once --sleep=0 --tries=1')->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
    }

    /**
     * @return array{HTML: string, Subject: string, To: array<int, array{Email: string}>}
     */
    private function latestEmail(): array
    {
        return Http::baseUrl($this->mailpitApiUrl)
            ->get('/api/v1/message/latest')
            ->throw()
            ->json();
    }

    /**
     * @return array{string, string}
     */
    private function extractCodeAndLink(string $html): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $code = trim((string) $xpath->query('//strong')->item(0)?->textContent);
        $link = (string) $xpath->query('//a')->item(0)?->attributes?->getNamedItem('href')?->nodeValue;

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame('', $link);

        return [$code, html_entity_decode($link, ENT_QUOTES | ENT_HTML5)];
    }
}
