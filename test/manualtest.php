<?php

require_once("../vendor/autoload.php");
require_once( "../includes/EbookReader.php");

use MediaWiki\Extension\EbookHandler\EbookReader;

$reader = new EbookReader(__DIR__ . '/alice-lewis-carroll.fb2');

$data = $reader->getMetadata();

var_dump($data);

$size = $reader->getCoverSize();
var_dump($size);

$res = $reader->saveCoverImageAs(__DIR__ . '/alice-lewis-carroll');
if ($res) {
    print("Cover image saved");
}