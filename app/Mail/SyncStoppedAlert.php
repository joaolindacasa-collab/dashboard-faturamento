<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de alerta de sync parado. Corpo em texto simples (passado pronto pelo
 * command) — renderizado como HTML com quebras de linha preservadas.
 */
class SyncStoppedAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->html(nl2br(e($this->bodyText)));
    }
}
