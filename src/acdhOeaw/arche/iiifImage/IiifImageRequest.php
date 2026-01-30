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

    public string $id;
    public bool $info = false;
    public ParamRegion $region;
    public ParamSize $size;
    public ParamRotation $rotation;
    public ParamQuality $quality;
    public ParamFormat $format;

    public function __construct(string $request) {
        if (str_ends_with($request, '/info.json')) {
            $this->info = true;
            $this->id   = substr($request, 0, -10);
            return;
        }
        $request        = explode('/', $request);
        $qualityFormat  = explode('.', (string) array_pop($request));
        $this->format   = new ParamFormat($qualityFormat[1] ?? '');
        $this->quality  = new ParamQuality($qualityFormat[0]);
        $this->rotation = new ParamRotation((string) array_pop($request));
        $this->size     = new ParamSize((string) array_pop($request));
        $this->region   = new ParamRegion((string) array_pop($request));
        $this->id       = implode('/', $request);
    }
}
