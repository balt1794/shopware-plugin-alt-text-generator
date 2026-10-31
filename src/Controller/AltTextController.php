<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Controller;

use AltTextGeneratorAI\Service\AltTextClient;
use AltTextGeneratorAI\Service\AltTextException;
use AltTextGeneratorAI\Service\MediaAltTextService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['api']])]
class AltTextController extends AbstractController
{
    public function __construct(
        private readonly MediaAltTextService $media,
        private readonly AltTextClient $client,
        private readonly SystemConfigService $config
    ) {
    }

    #[Route(path: '/api/_action/alt-text-generator/settings', name: 'api.action.alt-text-generator.settings', methods: ['GET'], defaults: ['_acl' => ['media:read']])]
    public function settings(): JsonResponse
    {
        return new JsonResponse($this->currentSettings());
    }

    #[Route(path: '/api/_action/alt-text-generator/settings', name: 'api.action.alt-text-generator.settings.update', methods: ['PUT'], defaults: ['_acl' => ['media:update']])]
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->toArray();
        if (\array_key_exists('autoGenerate', $data)) {
            $this->config->set(AltTextClient::CONFIG . 'autoGenerate', (bool) $data['autoGenerate']);
        }
        if (isset($data['language']) && \is_string($data['language']) && preg_match('/^[a-z][a-z \-()]{1,40}$/i', $data['language']) === 1) {
            $this->config->set(AltTextClient::CONFIG . 'language', $data['language']);
        }

        return new JsonResponse($this->currentSettings());
    }

    #[Route(path: '/api/_action/alt-text-generator/credits', name: 'api.action.alt-text-generator.credits', methods: ['GET'], defaults: ['_acl' => ['media:read']])]
    public function credits(): JsonResponse
    {
        return new JsonResponse(['credits' => $this->client->credits()]);
    }

    #[Route(path: '/api/_action/alt-text-generator/images', name: 'api.action.alt-text-generator.images', methods: ['GET'], defaults: ['_acl' => ['media:read']])]
    public function images(Request $request, Context $context): JsonResponse
    {
        return new JsonResponse($this->media->list($context, (string) $request->query->get('filter', 'missing')));
    }

    #[Route(path: '/api/_action/alt-text-generator/generate', name: 'api.action.alt-text-generator.generate', methods: ['POST'], defaults: ['_acl' => ['media:update']])]
    public function generate(Request $request, Context $context): JsonResponse
    {
        $data = $request->toArray();
        $id = $data['id'] ?? null;
        if (!\is_string($id)) {
            return new JsonResponse(['error' => 'Missing image id.'], 400);
        }
        $language = isset($data['language']) && \is_string($data['language']) ? $data['language'] : null;

        try {
            return new JsonResponse($this->media->generate($id, $context, $language));
        } catch (AltTextException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getStatus() >= 400 && $e->getStatus() < 600 ? $e->getStatus() : 500);
        }
    }

    #[Route(path: '/api/_action/alt-text-generator/save', name: 'api.action.alt-text-generator.save', methods: ['POST'], defaults: ['_acl' => ['media:update']])]
    public function save(Request $request, Context $context): JsonResponse
    {
        $items = $request->toArray()['items'] ?? null;
        if (!\is_array($items)) {
            throw RoutingException::missingRequestParameter('items');
        }

        $clean = [];
        foreach ($items as $item) {
            if (\is_array($item) && \is_string($item['id'] ?? null) && \is_string($item['alt'] ?? null)) {
                $clean[] = ['id' => $item['id'], 'alt' => $item['alt']];
            }
        }

        return new JsonResponse(['saved' => $this->media->save($clean, $context)]);
    }

    /**
     * @return array{hasApiKey: bool, autoGenerate: bool, language: string}
     */
    private function currentSettings(): array
    {
        return [
            'hasApiKey' => $this->client->apiKey() !== '',
            'autoGenerate' => $this->client->autoGenerate(),
            'language' => $this->client->language(),
        ];
    }
}
