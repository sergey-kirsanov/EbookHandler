<?php

namespace MediaWiki\Extension\EbookHandler\Formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;

class MobiHandler extends EbookHandler {
    
	protected function getHandlerExtension(): string {

		return "mobi";
	}

}