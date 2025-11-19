<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RefreshMicrosoftCategoriesMessage;
use App\Repository\UserRepository;
use App\Service\MicrosoftGraphService;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefreshMicrosoftCategoriesHandler
{
    private const int TTL_CATEGORIES = 3600; // 1 hour

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MicrosoftGraphService $microsoftGraphService,
        private readonly CacheItemPoolInterface $microsoftGraphCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshMicrosoftCategoriesMessage $message): void
    {
        $userId = $message->getUserId();

        $this->logger->info('Refreshing Microsoft categories in background', [
            'user_id' => $userId,
        ]);

        try {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                $this->logger->error('User not found for refresh', ['user_id' => $userId]);

                return;
            }

            $categories = $this->microsoftGraphService->getUserCategories($user);

            $cacheKey = sprintf('ms_graph:%d:categories', $userId);
            $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);
            $cacheItem->set([
                'data' => $categories,
                'timestamp' => time(),
            ]);
            $cacheItem->expiresAfter(self::TTL_CATEGORIES);
            $this->microsoftGraphCache->save($cacheItem);

            $this->logger->info('Microsoft categories refreshed successfully', [
                'user_id' => $userId,
                'count' => count($categories),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing Microsoft categories', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
