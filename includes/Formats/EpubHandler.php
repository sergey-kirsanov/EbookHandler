<?php

namespace MediaWiki\Extension\EbookHandler\Formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;

class EpubHandler extends EbookHandler {
    
	protected function getHandlerExtension(): string {

		return "epub";
	}

}