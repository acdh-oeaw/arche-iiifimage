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
 * See https://iiif.io/api/image/3.0/#43-rotation
 *
 * @author zozlak
 */
class ParamRotation {

    private const VALID = '`^[!]?[0-9]+([.][0-9]+)?$`';

    public readonly bool $mirror;
    public readonly float $angle;

    public function __construct(string $rotation) {
        if (!preg_match(self::VALID, $rotation)) {
            throw new RequestParamException("Invalid rotation parameter value: $rotation");
        }
        if (str_starts_with($rotation, '!')) {
            $this->mirror = true;
            $rotation     = substr($rotation, 1);
        } else {
            $this->mirror = false;
        }
        $this->angle = (float) $rotation;
        if ($this->angle < 0 || $this->angle > 360) {
            throw new RequestParamException("Invalid rotation parameter value: $rotation");
        }
    }
}
