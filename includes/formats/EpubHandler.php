<?php

namespace MediaWiki\Extension\EbookHandler\formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;
use MediaWiki\Extension\EbookHandler\EbookReader;

class EpubHandler extends EbookHandler {
    /**
	 * @param string $path
	 * @return EbookReader|null
	 */
	protected function createEbookReader(string $path): ?EbookReader {
		
		$ebookReader = null;	

		$ext = pathinfo($path, PATHINFO_EXTENSION);
		if ( $ext === "" ) {
			print("I am here");
			$tmpFile = $path . '.epub';
			var_dump($tmpFile);
			if (copy($path, $tmpFile)) {
				$ebookReader = new EbookReader($tmpFile);
				unlink($tmpFile);
			}
		}
		else {
			$ebookReader = new EbookReader($path);
		}

		return $ebookReader;
	}
}