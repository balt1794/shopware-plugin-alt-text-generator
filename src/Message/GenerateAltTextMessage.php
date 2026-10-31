<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/** Queued when an image is uploaded, so the upload itself never waits for the AI. */
final class GenerateAltTextMessage implements AsyncMessageInterface
{
    public function __construct(public readonly string $mediaId)
    {
    }
}
