<?php

namespace App\Mail\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string|int $code;
    public string $email;

    public function __construct(string $code, string $email)
    {
        $this->code = $code;
        $this->email = $email;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $htmlContent = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset=\"UTF-8\">
                <title>Password Reset</title>
            </head>
            <body style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;\">
                <div style=\"background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);\">
                    <h1 style=\"color: #333; text-align: center;\">Password Reset</h1>

                    <p>Hello,</p>

                    <p>You are receiving this email because you requested to reset your password on Arlekino.</p>

                    <p>Your password reset code is:</p>

                    <div style=\"text-align: center; margin: 30px 0;\">
                        <span style=\"font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #333;\">$this->code</span>
                    </div>

                    <p>If you didn't request this password reset, please ignore this email.</p>

                    <p style=\"margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #777; font-size: 14px;\">
                        This code will expire in 1 hour.
                    </p>
                </div>
            </body>
            </html>
        ";

        return $this->subject('Password Reset Code')
                    ->html($htmlContent);
    }
}
