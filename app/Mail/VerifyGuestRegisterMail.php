<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyGuestRegisterMail extends Mailable
{
    use Queueable, SerializesModels;

    public $signed_url;
    public $username;

    public function __construct($signed_url)
    {
        $this->signed_url = $signed_url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Verificar Email - Registro de Cuenta')
                    ->from($this->username, env('APP_NAME'))
                    ->view('system.guest-register.emails.verify_email');
    }

    public function setUsernmae(string $username)
    {
        $this->username = $username;
    }
}