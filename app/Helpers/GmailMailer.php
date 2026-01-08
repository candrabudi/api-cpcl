<?php

namespace App\Helpers;

use Illuminate\Support\Facades\View;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class GmailMailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        \Log::info('📧 SENDING PLAIN TEXT EMAIL', [
            'to' => $to,
            'subject' => $subject,
        ]);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME');
            $mail->Password = env('MAIL_PASSWORD');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION');
            $mail->Port = (int) env('MAIL_PORT');

            $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $mail->addAddress($to);

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            
            \Log::info('✅ PLAIN TEXT EMAIL SENT SUCCESSFULLY', ['to' => $to]);
        } catch (Exception $e) {
            \Log::error('❌ PLAIN TEXT EMAIL FAILED', [
                'to' => $to,
                'error' => $e->getMessage(),
                'mail_host' => env('MAIL_HOST'),
                'mail_port' => env('MAIL_PORT'),
                'mail_username' => env('MAIL_USERNAME'),
                'mail_from' => env('MAIL_FROM_ADDRESS'),
            ]);
        }
    }

    /**
     * Send email using Blade view.
     */
    public static function sendView(string $to, string $subject, string $view, array $data = []): void
    {
        // Log konfigurasi email yang digunakan
        \Log::info('📧 ATTEMPTING TO SEND EMAIL', [
            'to' => $to,
            'subject' => $subject,
            'view' => $view,
            'data' => $data,
        ]);

        \Log::info('🔧 EMAIL CONFIGURATION FROM ENV', [
            'MAIL_HOST' => env('MAIL_HOST'),
            'MAIL_PORT' => env('MAIL_PORT'),
            'MAIL_USERNAME' => env('MAIL_USERNAME'),
            'MAIL_PASSWORD' => env('MAIL_PASSWORD') ? '***SET***' : '***NOT SET***',
            'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
            'MAIL_FROM_NAME' => env('MAIL_FROM_NAME'),
        ]);

        try {
            // Render HTML dari Blade
            $html = View::make($view, $data)->render();
            \Log::info('✅ Blade view rendered successfully', ['view' => $view]);

            $mail = new PHPMailer(true);
            
            // Enable verbose debug output
            $mail->SMTPDebug = 0; // Set to 2 for more verbose debugging
            $mail->Debugoutput = function($str, $level) {
                \Log::debug("PHPMailer Debug: $str");
            };
            
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME');
            $mail->Password = env('MAIL_PASSWORD');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION');
            $mail->Port = (int) env('MAIL_PORT');

            $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;

            \Log::info('📤 Sending email via PHPMailer...');
            $mail->send();
            
            \Log::info('✅ EMAIL SENT SUCCESSFULLY', [
                'to' => $to,
                'subject' => $subject,
            ]);
        } catch (Exception $e) {
            \Log::error('❌ EMAIL SENDING FAILED (PHPMailer Exception)', [
                'to' => $to,
                'subject' => $subject,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'smtp_host' => env('MAIL_HOST'),
                'smtp_port' => env('MAIL_PORT'),
            ]);

            // fallback ke plain text jika Blade gagal
            try {
                \Log::info('🔄 Attempting fallback to plain text email...');
                self::send($to, $subject, $data['otp'] ?? 'Kode OTP tidak tersedia');
            } catch (\Throwable $fallbackError) {
                \Log::error('❌ FALLBACK EMAIL ALSO FAILED', [
                    'error' => $fallbackError->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('❌ UNEXPECTED ERROR IN sendView', [
                'to' => $to,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
