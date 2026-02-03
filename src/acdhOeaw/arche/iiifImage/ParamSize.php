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

/**
 * See https://iiif.io/api/image/3.0/#42-size
 *
 * @author zozlak
 */
class ParamSize {

    private const VALID = '`^\\^?(max|[!]?[0-9]+,[0-9]+|[0-9]+,|,[0-9]+|pct:[0-9]+([.][0-9]+)?)$`';

    private string $sizeOrig;
    private bool $upscale;
    private bool $keepAspect;
    private float $percentage;
    private int $width;
    private int $height;

    public function __construct(string $size) {
        $this->sizeOrig = $size;
        if (!preg_match(self::VALID, $size)) {
            throw new RequestParamException("Invalid size parameter value: $size");
        }
        if (str_starts_with($size, '^')) {
            $this->upscale = true;
            $size          = substr($size, 1);
        }
        if ($size === 'max') {
            $this->width  = PHP_INT_MAX;
            $this->height = PHP_INT_MAX;
        } elseif (str_starts_with($size, 'pct:')) {
            $this->percentage = ((float) substr($size, 4)) / 100;
            if ($this->percentage <= 0) {
                throw new RequestParamException("Invalid size requested: $this->sizeOrig");
            }
            if (!($this->upscale ?? false) && $this->percentage > 1.0) {
                throw new RequestParamException("Invalid size requested: $this->sizeOrig");
            }
        } else {
            if (str_starts_with($size, '!')) {
                $this->keepAspect = true;
                $size             = substr($size, 1);
            }
            list($w, $h) = explode(',', $size);
            $this->width  = (int) $w;
            $this->height = (int) $h;
            if ($this->width <= 0 && $this->height <= 0) {
                throw new RequestParamException("Invalid size requested: $this->sizeOrig");
            }
        }

        $this->upscale    ??= false;
        $this->keepAspect ??= false;
    }

    public function getSize(Bounds $bounds, ImageInterface $image, Service $service): Size {
        if ($this->upscale) {
            $maxWidth  = $service->maxWidth;
            $maxHeight = $service->maxHeight;
        } else {
            $maxWidth  = $bounds->width;
            $maxHeight = $bounds->height;
        }

        if (isset($this->percentage)) {
            $width  = $bounds->width * $this->percentage;
            $height = $bounds->height * $this->percentage;
        } else {
            $aspectRatio = $image->getAspectRatio();
            if ($this->keepAspect) {
                $targetAspectRatio = ((float) $this->width) / ((float) $this->height);
                $width             = $height            = 0;
                if ($targetAspectRatio > $aspectRatio) {
                    $height = $this->height;
                } else {
                    $width = $this->width;
                }
            } else {
                $width  = $this->width === PHP_INT_MAX ? $maxWidth : $this->width;
                $height = $this->height === PHP_INT_MAX ? $maxHeight : $this->height;
            }

            if ($width === 0) {
                $width = ((float) $height) * $aspectRatio;
            } elseif ($height === 0) {
                $height = ((float) $width) / $aspectRatio;
            }
        }
        $size = new Size((int) round($width), (int) round($height));
        if ($size->width > $maxWidth || $size->height > $maxHeight) {
            throw new RequestParamException("Invalid size requested: $this->sizeOrig");
        }
        return $size;
    }
}
