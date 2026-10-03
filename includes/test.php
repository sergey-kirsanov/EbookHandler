<?php

require_once("../vendor/autoload.php");
require_once(__DIR__ . "/EbookReader.php");

use MediaWiki\Extension\EpubHandler\EbookReader;

$reader = new EbookReader('/home/sergey/Documents/Projects/The.phoenix.project.epub');

$filePath = $reader->saveCoverImageAs('/home/sergey/Documents/Projects/The.phoenix.project.gif');

var_dump($reader->getCoverSize());

$data = $reader->getMetadata();

var_dump($data);