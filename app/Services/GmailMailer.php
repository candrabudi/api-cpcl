<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class GmailMailer
{
    public static function sendView(string $to, string $subject, string $view, array $data = []): void
    {
        $data['subject'] = $subject;

        Mail::send($view, $data, function ($mail) use ($to, $subject) {
            $mail->to($to)
                 ->subject($subject);
        });
    }
}
