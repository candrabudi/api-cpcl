<?php

namespace App\Jobs;

use App\Services\GmailMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLoginOtpEmail implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected string $email;
    protected int $otp;

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
