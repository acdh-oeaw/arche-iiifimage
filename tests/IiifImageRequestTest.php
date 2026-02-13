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
use acdhOeaw\arche\iiifImage\ServiceConfig;
use acdhOeaw\arche\iiifImage\Size;
use acdhOeaw\arche\iiifImage\Bounds;
use acdhOeaw\arche\iiifImage\RequestParamException;
use acdhOeaw\arche\iiifImage\ImageImagick;

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
            $this->assertEquals($i, $req->format->format);
        }
        $this->expectException(RequestParamException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage("Invalid format parameter value: foo");
        new IiifImageRequest($this->buildRequestString(format: 'foo'));
    }

    public function testQuality(): void {
        foreach (ParamQuality::QUALITIES as $i) {
            $req = new IiifImageRequest($this->buildRequestString(quality: $i));
            $this->assertEquals($i, $req->quality->quality);
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
                $this->assertEquals($i, $req->rotation->angle);
                $this->assertEquals(!empty($j), $req->rotation->mirror);
            }
        }
        foreach ([-10, '-0', 360.0001, '*50'] as $i) {
            try {
                $req = new IiifImageRequest($this->buildRequestString(rotation: (string) $i));
                /** @phpstan-ignore method.impossibleType */
                $this->assertTrue(false, (string) $i);
            } catch (RequestParamException $e) {
                $this->assertEquals(400, $e->getCode());
                $this->assertEquals("Invalid rotation parameter value: $i", $e->getMessage());
            }
        }
    }

    public function testRegion(): void {
        $service   = new ServiceConfig(0, 0, []);
        $img       = ImageStub::fromDimensions(201, 100, $service);
        $testCases = [
            'full'                    => new Bounds(0, 0, 201, 100),
            'square'                  => new Bounds(50, 0, 150, 100),
            '28,30,42,55'             => new Bounds(28, 30, 70, 85), // no clip
            '37,28,129,245'           => new Bounds(37, 28, 166, 100), // clip x
            '190,0,20,95'             => new Bounds(190, 0, 201, 95), // clip y
            '0,90,300,20'             => new Bounds(0, 90, 201, 100), // clip both
            'pct:28.5,30,50.5,50'     => new Bounds(57, 30, 158, 80), // no clip
            'pct:37,28,129,45.99'     => new Bounds(74, 28, 201, 73), // clip x
            'pct:0.0001,20,20.899,95' => new Bounds(0, 20, 42, 100), // clip y
            'pct:30,60,110,100'       => new Bounds(60, 60, 201, 100), // clip both
        ];
        foreach ($testCases as $region => $output) {
            $req = new IiifImageRequest($this->buildRequestString(region: $region));
            $this->assertEquals($output, $req->getBounds($img));
        }

        $errorCases = [
            'foo'                   => 'Invalid region parameter value: foo',
            '0, 0, 100, 100'        => 'Invalid region parameter value: 0, 0, 100, 100',
            '-10,0,10,10'           => 'Invalid region parameter value: -10,0,10,10',
            '0,-10,10,10'           => 'Invalid region parameter value: 0,-10,10,10',
            '0,0,-10,10'            => 'Invalid region parameter value: 0,0,-10,10',
            '0,0,10,-10'            => 'Invalid region parameter value: 0,0,10,-10',
            '201,0,100,100'         => 'Invalid region requested: (x: 201, y: 0, width: 0, height: 100)',
            '0,100,100,100'         => 'Invalid region requested: (x: 0, y: 100, width: 100, height: 0)',
            '300,500,90,80'         => 'Invalid region requested: (x: 201, y: 100, width: 0, height: 0)',
            'pct:-1,0,100,100'      => 'Invalid region parameter value: pct:-1,0,100,100',
            'pct:0,-1,100,100'      => 'Invalid region parameter value: pct:0,-1,100,100',
            'pct:0,0,-10,100'       => 'Invalid region parameter value: pct:0,0,-10,100',
            'pct:0,0,100,-10'       => 'Invalid region parameter value: pct:0,0,100,-10',
            'pct:100,99.99,100,100' => 'Invalid region requested: (x: 201, y: 99, width: 0, height: 1)',
            'pct:99.99,120,100,100' => 'Invalid region requested: (x: 200, y: 100, width: 1, height: 0)',
            'pct:100,120,100,100'   => 'Invalid region requested: (x: 201, y: 100, width: 0, height: 0)',
        ];
        foreach ($errorCases as $region => $errorMsg) {
            try {
                $req    = new IiifImageRequest($this->buildRequestString(region: $region));
                $bounds = $req->getBounds($img);
                /** @phpstan-ignore method.impossibleType */
                $this->assertTrue(false, $region);
            } catch (RequestParamException $e) {
                $this->assertEquals(400, $e->getCode());
                $this->assertEquals($errorMsg, $e->getMessage());
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Depends('testRegion')]
    public function testSize(): void {
        $service = new ServiceConfig(300, 500, []);
        $image   = ImageStub::fromDimensions(201, 100, $service);

        $testCases = [
            'max'        => new Size(201, 100),
            '^max'       => new Size(300, 500),
            '100,'       => new Size(100, 50),
            ',75'        => new Size(151, 75),
            '^300,'      => new Size(300, 149),
            '^,129'      => new Size(259, 129),
            'pct:50.9'   => new Size(102, 51),
            '^pct:149.2' => new Size(300, 149),
            '^pct:39'    => new Size(78, 39),
            '101,100'    => new Size(101, 100),
            '^250,50'    => new Size(250, 50),
            '!100,100'   => new Size(100, 50),
            '!201,50'    => new Size(101, 50),
            '!201,100'   => new Size(201, 100),
            '^!300,500'  => new Size(300, 149),
        ];
        foreach ($testCases as $size => $output) {
            $req = new IiifImageRequest($this->buildRequestString(size: $size));
            $this->assertEquals($output, $req->getSize($image, $service));
        }

        $errorCases = [
            '100.0,15'   => 'Invalid size parameter value: 100.0,15',
            '300,'       => 'Invalid size requested: 300,',
            ',101'       => 'Invalid size requested: ,101',
            '0,'         => 'Invalid size requested: 0,',
            ',0'         => 'Invalid size requested: ,0',
            '^400,'      => 'Invalid size requested: ^400,',
            '^,501'      => 'Invalid size requested: ^,501',
            '^,250'      => 'Invalid size requested: ^,250',
            'pct:0'      => 'Invalid size requested: pct:0',
            'pct:100.3'  => 'Invalid size requested: pct:100.3',
            '^pct:149.6' => 'Invalid size requested: ^pct:149.6',
            '0,0'        => 'Invalid size requested: 0,0',
            '250,50'     => 'Invalid size requested: 250,50',
            '!100,'      => 'Invalid size parameter value: !100,',
            '!,100'      => 'Invalid size parameter value: !,100',
            '!max'       => 'Invalid size parameter value: !max',
            '^!100,'     => 'Invalid size parameter value: ^!100,',
            '^!,100'     => 'Invalid size parameter value: ^!,100',
            '^!max'      => 'Invalid size parameter value: ^!max',
            '!202,101'   => 'Invalid size requested: !202,101',
        ];
        foreach ($errorCases as $size => $errorMsg) {
            try {
                $req    = new IiifImageRequest($this->buildRequestString(size: $size));
                $bounds = $req->getBounds($image);
                $req->getSize($image, $service, $bounds);
                /** @phpstan-ignore method.impossibleType */
                $this->assertTrue(false, $size);
            } catch (RequestParamException $e) {
                $this->assertEquals(400, $e->getCode());
                $this->assertEquals($errorMsg, $e->getMessage());
            }
        }
    }

    public function testTransform(): void {
        $service = new ServiceConfig(1000, 1000, []);
        foreach (glob(__DIR__ . '/data/input.*') ?: [] as $input) {
            $image = new ImageImagick($input, $service);
            foreach (ParamFormat::FORMATS as $format) {
                $request = new IiifImageRequest($this->buildRequestString(region: 'full', size: '!500,500', rotation: '45', quality: 'gray', format: $format));
                $output  = __DIR__ . '/output.' . $format;
                if (file_exists($output)) {
                    unlink($output);
                }
                $image->transform($output, $request);
                $this->assertFileExists($output, basename($input) . ' => ' . basename($output));
            }
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
