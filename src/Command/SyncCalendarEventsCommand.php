<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CalendarEventSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-calendar-events',
    description: 'Synchronize calendar events with Microsoft Graph API',
)]
class SyncCalendarEventsCommand extends Command
{
    public function __construct(
        private readonly CalendarEventSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Calendar Events Synchronization');
        $io->info('Starting synchronization with Microsoft Graph API...');

        try {
            $syncedCount = $this->syncService->syncAllPendingEvents();

            if ($syncedCount > 0) {
                $io->success(sprintf('%d événement(s) synchronisé(s) avec succès.', $syncedCount));
            } else {
                $io->info('Aucun événement à synchroniser.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la synchronisation : %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
