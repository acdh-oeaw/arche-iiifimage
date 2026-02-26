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
 * A container for the IIIF Image API request parameters
 * 
 * See https://iiif.io/api/image/3.0/#4-image-requests
 *
 * @author zozlak
 */
class IiifImageRequest {

    public readonly string $id;
    public readonly bool $info;

    /** @phpstan-ignore property.uninitializedReadonly */
    public readonly ParamRegion $region;

    /** @phpstan-ignore property.uninitializedReadonly */
    public readonly ParamSize $size;

    /** @phpstan-ignore property.uninitializedReadonly */
    public readonly ParamRotation $rotation;

    /** @phpstan-ignore property.uninitializedReadonly */
    public readonly ParamQuality $quality;

    /** @phpstan-ignore property.uninitializedReadonly */
    public readonly ParamFormat $format;

    public function __construct(string $request) {
        if (str_ends_with($request, 'info.json')) {
            $this->info = true;
            $this->id   = substr($request, 0, -10);
            return;
        }
        $this->info     = false;
        $request        = explode('/', $request);
        $qualityFormat  = explode('.', (string) array_pop($request));
        $this->format   = new ParamFormat($qualityFormat[1] ?? '');
        $this->quality  = new ParamQuality($qualityFormat[0]);
        $this->rotation = new ParamRotation((string) array_pop($request));
        $this->size     = new ParamSize((string) array_pop($request));
        $this->region   = new ParamRegion((string) array_pop($request));
        $this->id       = implode('/', $request);
    }

    public function getBounds(ImageInterface $image): Bounds {
        return $this->region->getBounds($image);
    }

    public function getSize(ImageInterface $image, ServiceConfig $service,
                            Bounds | null $bounds = null): Size {
        $bounds ??= $this->getBounds($image);
        return $this->size->getSize($bounds, $image, $service);
    }

    public function getCanonical(?ImageInterface $image = null,
                                 ?ServiceConfig $service = null): string {
        if ($this->info) {
            return 'info.json';
        } elseif ($image === null || $service === null) {
            throw new \BadMethodCallException("If request is not an info one, then image and service parameters have to be provided");
        }
        $bounds = $this->getBounds($image);
        $size   = $this->getSize($image, $service);
        return $bounds . '/' . $size . '/' . $this->rotation . '/' . $this->quality . '.' . $this->format;
    }
}
