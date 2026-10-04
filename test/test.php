<?php

require_once("../vendor/autoload.php");
require_once( "../includes/EbookReader.php");

use MediaWiki\Extension\EbookHandler\EbookReader;

$reader = new EbookReader(__DIR__ . '/alice-lewis-carroll.mobi');

var_dump($reader->getCoverSize());

$data = $reader->getMetadata();

var_dump($data);

$filePath = $reader->saveCoverImageAs(__DIR__ . '/alice-lewis-carroll');
if ($filePath != null) {
    print("Cover image save to " . $filePath);
}