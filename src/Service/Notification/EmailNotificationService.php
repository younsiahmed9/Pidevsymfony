<?php

namespace App\Service\Notification;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

final class EmailNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {
    }

    public function sendPromotionNotification(string $email, string $subject, string $message): bool
    {
        try {
            $emailMessage = (new Email())
                ->from('no-reply@fintrack.local')
                ->to($email)
                ->subject($subject)
                ->text($message)
                ->html($this->createHtmlTemplate($subject, $message));

            $this->mailer->send($emailMessage);

            $this->logger->info('Promotion email sent successfully', [
                'email' => $email,
                'subject' => $subject,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send promotion email', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);

            return false;
        }
    }

    private function createHtmlTemplate(string $subject, string $message): string
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #4f46e5; color: white; padding: 20px; text-align: center;'>
                <h1>FinTrack</h1>
                <h2>{$subject}</h2>
            </div>
            <div style='padding: 20px; background: #f9fafb;'>
                <p style='font-size: 16px; line-height: 1.5;'>{$message}</p>
            </div>
            <div style='background: #e5e7eb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280;'>
                <p>Cet email a été envoyé automatiquement par FinTrack</p>
                <p>Pour ne plus recevoir ces emails, contactez le support.</p>
            </div>
        </body>
        </html>";
    }
}
