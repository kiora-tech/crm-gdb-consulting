<?php

namespace App\Mcp\Tool;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(tags: [['name' => 'monolog.logger', 'channel' => 'mcp_audit']])]
class AuditLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function log(string $action, ?string $userEmail, ?int $customerId, ?string $customerName): void
    {
        $this->logger->info('MCP write operation', [
            'action' => $action,
            'user_email' => $userEmail,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
