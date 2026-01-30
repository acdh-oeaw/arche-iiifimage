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

namespace acdhOeaw\arche\iiifImage\tests;

use acdhOeaw\arche\iiifImage\IiifImageRequest;
use acdhOeaw\arche\iiifImage\ParamFormat;
use acdhOeaw\arche\iiifImage\ParamQuality;
use acdhOeaw\arche\iiifImage\ParamRotation;
use acdhOeaw\arche\iiifImage\ParamSize;
use acdhOeaw\arche\iiifImage\ParamRegion;
use acdhOeaw\arche\iiifImage\Image;
use acdhOeaw\arche\iiifImage\RequestParamException;

/**
 * Description of IiifImageRequestTest
 *
 * @author zozlak
 */
class IiifImageRequestTest extends \PHPUnit\Framework\TestCase {

    public function testInfo(): void {
        $req = new IiifImageRequest('/foo/bar/info.json');
        $this->assertEquals('/foo/bar', $req->id);
        $this->assertTrue($req->info);
    }

    public function testFormat(): void {
        foreach (ParamFormat::FORMATS as $i) {
            $req = new IiifImageRequest($this->buildRequestString(format: $i));
            $this->assertEquals($i, $req->format->getFormat());
        }
        $this->expectException(RequestParamException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage("Invalid format parameter value: foo");
        new IiifImageRequest($this->buildRequestString(format: 'foo'));
    }

    public function testQuality(): void {
        foreach (ParamQuality::QUALITIES as $i) {
            $req = new IiifImageRequest($this->buildRequestString(quality: $i));
            $this->assertEquals($i, $req->quality->getQuality());
        }
        $this->expectException(RequestParamException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage("Invalid quality parameter value: foo");
        new IiifImageRequest($this->buildRequestString(quality: 'foo'));
    }

    public function testRotation(): void {
        foreach (['', '!'] as $j) {
            foreach ([0, 0.000, 0.0014, 97, 179.58, 360, 360.000]as $i) {
                $req = new IiifImageRequest($this->buildRequestString(rotation: "$j$i"));
                $this->assertEquals($i, $req->rotation->getAngle());
                $this->assertEquals(!empty($j), $req->rotation->getMirror());
            }
        }
        foreach ([-10, '-0', 360.0001, '*50'] as $i) {
            try {
                $req = new IiifImageRequest($this->buildRequestString(rotation: (string) $i));
            } catch (RequestParamException $e) {
                $this->assertEquals(400, $e->getCode());
                $this->assertEquals("Invalid rotation parameter value: $i", $e->getMessage());
            }
        }
    }

    public function testSize(): void {
        
    }

    public function testRegion(): void {
        $img       = Image::fromDimensions(201, 100);
        $testCases = [
            'full' => [
                'x0' => 0, 'y0' => 0, 'x1' => 201, 'y1' => 100, 'w'  => 201, 'h'  => 100],
        ];
        foreach ($testCases as $region => $output) {
            $req = new IiifImageRequest($this->buildRequestString(region: $region));
            $this->assertEquals($output, $req->region->getBounds($img));
        }
    }

    private function buildRequestString(string $id = 'resource/id',
                                        string $region = 'full',
                                        string $size = 'max',
                                        string $rotation = '0',
                                        string $quality = 'default',
                                        string $format = 'png'): string {
        return "$id/$region/$size/$rotation/$quality.$format";
    }
}
