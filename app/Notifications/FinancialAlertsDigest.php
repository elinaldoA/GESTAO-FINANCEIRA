<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinancialAlertsDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly array $alerts)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Alertas financeiros — '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Encontramos alguns pontos que merecem sua atenção hoje:');

        foreach ($this->alerts as $alert) {
            $mail->line('• '.$alert['message']);
        }

        return $mail
            ->action('Abrir dashboard', route('dashboard'))
            ->line('Você está recebendo este e-mail porque há pendências financeiras em aberto na sua conta.');
    }
}
