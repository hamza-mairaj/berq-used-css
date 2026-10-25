<?php

namespace BerqUsedCSS\Deps;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/vendor/autoload.php';
$readmeText = (new \BerqUsedCSS\Deps\voku\PhpReadmeHelper\GenerateApi())->generate(__DIR__ . '/../src/', __DIR__ . '/docs/api.md', [\BerqUsedCSS\Deps\voku\helper\DomParserInterface::class, \BerqUsedCSS\Deps\voku\helper\SimpleHtmlDomNodeInterface::class, \BerqUsedCSS\Deps\voku\helper\SimpleHtmlDomInterface::class]);
\file_put_contents(__DIR__ . '/../README_API.md', $readmeText);
