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

use Psr\Log\LoggerInterface;
use quickRdf\DataFactory as DF;
use termTemplates\PredicateTemplate as PT;
use acdhOeaw\arche\lib\Repo;
use acdhOeaw\arche\lib\RepoResource;
use acdhOeaw\arche\lib\dissCache\Service;
use acdhOeaw\arche\lib\dissCache\CachePdo;
use acdhOeaw\arche\lib\dissCache\FileCache;
use acdhOeaw\arche\iiifImage\Resource;

/**
 * Description of ResourceTest
 *
 * @author zozlak
 */
class ResourceTest extends \PHPUnit\Framework\TestCase {

    const SAMPLE_RES_URI = 'https://arche.acdh.oeaw.ac.at/api/997283';

    static private Service $service;
    static private LoggerInterface $log;
    static private object $config;

    static public function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$service = new Service(__DIR__ . '/config.yaml');
        self::$log     = self::$service->getLog();
        self::$config  = self::$service->getConfig();
    }

    public function setUp(): void {
        parent::setUp();
        $cache = new FileCache(self::$config->cache->dir);
        $cache->clean(0, FileCache::BY_SIZE);
    }

    public function testParseRequestUri(): void {
        $ref = [
            'https://id.acdh.oeaw.ac.at/foo/bar',
            'full/max/90/default.jpg',
        ];
        $this->assertEquals($ref, Resource::parseRequestUri('', 'https://id.acdh.oeaw.ac.at/foo/bar/full/max/90/default.jpg'));
        $this->assertEquals($ref, Resource::parseRequestUri('https://base/url/', 'https://base/url/id.acdh.oeaw.ac.at/foo/bar/full/max/90/default.jpg'));

        $ref = [
            '12345',
            'square/pct:40/45/gray.jpg',
        ];
        $this->assertEquals($ref, Resource::parseRequestUri('', '12345/square/pct:40/45/gray.jpg'));
        $this->assertEquals($ref, Resource::parseRequestUri('https://base/url/', 'https://base/url/12345/square/pct:40/45/gray.jpg'));
    }

    public function testImage(): void {
        $res       = $this->getSampleResource();
        $iiifParam = 'full/max/0/default.webp';

        $resp1      = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $this->assertFalse($resp1->hit);
        $this->assertTrue($resp1->file);
        $this->assertFileExists($resp1->body);
        $this->assertEquals(200, $resp1->responseCode);
        $refHeaders = [
            'Content-Type' => 'image/webp',
            'Link'         => [
                '<http://iiif.io/api/image/3/level2.json>;rel="profile"',
                'https://arche-iiifimage.acdh.oeaw.ac.at/https%3A%2F%2Farche.acdh.oeaw.ac.at%2Fapi%2F997283/0,0,162,186/162,186/0/default.webp;rel="canonical"',
            ]
        ];
        $this->assertEqualsCanonicalizing($refHeaders, $resp1->headers);

        $resp1->hit = true;
        $resp2      = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $this->assertEquals($resp1, $resp2);
    }

    public function testInfo(): void {
        $res        = $this->getSampleResource();
        $resUri     = (string) $res->getUri();
        $iiifParam  = 'info.json';
        $resp1      = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $resp1Body  = json_decode($resp1->body, true);
        $this->assertFalse($resp1->hit);
        $this->assertFalse($resp1->file);
        $this->assertEquals(200, $resp1->responseCode);
        $refHeaders = [
            'Content-Type' => 'application/ld+json;profile="http://iiif.io/api/image/3/context.json"',
            'Link'         => [
                '<http://iiif.io/api/image/3/level2.json>;rel="profile"',
                'https://arche-iiifimage.acdh.oeaw.ac.at/https%3A%2F%2Farche.acdh.oeaw.ac.at%2Fapi%2F997283/info.json;rel="canonical"',
            ]
        ];
        $this->assertEqualsCanonicalizing($refHeaders, $resp1->headers);
        $refBody    = [
            "@context"         => Resource::JSONLD_CONTEXT,
            "id"               => $resUri,
            "type"             => "ImageService3",
            "protocol"         => "http://iiif.io/api/image",
            "profile"          => "level2",
            "height"           => 186,
            "width"            => 162,
            "maxHeight"        => self::$config->iiifImage->maxHeight,
            "maxWidth"         => self::$config->iiifImage->maxWidth,
            "maxArea"          => self::$config->iiifImage->maxArea,
            "preferredFormats" => ["webp"],
            "rights"           => "https://creativecommons.org/licenses/by/4.0/",
            "extraQualities"   => ["color", "gray", "bitonal"],
            "extraFormats"     => ["tif", "gif", "pdf", "jp2", "webp"],
            "extraFeatures"    => ["canonicalLinkHeader", "cors", "jsonldMediaType",
                "mirroring", "profileLinkHeader", "regionByPct", "regionByPx", "regionSquare",
                "rotationArbitrary", "rotationBy90s", "sizeByConfinedWh", "sizeByH",
                "sizeByPct", "sizeByW", "sizeByH", "sizeUpscaling"],
            "partOf"           => [
                [
                    "id"    => "https://hdl.handle.net/21.11115/0000-0013-BE8D-7",
                    "type"  => "Dataset",
                    "label" => "Digitales Archiv der Internationalen Gustav Mahler Gesellschaft",
                ],
            ],
            "seeAlso"          => [
                [
                    "id"     => $resUri,
                    "type"   => "Image",
                    "label"  => "Source image",
                    "format" => "{acdh:hasFormat}",
                ],
                [
                    "id"      => "$resUri/metadata?format=text%2Fturtle",
                    "type"    => "Dataset",
                    "label"   => "RDF metadata",
                    "format"  => "text/turtle",
                    "profile" => "https://vocabs.acdh.oeaw.ac.at/#schema",
                ],
            ],
        ];
        $this->assertEqualsCanonicalizing($refBody, $resp1Body);

        $resp2 = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $this->assertTrue($resp2->hit);
        $this->assertFalse($resp2->file);
        $this->assertEquals(200, $resp2->responseCode);
        $this->assertEquals($resp1->body, $resp2->body);
    }

    public function testHashChangedImage(): void {
        $res       = $this->getSampleResource();
        $iiifParam = 'full/max/0/default.webp';

        $resp1      = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $this->assertFalse($resp1->hit);
        $this->assertTrue($resp1->file);
        $this->assertFileExists($resp1->body);
        $this->assertEquals(200, $resp1->responseCode);
        $refHeaders = [
            'Content-Type' => 'image/webp',
            'Link'         => [
                '<http://iiif.io/api/image/3/level2.json>;rel="profile"',
                'https://arche-iiifimage.acdh.oeaw.ac.at/https%3A%2F%2Farche.acdh.oeaw.ac.at%2Fapi%2F997283/0,0,162,186/162,186/0/default.webp;rel="canonical"',
            ]
        ];
        $this->assertEqualsCanonicalizing($refHeaders, $resp1->headers);

        // change resource content hash in the metadata
        $hashProp = DF::namedNode(self::$config->schema->hash);
        $meta     = $res->getGraph();
        $meta->delete(new PT($hashProp));
        $meta->add(DF::quadNoSubject($hashProp, DF::literal('sha1:123456789')));
        $resp2    = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $this->assertEquals($resp1, $resp2);

        // try again without changes
        $resp3      = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $resp2->hit = true;
        $this->assertEquals($resp2, $resp3);
    }

    public function testHashChangedInfo(): void {
        $res       = $this->getSampleResource();
        $iiifParam = 'info.json';

        $resp1 = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $this->assertFalse($resp1->hit);
        $this->assertFalse($resp1->file);
        $this->assertEquals(200, $resp1->responseCode);

        // change resource content hash in the metadata
        $hashProp = DF::namedNode(self::$config->schema->hash);
        $meta     = $res->getGraph();
        $meta->delete(new PT($hashProp));
        $meta->add(DF::quadNoSubject($hashProp, DF::literal('sha1:123456789')));
        $resp2    = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $this->assertEquals($resp1, $resp2);

        // try again without changes
        $resp3      = Resource::cacheHandler($res, [$iiifParam], self::$config);
        $resp2->hit = true;
        $this->assertEquals($resp2, $resp3);
    }

    public function testInfoAccept(): void {
        $res = $this->getSampleResource();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $resp                   = Resource::cacheHandler($res, ['info.json'], self::$config);
        $this->assertEquals('application/json', $resp->headers['Content-Type'] ?? '');

        $_SERVER['HTTP_ACCEPT'] = 'text/plain';
        $this->expectException(\zozlak\httpAccept\NoMatchException::class);
        $this->expectExceptionCode(406);
        Resource::cacheHandler($res, ['info.json'], self::$config);
    }

    public function testAuth(): void {
        
    }

    private function getSampleResource(): RepoResource {
        $repo = Repo::factoryFromUrl('https://arche.acdh.oeaw.ac.at/api/');
        $res  = new RepoResource(self::SAMPLE_RES_URI, $repo);
        $cfg  = self::$config->dissCacheService;
        $res->loadMetadata(true, $cfg->metadataMode, $cfg->parentProperty, $cfg->resourceProperties, $cfg->relativesProperties);
        //echo $res->getGraph()->getDataset();
        return $res;
    }
}
