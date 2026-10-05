<?php

namespace MediaWiki\Extension\EbookHandler\Formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;

class Fb2Handler extends EbookHandler {
    
	protected function getHandlerExtension(): string {

		return "fb2";
	}

}