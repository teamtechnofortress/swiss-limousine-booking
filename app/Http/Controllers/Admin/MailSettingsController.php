<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Integrations\SendTestMailRequest;
use App\Http\Requests\Admin\Integrations\UpdateSenderRequest;
use App\Http\Requests\Admin\Integrations\UpdateSmtpSettingsRequest;
use App\Models\User;
use App\Services\Notifications\MailSettingsService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class MailSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MailSettingsService $mail,
    ) {}

    /** The password is never returned. The response only says whether one is saved. */
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'mailer' => $this->settings->string('mail_mailer'),
            'host' => $this->settings->string('smtp_host'),
            'port' => $this->settings->int('smtp_port'),
            'encryption' => $this->settings->string('smtp_encryption'),
            'username' => $this->settings->string('smtp_username'),
            'password_set' => $this->settings->secret('smtp_password') !== null,
        ]]);
    }

    public function update(UpdateSmtpSettingsRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $data = $request->validated();

        $this->settings->update('mail_mailer', $data['mailer'], $actor);
        $this->settings->update('smtp_host', $data['host'] ?? '', $actor);
        $this->settings->update('smtp_port', (string) ($data['port'] ?? 587), $actor);
        $this->settings->update('smtp_encryption', $data['encryption'], $actor);
        $this->settings->update('smtp_username', $data['username'] ?? '', $actor);

        if (! empty($data['password'])) {
            $this->settings->update('smtp_password', $data['password'], $actor);
        }

        return $this->show();
    }

    public function test(SendTestMailRequest $request): JsonResponse
    {
        try {
            $this->mail->send(
                $request->string('to')->toString(),
                'Test email from the booking system',
                "SMTP is working.\n\nSent at ".now('UTC')->toIso8601String().' UTC.',
            );
        } catch (Throwable $e) {
            return response()->json(['error' => [
                'code' => 'mail_failed',
                'message' => Str::limit($this->redactPassword($e->getMessage()), 300),
            ]], 422);
        }

        return response()->json(['data' => ['message' => 'Test email sent.']]);
    }

    public function showSender(): JsonResponse
    {
        return response()->json(['data' => [
            'from_address' => $this->settings->string('mail_from_address'),
            'from_name' => $this->settings->string('mail_from_name'),
        ]]);
    }

    public function updateSender(UpdateSenderRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $data = $request->validated();

        $this->settings->update('mail_from_address', $data['from_address'], $actor);
        $this->settings->update('mail_from_name', $data['from_name'], $actor);

        return $this->showSender();
    }

    private function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }

    private function redactPassword(string $message): string
    {
        $password = $this->settings->secret('smtp_password');

        return $password === null || $password === ''
            ? $message
            : str_replace($password, '[redacted]', $message);
    }
}
