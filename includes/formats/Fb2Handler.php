<?php

use MediaWiki\Extension\EbookHandler\EbookHandler;
use MediaWiki\Extension\EbookHandler\EbookReader;

class Fb2Handler extends EbookHandler {
    /**
	 * @param \MediaHandlerState $state
	 * @param string $path
	 * @return EbookReader|null
	 */
	protected function createEbookReader($state, string $path): ?EbookReader {
		
		$ebookReader = null;	

		$ebookReader = new EbookReader($path);
		$state->setHandlerState(self::STATE_EBOOK_READER, $ebookReader);

		return $ebookReader;
	}
}