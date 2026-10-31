<?php declare(strict_types=1);

namespace AltTextGeneratorAI\Tests\Service;

use AltTextGeneratorAI\Service\ImagePreparer;
use PHPUnit\Framework\TestCase;

final class ImagePreparerTest extends TestCase
{
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function decode(string $dataUrl): array
    {
        self::assertStringStartsWith('data:image/jpeg;base64,', $dataUrl);
        $size = getimagesizefromstring((string) base64_decode(substr($dataUrl, \strlen('data:image/jpeg;base64,'))));
        self::assertIsArray($size);

        return [$size[0], $size[1]];
    }

    public function testShrinksALargeImageToTheLongestSideLimit(): void
    {
        $dataUrl = (new ImagePreparer())->toDataUrl($this->png(3000, 1500), 'image/png');

        self::assertNotNull($dataUrl);
        self::assertSame([1024, 512], $this->decode($dataUrl));
    }

    public function testShrinksATallImage(): void
    {
        $dataUrl = (new ImagePreparer())->toDataUrl($this->png(800, 2400), 'image/png');

        self::assertNotNull($dataUrl);
        self::assertSame([341, 1024], $this->decode($dataUrl));
    }

    public function testDoesNotEnlargeASmallImage(): void
    {
        $dataUrl = (new ImagePreparer())->toDataUrl($this->png(300, 200), 'image/png');

        self::assertNotNull($dataUrl);
        self::assertSame([300, 200], $this->decode($dataUrl));
    }

    public function testSendsTheOriginalWhenItCannotBeDecodedButIsAKnownType(): void
    {
        $dataUrl = (new ImagePreparer())->toDataUrl('not really an image', 'image/png');

        self::assertSame('data:image/png;base64,' . base64_encode('not really an image'), $dataUrl);
    }

    public function testRejectsFilesThatAreNotImages(): void
    {
        self::assertNull((new ImagePreparer())->toDataUrl('%PDF-1.7', 'application/pdf'));
    }
}
