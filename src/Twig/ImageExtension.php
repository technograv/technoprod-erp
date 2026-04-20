<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ImageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('base64_image', [$this, 'imageToBase64']),
        ];
    }

    public function imageToBase64(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            return '';
        }

        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            return '';
        }

        // Détecter le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imagePath);
        finfo_close($finfo);

        $base64 = base64_encode($imageData);

        return 'data:' . $mimeType . ';base64,' . $base64;
    }
}
