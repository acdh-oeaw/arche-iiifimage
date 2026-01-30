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
 * See https://iiif.io/api/image/3.0/#41-region
 *
 * @author zozlak
 */
class ParamRegion {

    const FULL          = 'full';
    const SQUARE        = 'square';
    const ABSOLUTE      = '[0-9]+,[0-9]+,[0-9]+.[0-9]+';
    const RELATIVE      = 'pct:[0-9]+(?:[.][0-9]+)?,[0-9]+(?:[.][0-9]+)?,[0-9]+(?:[.][0-9]+)?,[0-9]+(?:[.][0-9]+)?';
    private const VALID = '`^(' . self::FULL . '|' . self::SQUARE . '|' . self::ABSOLUTE . '|' . self::RELATIVE . ')$`';

    public function __construct(private string $region) {
        if (!preg_match(self::VALID, $region)) {
            throw new RequestParamException("Invalid region parameter value: $region");
        }
    }

    /**
     * 
     * @return array{'x0': int, 'y0': int, 'x1': int, 'y1': int, 'w': int, 'h': int}
     */
    public function getBounds(Image $image): array {
        $imW = $image->getWidth();
        $imH = $image->getHeight();
        if ($this->region === self::FULL) {
            $x0 = $y0 = 0;
            $x1 = $imW;
            $y1 = $imH;
        } elseif ($this->region === self::SQUARE) {
            $wh = min($imW, $imH);
            $x0 = (int) (($imW - $wh) / 2);
            $y0 = (int) (($imH - $wh) / 2);
            $x1 = $x0 + $wh;
            $y1 = $y0 + $wh;
        } elseif (str_starts_with($this->region, 'pct:')) {
            list($px0, $py0, $pw, $ph) = array_map(fn($x) => (float) $x, explode(',', substr($this->region, 4)));
            $x0 = (int) ($imW * $px0);
            $y0 = (int) ($imH * $py0);
            $x1 = (int) ($x0 + $imW * $pw);
            $y1 = (int) ($y0 + $imH * $ph);
        } else {
            list($x0, $y0, $w, $h) = array_map(fn($x) => (int) $x, explode(',', $this->region));
            $x1 = $x0 + $w;
            $y1 = $y0 + $h;
        }
        $x0 = min($x0, $imW);
        $x1 = min($x1, $imW);
        $y0 = min($y0, $imH);
        $y1 = min($y1, $imH);
        $w  = $x1 - $x0;
        $h  = $y1 - $y0;
        if ($w === 0 || $h === 0) {
            throw new RequestParamException("Requested region is invalid (x: $x0, y: $y0, width: $w, height: $h)");
        }
        return [
            'x0' => $x0, 'y0' => $y0,
            'x1' => $x1, 'y1' => $y1,
            'w'  => $w, 'h'  => $h,
        ];
    }
}
