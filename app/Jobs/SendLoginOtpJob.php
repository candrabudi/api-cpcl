<?php

namespace App\Jobs;

use App\Helpers\GmailMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLoginOtpJob implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    public string $email;
    public int $otp;

    public function __construct(string $email, int $otp)
    {
        $this->email = $email;
        $this->otp = $otp;
    }

    public function handle(): void
    {
        GmailMailer::sendView(
            $this->email,
            'OTP Login',
            'emails.otp',
            [
                'otp' => $this->otp,
            ]
        );
    }
}
