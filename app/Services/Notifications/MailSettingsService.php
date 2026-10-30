<?php

namespace App\Services\Notifications;

use App\Services\Settings\SettingsService;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/** Applies database-backed mail settings immediately before each send. */
final class MailSettingsService
{
    public function __construct(private readonly SettingsService $settings) {}

    /** Applies the admin-configured mail settings to Laravel's mailer. */
    public function apply(): void
    {
        $mailer = $this->settings->string('mail_mailer') === 'log' ? 'log' : 'smtp';

        if ($mailer === 'smtp') {
            $host = $this->settings->string('smtp_host');

            if ($host === '') {
                throw new RuntimeException('SMTP host is not configured.');
            }

            config(['mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => $this->settings->string('smtp_encryption') === 'ssl' ? 'smtps' : null,
                'host' => $host,
                'port' => $this->settings->int('smtp_port'),
                'username' => $this->settings->string('smtp_username') ?: null,
                'password' => $this->settings->secret('smtp_password'),
                'timeout' => 10,
            ]]);
        }

        config([
            'mail.default' => $mailer,
            'mail.from' => [
                'address' => $this->settings->string('mail_from_address') ?: config('mail.from.address'),
                'name' => $this->settings->string('mail_from_name') ?: config('mail.from.name'),
            ],
        ]);

        // Queue workers keep mailers in memory, so rebuild them after every settings change.
        Mail::purge('smtp');
        Mail::purge('log');
    }

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): void
    {
        $this->apply();

        Mail::raw($body, function (Message $message) use ($to, $subject, $replyTo): void {
            $message->to($to)->subject($subject);

            if ($replyTo !== null && $replyTo !== '') {
                $message->replyTo($replyTo);
            }
        });
    }
}
