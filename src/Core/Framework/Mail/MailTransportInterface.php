<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Mail;

interface MailTransportInterface
{
    public function send(string $recipient, string $subject, string $text): void;
}
