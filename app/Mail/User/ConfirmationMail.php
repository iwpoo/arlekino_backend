<?php

namespace App\Mail\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string|int $code;
    public string $email;

    public function __construct(string $code, string $email)
    {
        $this->code = $code;
        $this->email = $email;
    }

    public function build()
    {
        $htmlContent = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset=\"UTF-8\">
                <title>Email Verification</title>
            </head>
            <body style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;\">
                <div style=\"background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);\">
                    <h1 style=\"color: #333; text-align: center;\">Email Verification</h1>

                    <p>Hello,</p>

                    <p>You are receiving this email because you requested to verify your email address on Arlekino.</p>

                    <div style=\"text-align: center; margin: 30px 0;\">
                        <p>Your verification code is:</p>
                        <p style=\"font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 5px;\">$this->code</p>
                    </div>

                    <p>Please enter this code in the application to complete your email verification.</p>

                    <p>If you did not request this verification, please ignore this email.</p>

                    <hr style=\"margin: 30px 0; border: none; border-top: 1px solid #eee;\">

                    <p style=\"text-align: center; color: #666;\">
                        Thanks,<br>
                        <strong>" . config('app.name') . "</strong>
                    </p>
                </div>
            </body>
            </html>
        ";

        $this->html = $htmlContent;

        return $this->subject('Email Verification Code');
    }
}
