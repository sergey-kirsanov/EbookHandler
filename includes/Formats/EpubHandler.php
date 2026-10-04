<?php

namespace MediaWiki\Extension\EbookHandler\Formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;
use MediaWiki\Extension\EbookHandler\EbookReader;

class EpubHandler extends EbookHandler {
    /**
	 * @param string $path
	 * @return EbookReader|null
	 */
	protected function createEbookReader(string $path): EbookReader {
		
		return EbookHandler::createEbookReaderExt($path, "epub");
	}
}