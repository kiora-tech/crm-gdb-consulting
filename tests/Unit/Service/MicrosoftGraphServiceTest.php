<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\MicrosoftToken;
use App\Entity\User;
use App\Service\MicrosoftGraphService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Unit tests for MicrosoftGraphService calendar-related methods.
 * Note: This test focuses on the calendar event methods (createEventFromCalendarEvent, getEventById, updateEvent).
 */
class MicrosoftGraphServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private HttpClientInterface&MockObject $httpClient;
    private MicrosoftGraphService $graphService;
    private string $clientId = 'test-client-id';
    private string $clientSecret = 'test-client-secret';

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        // Use reflection to inject mocked HttpClient
        $this->graphService = new MicrosoftGraphService(
            $this->entityManager,
            $this->logger,
            $this->clientId,
            $this->clientSecret
        );

        $reflection = new \ReflectionClass($this->graphService);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->graphService, $this->httpClient);
    }

    public function testCreateEventFromCalendarEventWithValidDataReturnsEvent(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('valid-access-token');
        $user->method('getDefaultCalendarId')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $response->method('getContent')->willReturn(json_encode([
            'id' => 'microsoft-event-123',
            'subject' => 'Test Event',
            'start' => ['dateTime' => '2025-10-20T10:00:00', 'timeZone' => 'Europe/Paris'],
            'end' => ['dateTime' => '2025-10-20T11:00:00', 'timeZone' => 'Europe/Paris'],
            'location' => ['displayName' => 'Office'],
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://graph.microsoft.com/v1.0/me/events',
                $this->callback(function ($options) {
                    return isset($options['headers']['Authorization'])
                        && isset($options['json']['subject'])
                        && 'Test Event' === $options['json']['subject'];
                })
            )
            ->willReturn($response);

        $result = $this->graphService->createEventFromCalendarEvent(
            $user,
            'Test Event',
            '2025-10-20T10:00:00',
            '2025-10-20T11:00:00',
            'Test description',
            'Office'
        );

        $this->assertSame('microsoft-event-123', $result['id']);
        $this->assertSame('Test Event', $result['subject']);
    }

    public function testCreateEventFromCalendarEventWithSpecificCalendarUsesCalendarEndpoint(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('valid-access-token');
        $user->method('getDefaultCalendarId')->willReturn('calendar-123');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $response->method('getContent')->willReturn(json_encode([
            'id' => 'microsoft-event-123',
            'subject' => 'Test Event',
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://graph.microsoft.com/v1.0/me/calendars/calendar-123/events',
                $this->anything()
            )
            ->willReturn($response);

        $this->graphService->createEventFromCalendarEvent(
            $user,
            'Test Event',
            '2025-10-20T10:00:00',
            '2025-10-20T11:00:00'
        );
    }

    public function testCreateEventFromCalendarEventWithExpiredTokenRefreshesToken(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $user->method('getId')->willReturn(1);
        $token->method('isExpired')->willReturn(true);
        $token->method('getAccessToken')->willReturn('refreshed-access-token');
        $token->method('getRefreshToken')->willReturn('refresh-token');
        $token->method('getUser')->willReturn($user);
        $token->method('getExpiresAt')->willReturn(new \DateTime('+1 hour'));
        $user->method('getDefaultCalendarId')->willReturn(null);

        // Mock refresh token response
        $refreshResponse = $this->createMock(ResponseInterface::class);
        $refreshResponse->method('getStatusCode')->willReturn(200);
        $refreshResponse->method('getContent')->willReturn(json_encode([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]));

        // Mock event creation response
        $eventResponse = $this->createMock(ResponseInterface::class);
        $eventResponse->method('getStatusCode')->willReturn(201);
        $eventResponse->method('getContent')->willReturn(json_encode([
            'id' => 'microsoft-event-123',
            'subject' => 'Test Event',
        ]));

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($refreshResponse, $eventResponse);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->graphService->createEventFromCalendarEvent(
            $user,
            'Test Event',
            '2025-10-20T10:00:00',
            '2025-10-20T11:00:00'
        );

        $this->assertArrayHasKey('id', $result);
    }

    public function testCreateEventFromCalendarEventWith401ResponseRetriesWithRefreshedToken(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $user->method('getId')->willReturn(1);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('access-token');
        $token->method('getRefreshToken')->willReturn('refresh-token');
        $token->method('getUser')->willReturn($user);
        $token->method('getExpiresAt')->willReturn(new \DateTime('+1 hour'));
        $user->method('getDefaultCalendarId')->willReturn(null);

        // First request returns 401
        $firstResponse = $this->createMock(ResponseInterface::class);
        $firstResponse->method('getStatusCode')->willReturn(401);

        // Refresh token response
        $refreshResponse = $this->createMock(ResponseInterface::class);
        $refreshResponse->method('getStatusCode')->willReturn(200);
        $refreshResponse->method('getContent')->willReturn(json_encode([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]));

        // Retry request succeeds
        $retryResponse = $this->createMock(ResponseInterface::class);
        $retryResponse->method('getStatusCode')->willReturn(201);
        $retryResponse->method('getContent')->willReturn(json_encode([
            'id' => 'microsoft-event-123',
            'subject' => 'Test Event',
        ]));

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls($firstResponse, $refreshResponse, $retryResponse);

        $result = $this->graphService->createEventFromCalendarEvent(
            $user,
            'Test Event',
            '2025-10-20T10:00:00',
            '2025-10-20T11:00:00'
        );

        $this->assertSame('microsoft-event-123', $result['id']);
    }

    public function testCreateEventFromCalendarEventWithNoTokenThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getMicrosoftToken')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User has no Microsoft token');

        $this->graphService->createEventFromCalendarEvent(
            $user,
            'Test Event',
            '2025-10-20T10:00:00',
            '2025-10-20T11:00:00'
        );
    }

    public function testGetEventByIdWithValidEventIdReturnsEvent(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('valid-access-token');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode([
            'id' => 'microsoft-event-123',
            'subject' => 'Test Event',
            'start' => ['dateTime' => '2025-10-20T10:00:00'],
            'end' => ['dateTime' => '2025-10-20T11:00:00'],
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://graph.microsoft.com/v1.0/me/events/microsoft-event-123',
                $this->callback(function ($options) {
                    return isset($options['headers']['Authorization']);
                })
            )
            ->willReturn($response);

        $result = $this->graphService->getEventById($user, 'microsoft-event-123');

        $this->assertIsArray($result);
        $this->assertSame('microsoft-event-123', $result['id']);
        $this->assertSame('Test Event', $result['subject']);
    }

    public function testGetEventByIdWithNotFoundEventReturnsNull(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('valid-access-token');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->graphService->getEventById($user, 'non-existent-event');

        $this->assertNull($result);
    }

    public function testGetEventByIdWith404ExceptionReturnsNull(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $user->method('getId')->willReturn(1);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('valid-access-token');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('HTTP 404 returned'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error fetching event by ID', $this->arrayHasKey('error'));

        $result = $this->graphService->getEventById($user, 'non-existent-event');

        $this->assertNull($result);
    }

    public function testGetEventByIdWithExpiredTokenRefreshesAndRetries(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $user->method('getId')->willReturn(1);
        $token->method('isExpired')->willReturn(true);
        $token->method('getAccessToken')->willReturn('new-access-token');
        $token->method('getRefreshToken')->willReturn('refresh-token');
        $token->method('getUser')->willReturn($user);
        $token->method('getExpiresAt')->willReturn(new \DateTime('+1 hour'));

        // Mock refresh token response
        $refreshResponse = $this->createMock(ResponseInterface::class);
        $refreshResponse->method('getStatusCode')->willReturn(200);
        $refreshResponse->method('getContent')->willReturn(json_encode([
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
        ]));

        // Mock get event response
        $eventResponse = $this->createMock(ResponseInterface::class);
        $eventResponse->method('getStatusCode')->willReturn(200);
        $eventResponse->method('getContent')->willReturn(json_encode([
            'id' => 'microsoft-event-123',
            'subject' => 'Test Event',
        ]));

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($refreshResponse, $eventResponse);

        $result = $this->graphService->getEventById($user, 'microsoft-event-123');

        $this->assertIsArray($result);
        $this->assertSame('microsoft-event-123', $result['id']);
    }

    public function testUpdateEventWithValidDataReturnsUpdatedEvent(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $user->method('getId')->willReturn(1);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('valid-access-token');

        $eventData = [
            'subject' => 'Updated Event',
            'start' => ['dateTime' => '2025-10-20T14:00:00'],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode([
            'id' => 'microsoft-event-123',
            'subject' => 'Updated Event',
            'start' => ['dateTime' => '2025-10-20T14:00:00'],
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'PATCH',
                'https://graph.microsoft.com/v1.0/me/events/microsoft-event-123',
                $this->callback(function ($options) use ($eventData) {
                    return isset($options['headers']['Authorization'])
                        && $options['json'] === $eventData;
                })
            )
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Calendar event updated successfully', $this->arrayHasKey('event_id'));

        $result = $this->graphService->updateEvent($user, 'microsoft-event-123', $eventData);

        $this->assertSame('microsoft-event-123', $result['id']);
        $this->assertSame('Updated Event', $result['subject']);
    }

    public function testUpdateEventWith401ResponseRetriesWithRefreshedToken(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $user->method('getId')->willReturn(1);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('access-token');
        $token->method('getRefreshToken')->willReturn('refresh-token');
        $token->method('getUser')->willReturn($user);
        $token->method('getExpiresAt')->willReturn(new \DateTime('+1 hour'));

        $eventData = ['subject' => 'Updated Event'];

        // First request returns 401
        $firstResponse = $this->createMock(ResponseInterface::class);
        $firstResponse->method('getStatusCode')->willReturn(401);

        // Refresh token response
        $refreshResponse = $this->createMock(ResponseInterface::class);
        $refreshResponse->method('getStatusCode')->willReturn(200);
        $refreshResponse->method('getContent')->willReturn(json_encode([
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
        ]));

        // Retry request succeeds
        $retryResponse = $this->createMock(ResponseInterface::class);
        $retryResponse->method('getStatusCode')->willReturn(200);
        $retryResponse->method('getContent')->willReturn(json_encode([
            'id' => 'microsoft-event-123',
            'subject' => 'Updated Event',
        ]));

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls($firstResponse, $refreshResponse, $retryResponse);

        $result = $this->graphService->updateEvent($user, 'microsoft-event-123', $eventData);

        $this->assertSame('microsoft-event-123', $result['id']);
    }

    public function testUpdateEventWithExceptionThrowsRuntimeException(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $user->method('getId')->willReturn(1);
        $token->method('isExpired')->willReturn(false);
        $token->method('getAccessToken')->willReturn('valid-access-token');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Network error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error updating calendar event', $this->arrayHasKey('error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to update calendar event');

        $this->graphService->updateEvent($user, 'microsoft-event-123', ['subject' => 'Updated']);
    }

    public function testHasValidTokenWithValidTokenReturnsTrue(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $token->method('isExpired')->willReturn(false);

        $result = $this->graphService->hasValidToken($user);

        $this->assertTrue($result);
    }

    public function testHasValidTokenWithExpiredTokenReturnsFalse(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(MicrosoftToken::class);

        $user->method('getMicrosoftToken')->willReturn($token);
        $token->method('isExpired')->willReturn(true);

        $result = $this->graphService->hasValidToken($user);

        $this->assertFalse($result);
    }

    public function testHasValidTokenWithNoTokenReturnsFalse(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getMicrosoftToken')->willReturn(null);

        $result = $this->graphService->hasValidToken($user);

        $this->assertFalse($result);
    }
}
