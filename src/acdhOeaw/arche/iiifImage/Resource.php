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

use Psr\Log\LoggerInterface;
use quickRdf\DataFactory as DF;
use termTemplates\PredicateTemplate as PT;
use termTemplates\QuadTemplate as QT;
use zozlak\httpAccept\Accept;
use acdhOeaw\arche\lib\dissCache\ResponseCacheItem;
use acdhOeaw\arche\lib\dissCache\FileCache;
use acdhOeaw\arche\lib\RepoResourceInterface;

/**
 * Description of Resource
 *
 * @author zozlak
 */
class Resource {

    const JSONLD_CONTEXT               = 'http://iiif.io/api/image/3/context.json';
    const HASH                         = 'xxh128';
    const DEFAULT_MAX_DOWNLOAD_SIZE_MB = 50;

    /**
     * @return array{0: string, 1: string} list with first element being requested
     *   resource identifier and second element being IIIF Image API transform
     *   parameters
     */
    static public function parseRequestUri(string $baseUri,
                                           string | null $requestUri = null): array {
        $requestUri ??= $_SERVER['REQUEST_URI'];
        $allParam   = explode('/', substr($requestUri, strlen($baseUri)));
        $id         = implode('/', array_slice($allParam, 0, count($allParam) - 4));
        if (!is_numeric($id) && !str_starts_with($id, 'http')) {
            $id = 'https://' . $id;
        }
        $tranform = implode('/', array_slice($allParam, count($allParam) - 4));
        return [$id, $tranform];
    }

    /**
     * Gets the requested repository resource metadata and converts it to the thumbnail's
     * service ResourceMeta object.
     * 
     * @param array<mixed> $param
     */
    static public function cacheHandler(RepoResourceInterface $res,
                                        array $param, object $config,
                                        ?LoggerInterface $log = null): ResponseCacheItem {
        return (new self($res, $param[0] ?? '', $config, $log))->getResponse();
    }

    private RepoResourceInterface $res;
    private IiifImageRequest $request;
    private object $config;
    private ServiceConfig $serviceConfig;
    private FileCache $cache;
    private ImageImagick $image;
    private LoggerInterface | null $log;

    public function __construct(RepoResourceInterface $res, string $iiifRequest,
                                object $config, ?LoggerInterface $log = null) {
        $this->res           = $res;
        $this->request       = new IiifImageRequest($iiifRequest);
        $this->config        = $config;
        $this->serviceConfig = new ServiceConfig(
            $config->iiifImage->maxWidth,
            $config->iiifImage->maxHeight,
            $config->iiifImage->backendConfig ?? []
        );
        $this->log           = $log;
    }

    public function getResponse(): ResponseCacheItem {
        $meta         = $this->res->getGraph();
        $hashProp     = DF::namedNode($this->config->schema->hash);
        $hashPrevProp = DF::namedNode($this->config->schema->previousHash);
        $hashPrevTmpl = new PT($hashPrevProp);
        $hashPrev     = $meta->getObject($hashPrevTmpl);
        $hashCur      = $meta->getObject(new PT($hashProp));
        $force        = $hashPrev === null || !$hashCur->equals($hashPrev);

        $imageStub = ImageStub::fromDimensions($this->getWidth(), $this->getHeight(), $this->serviceConfig);
        $canonical = $this->request->getCanonical($imageStub, $this->serviceConfig);

        if ($this->request->info) {
            $ret = $this->getInfo($force);
        } else {
            $ret = $this->getImage($canonical, $force);
        }

        if (!$ret->hit) {
            $meta->delete($hashPrevTmpl);
            $meta->add(DF::quadNoSubject($hashPrevProp, $hashCur));
        }
        return $this->addHeaders($ret, $canonical);
    }

    private function getImage(string $canonical, bool $force): ResponseCacheItem {
        $resUri  = (string) $this->res->getUri();
        $headers = [
            'Content-Type' => $this->request->format->getMime(),
        ];

        $cacheDir  = $this->config->cache->dir . '/' . hash(self::HASH, $resUri);
        $cacheFile = $cacheDir . '/' . hash(self::HASH, $canonical);
        if ($force === false && file_exists($cacheFile)) {
            return new ResponseCacheItem($cacheFile, 200, $headers, true, true);
        }

        $srcMime     = $this->res->getGraph()->getObjectValue(new PT(DF::namedNode($this->config->schema->mime)));
        $localAccess = (array) ($this->config->cache->localAccess ?? []);
        $guzzleOpts  = $this->config->cache->guzzleOpts ?? [];
        $maxDwnldMb  = $this->config->cache->maxDownloadSizeMb ?? self::DEFAULT_MAX_DOWNLOAD_SIZE_MB;

        $this->cache = new FileCache($this->config->cache->dir, $this->log, $localAccess);
        $path        = $this->cache->getRefFilePath($resUri, $srcMime, $guzzleOpts, $maxDwnldMb);
        $this->image = new ImageImagick($path, $this->serviceConfig);
        $this->image->transform($cacheFile, $this->request);

        return new ResponseCacheItem($cacheFile, 200, $headers, false, true);
    }

    private function getInfo(bool $force): ResponseCacheItem {
        $iiifInfoProp = DF::namedNode($this->config->schema->iiifInfo);
        $iiifInfoTmpl = new PT($iiifInfoProp);

        $meta = $this->res->getGraph();
        $body = $meta->getObjectValue($iiifInfoTmpl);
        $hit  = true;
        if ($force || $body === null) {
            $body = $this->getInfoBody();
            $meta->delete($iiifInfoTmpl);
            $meta->add(DF::quadNoSubject($iiifInfoProp, DF::literal($body)));
            $hit  = false;
        }

        $accept      = Accept::fromHeader();
        $bestMatch   = $accept->getBestMatch(['application/ld+json', 'application/json']);
        $contentType = match ($bestMatch->subtype) {
            'json' => 'application/json',
            default => 'application/ld+json;profile="' . self::JSONLD_CONTEXT . '"',
        };
        $headers     = [
            'Content-Type' => $contentType,
        ];

        return new ResponseCacheItem($body, 200, $headers, $hit, false);
    }

    private function getInfoBody(): string {
        $schema          = $this->config->schema;
        $meta            = $this->res->getGraph();
        $metaAll         = $meta->getDataset();
        $resUri          = (string) $this->res->getUri();
        $body            = [
            "@context"         => self::JSONLD_CONTEXT,
            "id"               => $resUri,
            "type"             => "ImageService3",
            "protocol"         => "http://iiif.io/api/image",
            "profile"          => "level2",
            "height"           => $this->getHeight(),
            "width"            => $this->getWidth(),
            "maxHeight"        => $this->serviceConfig->maxHeight,
            "maxWidth"         => $this->serviceConfig->maxWidth,
            "maxArea"          => $this->serviceConfig->maxArea,
            "preferredFormats" => ["webp"],
            //TODO - fetch external URI
            "rights"           => $meta->getObjectValue(new PT($schema->license)),
            "extraQualities"   => ["color", "gray", "bitonal"],
            "extraFormats"     => ["tif", "gif", "pdf", "jp2", "webp"],
            "extraFeatures"    => ["canonicalLinkHeader", "cors", "jsonldMediaType",
                "mirroring", "profileLinkHeader", "regionByPct", "regionByPx",
                "regionSquare",
                "rotationArbitrary", "rotationBy90s", "sizeByConfinedWh", "sizeByH",
                "sizeByPct", "sizeByW", "sizeByH", "sizeUpscaling"],
            "partOf"           => [],
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
            // consider inspecting cache for already cached dimensions
            //"sizes":[
            //  ["width": 100, "height": 100],
            //],
            //
            // if image is tiled with overviews
            //"tiles": [
            //  ["width"=>256, "scaleFactors": [1, 2, 4]]
            //]
        ];
        $parentIdTmpl    = new QT(null, $schema->id);
        $parentLabelTmpl = new QT(null, $schema->label);
        foreach ($meta->listObjects(new PT($this->config->schema->parent)) as $parent) {
            $body['partOf'][] = [
                'id'    => $metaAll->getObjectValue($parentIdTmpl->withSubject($parent)),
                'type'  => 'Dataset',
                'label' => $metaAll->getObjectValue($parentLabelTmpl->withSubject($parent)),
            ];
        }
        return (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function getWidth(): int {
        static $width = null;
        $width        ??= (int) $this->res->getGraph()->getObjectValue(new PT(DF::namedNode($this->config->schema->width)));
        return $width;
    }

    private function getHeight(): int {
        static $height = null;
        $height        ??= (int) $this->res->getGraph()->getObjectValue(new PT(DF::namedNode($this->config->schema->height)));
        return $height;
    }

    private function addHeaders(ResponseCacheItem $response, string $canonical): ResponseCacheItem {
        $canonical = $this->config->iiifImage->baseUrl .
            urlencode($this->res->getUri()) .
            '/' . $canonical;

        $response->headers['Link'] = [
            '<http://iiif.io/api/image/3/level2.json>;rel="profile"',
            $canonical . ';rel="canonical"',
        ];
        return $response;
    }
}
