<?php

/*
 * The MIT License
 *
 * Copyright 2026 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\arche\iiifImage;

use Imagick;
use ImagickPixel;
use ReflectionClass;

/**
 * Imagick library based implementation of the ImageInterface.
 *
 * @author zozlak
 */
class ImageImagick implements ImageInterface {

    private const DEFAULT_BACKGROUND               = 'rgba(100%, 100%, 100%, 0.0)';
    // Imagick::FILTER_*
    private const DEFAULT_FILTER                   = 'CATROM';
    private const DEFAULT_BLUR                     = 1;
    private const DEFAULT_BITONAL_THRESHLD         = 0.5;
    public const DEFAULT_PNG_COMPRESSION_LEVEL     = 9;
    public const DEFAULT_JPG_QUALITY               = 70;
    // Imagick::COMPRESSION_*
    public const DEFAULT_PDF_COMPRESSION           = 'JPEG';
    public const DEFAULT_PDF_QUALITY               = 70;
    // Imagick::COMPRESSION_*
    public const DEFAULT_TIFF_COMPRESSION          = 'LZW';
    public const DEFAULT_TIFF_QUALITY              = 70;
    public const DEFAULT_TIFF_TILE_GEOMETRY        = '256x256';
    public const DEFAULT_TIFF_EXIF_PROPERTIES      = 'false';
    public const DEFAULT_TIFF_GPS_PROPERTIES       = 'false';
    public const DEFAULT_TIFF_PRESERVE_COMPRESSION = 'false';
    // https://imagemagick.org/script/jp2.php
    public const DEFAULT_JP2_NUMBER_RESOLUTIONS    = 1;
    public const DEFAULT_JP2_QUALITY               = null;
    public const DEFAULT_JP2_RATE                  = '10';
    public const DEFAULT_JP2_PROGRESSION_ORDER     = 'LRCP';
    // https://imagemagick.org/script/webp.php
    public const DEFAULT_WEBP_ALPHA_COMPRESSION    = 1;
    public const DEFAULT_WEBP_ALPHA_FILTERING      = 1;
    public const DEFAULT_WEBP_ALPHA_QUALITY        = 100;
    public const DEFAULT_WEBP_EXACT                = 'false';
    public const DEFAULT_WEBP_AUTO_FILTER          = 'false';
    public const DEFAULT_WEBP_EMULATE_JPEG_SIZE    = 'false';
    public const DEFAULT_WEBP_FILTER_SHARPNESS     = null;
    public const DEFAULT_WEBP_FILTER_STRENGTH      = null;
    public const DEFAULT_WEBP_FILTER_TYPE          = null;
    public const DEFAULT_WEBP_IMAGE_HINT           = null;
    public const DEFAULT_WEBP_LOSSLESS             = 'false';
    public const DEFAULT_WEBP_LOW_MEMORY           = 'true';
    public const DEFAULT_WEBP_METHOD               = 6;
    public const DEFAULT_WEBP_PREPROCESSING        = null;
    public const DEFAULT_WEBP_PARTITIONS           = null;
    public const DEFAULT_WEBP_PARTITION_LIMIT      = null;
    public const DEFAULT_WEBP_PASS                 = null;
    public const DEFAULT_WEBP_SEGMENTS             = null;
    public const DEFAULT_WEBP_SHOW_COMPRESSED      = null;
    public const DEFAULT_WEBP_SNS_STRENGTH         = null;
    public const DEFAULT_WEBP_TARGET_SIZE          = null;
    public const DEFAULT_WEBP_TARGET_PSNR          = null;
    public const DEFAULT_WEBP_THREAD_LEVEL         = 0;
    public const DEFAULT_WEBP_USE_SHARP_YUV        = 'false';

    private Imagick $img;
    private int $width;
    private int $height;

    public function __construct(private string $path,
                                private ServiceConfig $serviceConfig) {
        
    }

    /**
     * https://phpimagick.com/Imagick
     */
    public function transform(string $targetFile, IiifImageRequest $request): void {
        $cfg    = $this->serviceConfig->backendConfig;
        $this->load();
        $bounds = $request->getBounds($this);
        $size   = $request->getSize($this, $this->serviceConfig, $bounds);
        // region->size->rotation->quality->format
        $resize = $size->width != $this->width || $size->height != $this->height;
        if ($bounds->x0 != 0 || $bounds->y0 != 0 || $bounds->width != $this->width || $bounds->height != $this->height) {
            $this->img->cropImage($bounds->width, $bounds->height, $bounds->x0, $bounds->y0);
            $resize = true;
        }
        if ($resize) {
            $filter          = $cfg['resample'] ??= self::DEFAULT_FILTER;
            $filter          = (int) $this->getImagickConstant("FILTER_" . $filter);
            $blur            = $cfg['blur']     ??= self::DEFAULT_BLUR;
            $this->img->resizeImage($size->width, $size->height, $filter, $blur);
        }
        if ($request->rotation->mirror) {
            $this->img->flipImage();
        }
        if ($request->rotation->angle != 0) {
            $background        = $cfg['background'] ??= self::DEFAULT_BACKGROUND;
            $this->img->rotateImage(new ImagickPixel($background), $request->rotation->angle);
        }
        $quality = $request->quality->quality;
        if (!in_array($quality, [ParamQuality::DEFAULT, ParamQuality::COLOR])) {
            if (!in_array($quality, [ParamQuality::BITONAL, ParamQuality::GRAY])) {
                throw new IiifImageException("Unsupported quality: $quality");
            }
            $this->img->transformImageColorspace(Imagick::COLORSPACE_GRAY);
            if ($quality === ParamQuality::BITONAL) {
                $threshold = $cfg['bitonalThreshold'] ?? self::DEFAULT_BITONAL_THRESHLD;
                $this->img->thresholdImage($threshold);
            }
        }
        // https://imagemagick.org/script/formats.php
        $format        = match ($request->format->format) {
            'tif' => 'tiff',
            default => $request->format->format,
        };
        $this->setOutputOptions($format, $cfg);
        $targetFileTmp = $targetFile . '_' . rand();
        $this->img->writeImage($format . ':' . $targetFileTmp);
        rename($targetFileTmp, $targetFile);
        unset($this->img);
    }

    public function getAspectRatio(): float {
        return ((float) $this->getWidth()) / ((float) $this->getHeight());
    }

    public function getHeight(): int {
        $this->load();
        return $this->height;
    }

    public function getWidth(): int {
        $this->load();
        return $this->width;
    }

    /**
     * 
     * @param array<string, mixed> $cfg
     */
    private function setOutputOptions(string $format, array $cfg): void {
        $img      = $this->img;
        $defaults = $this->getDefaultOutputOptions($format);
        if ($format === 'pdf') {
            $img->setPage($img->getImageWidth(), $img->getImageHeight(), 0, 0);
        }
        foreach ($defaults as $i) {
            $keyImagick = $i['imagick'];
            $value      = $cfg[$i['config']] ?? $i['value'];
            if ($value !== null) {
                if (str_ends_with($keyImagick, ':quality')) {
                    $img->setImageCompressionQuality($value);
                    $img->setCompressionQuality($value);
                } elseif (str_ends_with($keyImagick, ':compression')) {
                    $value = $this->getImagickConstant("COMPRESSION_$value");
                    $img->setImageCompression($value);
                    $img->setCompression($value);
                } else {
                    $img->setOption($keyImagick, $value);
                }
            }
        }
    }

    /**
     * 
     * @return array<array{'imagick': string, 'config': string, 'value': mixed}>
     */
    private function getDefaultOutputOptions(string $format): array {
        $prefix     = "DEFAULT_" . strtoupper($format) . "_";
        $filter     = fn($v, $k) => str_starts_with($k, $prefix);
        $reflection = new ReflectionClass($this);
        $constants  = array_filter($reflection->getConstants(), $filter, \ARRAY_FILTER_USE_BOTH);

        $formatSkipPos = strlen($format) + 1;
        $defaults      = [];
        foreach ($constants as $k => $v) {
            $k          = strtolower(substr($k, 8));
            $kImagick   = $format . ':' . str_replace('_', '-', substr($k, $formatSkipPos));
            $kCfg       = str_replace('_', '', lcfirst(ucwords($k, '_')));
            $defaults[] = ['imagick' => $kImagick, 'config' => $kCfg, 'value' => $v];
        }
        return $defaults;
    }

    private function getImagickConstant(string $name): mixed {
        $reflection = new ReflectionClass('imagick');
        return $reflection->getConstant(strtoupper($name));
    }

    private function load(): void {
        if (!isset($this->img)) {
            $this->img    = new Imagick($this->path);
            $this->width  = $this->img->getImageWidth();
            $this->height = $this->img->getImageHeight();
        }
    }
}
