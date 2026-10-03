<?php

require_once("../vendor/autoload.php");
require_once( "../includes/EbookReader.php");

use MediaWiki\Extension\EpubHandler\EbookReader;
use MediaWiki\ResourceLoader\FilePath;

function sharptrim(string $str, string $patt) {
		$i = strpos($str, $patt);
		if ($i === 0) {
			$str = substr($str, strlen($patt), strlen($str) - strlen($patt)); 
		}
		$i = strpos($str, $patt);
		if ($i === strlen($str) - strlen($patt)) {
			$str = substr($str, 0, strlen($str) - strlen($patt)); 
		}
		return $str;
	}

$reader = new EbookReader(__DIR__ . '/alice-lewis-carroll.epub');

var_dump($reader->getCoverSize());

$data = $reader->getMetadata();

var_dump($data);

$filePath = $reader->saveCoverImageAs(__DIR__ . '/alice-lewis-carroll');
if ($filePath != null) {
    print("Cover image save to " . $filePath);
}

$testString = "exif-name-exif-";
$id = sharptrim($testString, "exif-");
$id = sharptrim($id, "exif_");
var_dump($id);