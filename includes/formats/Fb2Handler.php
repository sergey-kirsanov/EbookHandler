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
		
		return EbookHandler::createEbookReaderForFileWithExt($path, "fb2");
	}
}