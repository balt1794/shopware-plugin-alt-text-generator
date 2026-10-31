<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Finds the images in the media library, writes alt text for them and saves it in the
 * media's own alt field, which the storefront already uses as the alt attribute.
 */
class MediaAltTextService
{
    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
    private const MAX_IMAGES = 2000;

    /**
     * @param EntityRepository<\Shopware\Core\Content\Media\MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly FilesystemOperator $publicFilesystem,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly ImagePreparer $preparer,
        private readonly AltTextClient $client
    ) {
    }

    /**
     * @return array{files: list<array<string, mixed>>, images: int, missing: int}
     */
    public function list(Context $context, string $filter): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('mimeType', self::IMAGE_MIME_TYPES));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(self::MAX_IMAGES);

        $entities = $this->mediaRepository->search($criteria, $context)->getEntities();

        $files = [];
        $missing = 0;
        foreach ($entities as $media) {
            $alt = trim((string) $media->getTranslation('alt'));
            if ($alt === '') {
                ++$missing;
            }
            $files[] = [
                'id' => $media->getId(),
                'name' => $this->nameOf($media),
                'url' => $media->getUrl(),
                'alt' => $alt,
                'mimeType' => $media->getMimeType(),
            ];
        }

        $images = \count($files);
        if ($filter !== 'all') {
            $files = array_values(array_filter($files, static fn (array $file): bool => $file['alt'] === ''));
        }

        return ['files' => $files, 'images' => $images, 'missing' => $missing];
    }

    /**
     * @return array{altText: string, creditsLeft: int|null}
     *
     * @throws AltTextException
     */
    public function generate(string $mediaId, Context $context, ?string $language = null): array
    {
        $media = $this->find($mediaId, $context);
        if ($media === null) {
            throw new AltTextException('Image not found.', 404);
        }

        return $this->client->generate($this->dataUrlFor($media), $language);
    }

    /** Used for automatic mode: writes alt text only when the image has none. */
    public function generateIfMissing(string $mediaId, Context $context): bool
    {
        $media = $this->find($mediaId, $context);
        if ($media === null || trim((string) $media->getTranslation('alt')) !== '') {
            return false;
        }
        if (!\in_array($media->getMimeType(), self::IMAGE_MIME_TYPES, true)) {
            return false;
        }

        $result = $this->client->generate($this->dataUrlFor($media));
        $this->save([['id' => $mediaId, 'alt' => $result['altText']]], $context);

        return true;
    }

    /**
     * @param list<array{id: string, alt: string}> $items
     */
    public function save(array $items, Context $context): int
    {
        $payload = [];
        foreach ($items as $item) {
            if (!Uuid::isValid($item['id'])) {
                continue;
            }
            $payload[] = ['id' => $item['id'], 'alt' => trim($item['alt'])];
        }
        if ($payload !== []) {
            $this->mediaRepository->update($payload, $context);
        }

        return \count($payload);
    }

    private function find(string $mediaId, Context $context): ?MediaEntity
    {
        if (!Uuid::isValid($mediaId)) {
            return null;
        }

        return $this->mediaRepository->search(new Criteria([$mediaId]), $context)->getEntities()->first();
    }

    private function dataUrlFor(MediaEntity $media): string
    {
        $path = $media->getPath();
        if ($path === '' || $media->getMimeType() === null) {
            throw new AltTextException('This image has no file yet.', 422);
        }

        try {
            $bytes = ($media->isPrivate() ? $this->privateFilesystem : $this->publicFilesystem)->read($path);
        } catch (\Throwable $e) {
            throw new AltTextException('Could not read the image file.', 500, $e);
        }

        $dataUrl = $this->preparer->toDataUrl($bytes, $media->getMimeType());
        if ($dataUrl === null) {
            throw new AltTextException('This image type is not supported or the image is too large.', 415);
        }

        return $dataUrl;
    }

    private function nameOf(MediaEntity $media): string
    {
        $name = (string) $media->getFileName();
        $extension = (string) $media->getFileExtension();

        return $extension !== '' ? $name . '.' . $extension : $name;
    }
}
