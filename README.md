# ARCHE-IIIF-image

[![Build status](https://github.com/acdh-oeaw/arche-iiifimage/actions/workflows/deploy.yaml/badge.svg)](https://github.com/acdh-oeaw/arche-iiifimage/actions/workflows/deploy.yaml)
[![Coverage Status](https://coveralls.io/repos/github/acdh-oeaw/arche-iiifimage/badge.svg?branch=master)](https://coveralls.io/github/acdh-oeaw/arche-iiifimage?branch=master)

A dissemination service for the [ARCHE Suite](https://acdh-oeaw.github.io/arche-docs/) providing [IIIF Image API 3.0](https://iiif.io/api/image/3.0/) implementation.

## REST API

`{deploymentUrl}/{identifier}/{other IIIF Image API Parameters}`

* {identifier} identifies the image to be processes and can be:
  * URL-encoded identifier of any ARCHE resource, e.g. its PID handle,
    its URI in the https://id.acdh.oeaw.ac.at domain or its ARCHE URL
  * An internal numeric identifier of an image (works only for services default ARCHE instance).
* [description of other IIIF Image API Parameters](https://iiif.io/api/image/3.0/#2-uri-syntax)
  (`{region}`.`{size}`, `{rotation}`, `{quality}` and `{format}`)

## Deployment

See the .github/workflows/deploy.yaml
