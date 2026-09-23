<?php

namespace App\Mail\Access;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailVerificationMessage extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $challengeId,
        public readonly string $token,
        public readonly string $code,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme seu e-mail');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access.email-verification',
            with: ['verificationUrl' => $this->verificationUrl()],
        );
    }

    public function verificationUrl(): string
    {
        return rtrim((string) config('app.url'), '/')
            .'/access/email-verification?challengeId='.rawurlencode($this->challengeId)
            .'&token='.rawurlencode($this->token);
    }
}
