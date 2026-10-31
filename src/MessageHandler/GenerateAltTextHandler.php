<?php declare(strict_types=1);

namespace AltTextGeneratorAI\MessageHandler;

use AltTextGeneratorAI\Message\GenerateAltTextMessage;
use AltTextGeneratorAI\Service\AltTextClient;
use AltTextGeneratorAI\Service\AltTextException;
use AltTextGeneratorAI\Service\MediaAltTextService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateAltTextHandler
{
    public function __construct(
        private readonly MediaAltTextService $media,
        private readonly AltTextClient $client,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(GenerateAltTextMessage $message): void
    {
        // The setting may have been switched off between the upload and now.
        if (!$this->client->autoGenerate() || $this->client->apiKey() === '') {
            return;
        }

        try {
            $this->media->generateIfMissing($message->mediaId, new Context(new SystemSource()));
        } catch (AltTextException $e) {
            // Never retry a batch of uploads against a bad key or an empty balance.
            $this->logger->warning('Alt Text Generator AI: ' . $e->getMessage(), ['mediaId' => $message->mediaId]);
        }
    }
}
