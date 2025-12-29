<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class GmailMailer
{
    public static function send(string $to, string $subject, string $message): void
    {
        Mail::raw($message, function ($mail) use ($to, $subject) {
            $mail->to($to)
                ->subject($subject);
        });
    }
}
