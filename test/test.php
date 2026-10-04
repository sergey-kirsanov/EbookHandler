<?php

require_once("../vendor/autoload.php");
require_once( "../includes/EbookReader.php");

use MediaWiki\Extension\EbookHandler\EbookReader;

$reader = new EbookReader(__DIR__ . '/alice-lewis-carroll.mobi');

var_dump($reader->getCoverSize());

$data = $reader->getMetadata();

var_dump($data);

$size = $reader->getCoverSize();
var_dump($size);

$filePath = $reader->saveCoverImageAs(__DIR__ . '/alice-lewis-carroll');
if ($filePath != null) {
    print("Cover image save to " . $filePath);
}