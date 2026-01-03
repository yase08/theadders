<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Maximum dimensions for thumbnails
     */
    private const THUMBNAIL_MAX_WIDTH = 800;
    private const THUMBNAIL_MAX_HEIGHT = 800;
    private const THUMBNAIL_MAX_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Maximum dimensions for product images
     */
    private const PRODUCT_IMAGE_MAX_WIDTH = 1920;
    private const PRODUCT_IMAGE_MAX_HEIGHT = 1920;
    private const PRODUCT_IMAGE_MAX_SIZE = 10 * 1024 * 1024; // 10MB

    /**
     * Process and store thumbnail image
     * Resizes to max 800x800 and compresses to max 5MB
     *
     * @param UploadedFile $file
     * @return string Storage path
     */
    public function processThumbnail(UploadedFile $file): string
    {
        return $this->processImage(
            $file,
            self::THUMBNAIL_MAX_WIDTH,
            self::THUMBNAIL_MAX_HEIGHT,
            self::THUMBNAIL_MAX_SIZE
        );
    }

    /**
     * Process and store product image
     * Resizes to max 1920x1920 and compresses to max 10MB
     *
     * @param UploadedFile $file
     * @return string Storage path
     */
    public function processProductImage(UploadedFile $file): string
    {
        return $this->processImage(
            $file,
            self::PRODUCT_IMAGE_MAX_WIDTH,
            self::PRODUCT_IMAGE_MAX_HEIGHT,
            self::PRODUCT_IMAGE_MAX_SIZE
        );
    }

    /**
     * Process image: resize and compress
     *
     * @param UploadedFile $file
     * @param int $maxWidth
     * @param int $maxHeight
     * @param int $maxSizeBytes
     * @return string Storage path
     */
    private function processImage(
        UploadedFile $file,
        int $maxWidth,
        int $maxHeight,
        int $maxSizeBytes
    ): string {
        // Get image info
        $imageInfo = getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new \Exception('Invalid image file');
        }

        [$originalWidth, $originalHeight, $imageType] = $imageInfo;

        // Create image resource from uploaded file
        $sourceImage = $this->createImageFromFile($file->getPathname(), $imageType);
        if ($sourceImage === false) {
            throw new \Exception('Failed to process image');
        }

        // Calculate new dimensions maintaining aspect ratio
        [$newWidth, $newHeight] = $this->calculateDimensions(
            $originalWidth,
            $originalHeight,
            $maxWidth,
            $maxHeight
        );

        // Resize if needed
        if ($newWidth !== $originalWidth || $newHeight !== $originalHeight) {
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG
            if ($imageType === IMAGETYPE_PNG) {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            imagecopyresampled(
                $resizedImage,
                $sourceImage,
                0, 0, 0, 0,
                $newWidth,
                $newHeight,
                $originalWidth,
                $originalHeight
            );
            
            imagedestroy($sourceImage);
            $sourceImage = $resizedImage;
        }

        // Generate unique filename
        $extension = $this->getExtensionFromType($imageType);
        $filename = 'product_images/' . Str::uuid() . '.' . $extension;
        $fullPath = storage_path('app/public/' . $filename);

        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Compress and save with quality adjustment to meet size limit
        $this->saveCompressedImage($sourceImage, $fullPath, $imageType, $maxSizeBytes);

        // Clean up
        imagedestroy($sourceImage);

        return $filename;
    }

    /**
     * Create GD image resource from file
     */
    private function createImageFromFile(string $path, int $imageType)
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    /**
     * Calculate new dimensions maintaining aspect ratio
     */
    private function calculateDimensions(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $maxHeight
    ): array {
        // If image is smaller than max, keep original size
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return [$originalWidth, $originalHeight];
        }

        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        
        return [
            (int) round($originalWidth * $ratio),
            (int) round($originalHeight * $ratio)
        ];
    }

    /**
     * Get file extension from image type constant
     */
    private function getExtensionFromType(int $imageType): string
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Save image with compression to meet max file size
     */
    private function saveCompressedImage(
        $image,
        string $path,
        int $imageType,
        int $maxSizeBytes
    ): void {
        $quality = 90; // Start with high quality
        $minQuality = 20; // Minimum acceptable quality
        
        do {
            // Save to buffer first to check size
            ob_start();
            
            match ($imageType) {
                IMAGETYPE_JPEG => imagejpeg($image, null, $quality),
                IMAGETYPE_PNG => imagepng($image, null, (int) round((100 - $quality) / 10)),
                IMAGETYPE_GIF => imagegif($image),
                IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($image, null, $quality) : imagejpeg($image, null, $quality),
                default => imagejpeg($image, null, $quality),
            };
            
            $imageData = ob_get_clean();
            $fileSize = strlen($imageData);
            
            // If size is acceptable or we've hit minimum quality, save and exit
            if ($fileSize <= $maxSizeBytes || $quality <= $minQuality) {
                file_put_contents($path, $imageData);
                return;
            }
            
            // Reduce quality for next iteration
            $quality -= 10;
            
        } while ($quality >= $minQuality);
        
        // Final save with minimum quality
        file_put_contents($path, $imageData);
    }
}
