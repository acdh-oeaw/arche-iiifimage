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

/**
 * Description of TransformImagick
 *
 * @author zozlak
 */
class ImageImagick implements ImageInterface {

    private Imagick $img;

    public function __construct(string $path) {
        $this->img = new Imagick($path);
    }

    public function transform(string $targetFile, Bounds $bounds, Size $size,
                              IiifImageRequest $request): void {
        // region->size->rotation->quality->format
        $resize = $size->width != $this->getWidth() || $size->height != $this->getHeight();
        if ($bouds->x != 0 || $bounds->y != 0 || $bounds->width != $this->getWidth() || $bounds->height != $this->getHeight()) {
            $this->img->cropImage($bounds->width, $bounds->height, $bounds->x0, $bounds->y0);
            $resize = true;
        }
        if ($resize) {
            $this->img->resizeImage($size->width, $size->height, Imagick::FILTER_CATROM, 1);
        }
        if ($request->rotation->mirror) {
            $this->img->flipImage();
        }
        if ($request->rotation->angle != 0) {
            $this->img->rotateImage(new ImagickPixel('rgba(100%, 100%, 100%, 0.0)'), $request->rotation->angle);
        }
        if ($request->quality->quality !== ParamQuality::DEFAULT && $request->quality->quality !== ParamQuality::COLOR) {
            $this->img->transformImageColorspace(Imagick::COLORSPACE_GRAY);
            if ($request->quality->quality === ParamQuality::BITONAL) {
                $this->img->thresholdImage(0.5);
            }
        }
        $this->img->writeImage($request->format->format . ':' . $targetFile);
    }

    public function getAspectRatio(): float {
        return ((float) $this->getWidth()) / ((float) $this->getHeight());
    }

    public function getHeight(): int {
        return $this->img->getImageHeight();
    }

    public function getWidth(): int {
        return $this->img->getImageWidth();
    }
}
