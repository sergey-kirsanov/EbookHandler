<?php

namespace MediaWiki\Extension\EbookHandler\Formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;
use Override;

class MobiHandler extends EbookHandler {
    
	#[Override]
	protected function getActualExt(): string {

		return "mobi";
	}

}