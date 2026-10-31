<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Subscriber;

use AltTextGeneratorAI\Message\GenerateAltTextMessage;
use AltTextGeneratorAI\Service\AltTextClient;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class MediaUploadedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AltTextClient $client,
        private readonly MessageBusInterface $bus
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [MediaUploadedEvent::class => 'onMediaUploaded'];
    }

    public function onMediaUploaded(MediaUploadedEvent $event): void
    {
        if (!$this->client->autoGenerate() || $this->client->apiKey() === '') {
            return;
        }

        $this->bus->dispatch(new GenerateAltTextMessage($event->getMediaId()));
    }
}
