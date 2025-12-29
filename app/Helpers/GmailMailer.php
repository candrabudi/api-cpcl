<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class GmailMailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME');
            $mail->Password = env('MAIL_PASSWORD');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION');
            $mail->Port = (int) env('MAIL_PORT');

            $mail->setFrom(
                env('MAIL_FROM_ADDRESS'),
                env('MAIL_FROM_NAME')
            );

            $mail->addAddress($to);

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
        } catch (Exception $e) {
            // jangan throw biar login gak gagal
            \Log::error('Email OTP gagal dikirim', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
