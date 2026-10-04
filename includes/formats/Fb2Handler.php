<?php

namespace MediaWiki\Extension\EbookHandler\formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;
use MediaWiki\Extension\EbookHandler\EbookReader;

class Fb2Handler extends EbookHandler {
    /**
	 * @param string $path
	 * @return EbookReader|null
	 */
	protected function createEbookReader(string $path): ?EbookReader {
		
		$ebookReader = null;	

		$ebookReader = new EbookReader($path);

		return $ebookReader;
	}
}