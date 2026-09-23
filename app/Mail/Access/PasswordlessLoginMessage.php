<?php

namespace App\Mail\Access;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordlessLoginMessage extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $challengeId, public readonly string $token, public readonly string $code)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Acesse sua conta');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.access.passwordless-login', with: ['loginUrl' => rtrim((string) config('app.url'), '/').'/access/passwordless?challengeId='.rawurlencode($this->challengeId).'&token='.rawurlencode($this->token)]);
    }
}
