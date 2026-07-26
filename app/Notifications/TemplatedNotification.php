<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for emails driven by editable templates (EmailTemplate). Loads the template by key,
 * replaces macros and sends as a queued email. The body is plain text – no user Blade.
 */
abstract class TemplatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Template key (EmailTemplate::KEY_*). */
    abstract protected function templateKey(): string;

    /** Macro values for substitution in the template. */
    abstract protected function macroData(object $notifiable): array;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailTemplate::where('key', $this->templateKey())->first();

        // Without a template (should not happen – we seed defaults) send at least an empty envelope.
        if (! $template) {
            return (new MailMessage)->subject(config('company.name'))->line('');
        }

        $rendered = $template->renderTemplate($this->macroData($notifiable));

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->view('emails.templated', [
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
            ]);
    }
}
