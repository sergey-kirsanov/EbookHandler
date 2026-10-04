<?php

namespace MediaWiki\Extension\EbookHandler\formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;
use MediaWiki\Extension\EbookHandler\EbookReader;

class EpubHandler extends EbookHandler {
    /**
	 * @param \MediaHandlerState $state
	 * @param string $path
	 * @return EbookReader|null
	 */
	protected function createEbookReader($state, string $path): ?EbookReader {
		
		$ebookReader = null;	

		$ext = pathinfo($path, PATHINFO_EXTENSION);
		if ( $ext == "" ) {
			$tmpFile = $path . '.epub';
			copy($path, $tmpFile);
			$ebookReader = new EbookReader($tmpFile);
			unlink($tmpFile);
		}
		else {
			$ebookReader = new EbookReader($path);
		}

		$state->setHandlerState(self::STATE_EBOOK_READER, $ebookReader);

		return $ebookReader;
	}
}