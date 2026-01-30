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

    public bool $upscale    = false;
    public bool $keepAspect = false;
    public float $percentage;
    public int $width;
    public int $height;

    public function __construct(string $size) {
        $sizeOrig = $size;
        if (!preg_match(self::VALID, $size)) {
            throw new RequestParamException("Invalid region parameter value: $size");
        }
        if (str_starts_with($size, '^')) {
            $this->upscale = true;
            $size          = substr($size, 1);
        }
        if ($size === 'max') {
            $this->width  = PHP_INT_MAX;
            $this->height = PHP_INT_MAX;
        } elseif (str_starts_with($size, 'pct:')) {
            $this->percentage = (float) substr($size, 4);
        } else {
            if (str_starts_with($size, '!')) {
                $this->keepAspect = true;
                $size             = substr($size, 1);
            }
            list($w, $h) = explode(',', $size);
            $this->width  = (int) $w;
            $this->height = (int) $h;
            if ($this->width === 0 && $this->height === 0) {
                throw new RequestParamException("Invalid size requested $sizeOrig");
            }
        }
    }

    /**
     * 
     * @return array{'w': int, 'h': int}
     */
    public function getSize(Image $image): array {
        return ['w' => 0, 'h' => 0];
    }
}
