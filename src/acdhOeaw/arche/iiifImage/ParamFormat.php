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
 * See https://iiif.io/api/image/3.0/#45-format
 *
 * @author zozlak
 */
class ParamFormat {

    const JPG           = 'jpg';
    const TIF           = 'tif';
    const PNG           = 'png';
    const GIF           = 'gif';
    const JP2           = 'jp2';
    const PDF           = 'pdf';
    const WEBP          = 'webp';
    const FORMATS       = [
        self::JPG, self::TIF, self::PNG, self::GIF, self::JP2, self::PDF, self::WEBP
    ];
    private const VALID = '`^(' . self::JPG . '|' . self::TIF . '|' . self::PNG . '|' . self::GIF . '|' . self::JP2 . '|' . self::PDF . '|' . self::WEBP . '|' . ')$`';

    public function __construct(public readonly string $format) {
        if (!preg_match(self::VALID, $format)) {
            throw new RequestParamException("Invalid format parameter value: $format");
        }
    }

    public function __toString(): string {
        return $this->format;
    }

    public function getMime(): string {
        return match ($this->format) {
            self::PDF => 'application/pdf',
            self::TIF => 'image/tiff',
            default => 'image/' . $this->format,
        };
    }
}
