<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Service;

/**
 * Shrinks an image to a JPEG with its longest side at most 1024 px before it is sent,
 * so a large original never goes over the network.
 */
final class ImagePreparer
{
    public const MAX_SIDE = 1024;
    private const MAX_DATA_URL_LENGTH = 5_500_000;

    /** @return string|null a data URL, or null when the image cannot be read */
    public function toDataUrl(string $bytes, string $mimeType): ?string
    {
        $dataUrl = $this->resize($bytes);
        if ($dataUrl === null && preg_match('#^image/(jpeg|png|webp|gif)$#', $mimeType) === 1) {
            $dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode($bytes);
        }

        if ($dataUrl === null || \strlen($dataUrl) > self::MAX_DATA_URL_LENGTH) {
            return null;
        }

        return $dataUrl;
    }

    private function resize(string $bytes): ?string
    {
        if (!\function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, self::MAX_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        // JPEG has no transparency, so transparent PNG and WebP areas become white.
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 80);
        $jpeg = (string) ob_get_clean();

        return $jpeg === '' ? null : 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }
}
