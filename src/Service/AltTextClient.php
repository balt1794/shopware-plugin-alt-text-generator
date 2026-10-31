<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the Alt Text Generator AI service from the Shopware server, so the API key
 * is never sent to the browser.
 */
class AltTextClient
{
    public const CONFIG = 'AltTextGeneratorAI.config.';
    public const DEFAULT_ENDPOINT = 'https://alttextgeneratorai.com/api/shopware';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly SystemConfigService $config
    ) {
    }

    public function apiKey(): string
    {
        return trim((string) $this->config->get(self::CONFIG . 'apiKey'));
    }

    public function endpoint(): string
    {
        $endpoint = trim((string) $this->config->get(self::CONFIG . 'endpoint'));

        return $endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT;
    }

    public function language(): string
    {
        $language = trim((string) $this->config->get(self::CONFIG . 'language'));

        return $language !== '' ? $language : 'english';
    }

    public function autoGenerate(): bool
    {
        return $this->config->getBool(self::CONFIG . 'autoGenerate');
    }

    /**
     * @return array{altText: string, creditsLeft: int|null}
     *
     * @throws AltTextException
     */
    public function generate(string $imageDataUrl, ?string $language = null): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new AltTextException('No API key. Add it under Extensions > My extensions > Alt Text Generator AI > Configure.', 401);
        }

        try {
            $response = $this->http->request('POST', $this->endpoint(), [
                'json' => ['image' => $imageDataUrl, 'language' => $language ?: $this->language(), 'wpkey' => $apiKey],
                'timeout' => 60,
            ]);
            $status = $response->getStatusCode();
            $body = trim($response->getContent(false));
            $headers = $response->getHeaders(false);
        } catch (ExceptionInterface $e) {
            throw new AltTextException('Could not reach Alt Text Generator AI. Check the server connection and try again.', 502, $e);
        }

        if ($status === 401) {
            throw new AltTextException('Invalid API key.', 401);
        }
        if ($status === 402) {
            throw new AltTextException('No credits left on this API key.', 402);
        }
        if ($status >= 400) {
            $message = $body !== '' && \strlen($body) < 160 && !str_starts_with($body, '<') ? $body : 'Generation failed (' . $status . ').';
            throw new AltTextException($message, $status);
        }
        if ($body === '') {
            throw new AltTextException('No alt text came back. Try again.', 502);
        }

        $left = $headers['x-credits-left'][0] ?? null;

        return ['altText' => $body, 'creditsLeft' => is_numeric($left) ? (int) $left : null];
    }

    /** Credits left on the key. Checking never uses a credit. */
    public function credits(): ?int
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = $this->http->request('POST', $this->verifyEndpoint(), [
                'json' => ['apiKey' => $apiKey],
                'timeout' => 15,
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $left = $response->toArray(false)['freeRewritesLeft'] ?? null;

            return is_numeric($left) ? (int) $left : null;
        } catch (ExceptionInterface) {
            return null;
        }
    }

    private function verifyEndpoint(): string
    {
        return preg_replace('#/api/[^/]+/?$#', '/api/verify', $this->endpoint()) ?? $this->endpoint();
    }
}
