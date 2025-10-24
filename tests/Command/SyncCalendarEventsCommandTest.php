<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SyncCalendarEventsCommand;
use App\Service\CalendarEventSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SyncCalendarEventsCommandTest extends TestCase
{
    private CalendarEventSyncService&MockObject $syncService;
    private SyncCalendarEventsCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->syncService = $this->createMock(CalendarEventSyncService::class);
        $this->command = new SyncCalendarEventsCommand($this->syncService);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteWithSuccessfulSyncReturnsSuccess(): void
    {
        $this->syncService->expects($this->once())
            ->method('syncAllPendingEvents')
            ->willReturn(5);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Calendar Events Synchronization', $output);
        $this->assertStringContainsString('5 événement(s) synchronisé(s) avec succès', $output);
    }

    public function testExecuteWithNoEventsToSyncReturnsSuccess(): void
    {
        $this->syncService->expects($this->once())
            ->method('syncAllPendingEvents')
            ->willReturn(0);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Aucun événement à synchroniser', $output);
    }

    public function testExecuteWithExceptionReturnsFailure(): void
    {
        $this->syncService->expects($this->once())
            ->method('syncAllPendingEvents')
            ->willThrowException(new \RuntimeException('Sync failed'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Erreur lors de la synchronisation', $output);
        $this->assertStringContainsString('Sync failed', $output);
    }

    public function testExecuteShowsProgressMessages(): void
    {
        $this->syncService->expects($this->once())
            ->method('syncAllPendingEvents')
            ->willReturn(3);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Calendar Events Synchronization', $output);
        $this->assertStringContainsString('Starting synchronization with Microsoft Graph API', $output);
        $this->assertStringContainsString('3 événement(s) synchronisé(s) avec succès', $output);
    }

    public function testCommandConfiguration(): void
    {
        $this->assertSame('app:sync-calendar-events', $this->command->getName());
        $this->assertSame(
            'Synchronize calendar events with Microsoft Graph API',
            $this->command->getDescription()
        );
    }
}
