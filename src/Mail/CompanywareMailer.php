<?php

declare(strict_types=1);

namespace App\Mail;

use Companyware\Core\Framework\Mail\MailTransportInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class CompanywareMailer implements MailTransportInterface
{
    public function __construct(private readonly MailerInterface $mailer, private readonly string $fromAddress) {}

    public function send(string $recipient, string $subject, string $text): void
    {
        $this->mailer->send((new Email())->from($this->fromAddress)->to($recipient)->subject($subject)->text($text));
    }
}
