<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Tests\Service;

use AltTextGeneratorAI\Service\AltTextClient;
use AltTextGeneratorAI\Service\AltTextException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AltTextClientTest extends TestCase
{
    /** @param array<string, mixed> $values */
    private function config(array $values): SystemConfigService
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (string $key) => $values[$key] ?? null);
        $config->method('getBool')->willReturnCallback(static fn (string $key) => (bool) ($values[$key] ?? false));

        return $config;
    }

    private function client(MockResponse $response, array $config = ['AltTextGeneratorAI.config.apiKey' => 'secret']): AltTextClient
    {
        return new AltTextClient(new MockHttpClient($response), $this->config($config));
    }

    public function testReturnsTheTextAndTheCreditsLeft(): void
    {
        $result = $this->client(new MockResponse('A red bridge at dusk.', ['response_headers' => ['X-Credits-Left: 41']]))
            ->generate('data:image/jpeg;base64,AA');

        self::assertSame(['altText' => 'A red bridge at dusk.', 'creditsLeft' => 41], $result);
    }

    public function testSendsTheKeyLanguageAndImage(): void
    {
        $captured = null;
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured) {
            $captured = [$method, $url, json_decode($options['body'], true)];

            return new MockResponse('ok');
        });
        (new AltTextClient($http, $this->config(['AltTextGeneratorAI.config.apiKey' => 'secret'])))
            ->generate('data:image/jpeg;base64,AA', 'spanish');

        self::assertSame(
            ['POST', AltTextClient::DEFAULT_ENDPOINT, ['image' => 'data:image/jpeg;base64,AA', 'language' => 'spanish', 'wpkey' => 'secret']],
            $captured
        );
    }

    public function testExplainsABadKeyAndAnEmptyBalance(): void
    {
        try {
            $this->client(new MockResponse('Invalid API key', ['http_code' => 401]))->generate('x');
            self::fail('Expected an exception.');
        } catch (AltTextException $e) {
            self::assertSame('Invalid API key.', $e->getMessage());
            self::assertTrue($e->isFatal());
        }

        try {
            $this->client(new MockResponse('No credits left', ['http_code' => 402]))->generate('x');
            self::fail('Expected an exception.');
        } catch (AltTextException $e) {
            self::assertSame('No credits left on this API key.', $e->getMessage());
            self::assertTrue($e->isFatal());
        }
    }

    public function testOtherFailuresAreNotFatal(): void
    {
        try {
            $this->client(new MockResponse('Generation failed', ['http_code' => 502]))->generate('x');
            self::fail('Expected an exception.');
        } catch (AltTextException $e) {
            self::assertSame('Generation failed', $e->getMessage());
            self::assertFalse($e->isFatal());
        }
    }

    public function testAsksForAKeyWhenThereIsNone(): void
    {
        $this->expectException(AltTextException::class);
        $this->expectExceptionMessage('No API key');
        $this->client(new MockResponse('ok'), [])->generate('x');
    }

    public function testReadsTheBalanceFromTheVerifyRoute(): void
    {
        $url = null;
        $http = new MockHttpClient(static function (string $method, string $u) use (&$url) {
            $url = $u;

            return new MockResponse(json_encode(['freeRewritesLeft' => 12]));
        });
        $client = new AltTextClient($http, $this->config(['AltTextGeneratorAI.config.apiKey' => 'secret']));

        self::assertSame(12, $client->credits());
        self::assertSame('https://alttextgeneratorai.com/api/verify', $url);
    }

    public function testCreditsAreNullForAnUnknownKey(): void
    {
        self::assertNull($this->client(new MockResponse('{}', ['http_code' => 404]))->credits());
    }
}
