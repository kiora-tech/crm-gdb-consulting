<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RefreshMicrosoftTasksMessage;
use App\Repository\UserRepository;
use App\Service\MicrosoftGraphService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
class RefreshMicrosoftTasksHandler
{
    private const int TTL_TASKS = 180; // 3 minutes

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MicrosoftGraphService $microsoftGraphService,
        private readonly CacheInterface $microsoftGraphCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshMicrosoftTasksMessage $message): void
    {
        $userId = $message->getUserId();

        $this->logger->info('Refreshing Microsoft tasks in background', [
            'user_id' => $userId,
        ]);

        try {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                $this->logger->error('User not found for refresh', ['user_id' => $userId]);

                return;
            }

            $tasks = $this->microsoftGraphService->getUserTasks($user);

            $cacheKey = sprintf('ms_graph:%d:tasks', $userId);
            $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);
            $cacheItem->set([
                'data' => $tasks,
                'timestamp' => time(),
            ]);
            $cacheItem->expiresAfter(self::TTL_TASKS);
            $this->microsoftGraphCache->save($cacheItem);

            $this->logger->info('Microsoft tasks refreshed successfully', [
                'user_id' => $userId,
                'count' => count($tasks),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing Microsoft tasks', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
