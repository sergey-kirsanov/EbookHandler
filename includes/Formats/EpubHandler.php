<?php

namespace MediaWiki\Extension\EbookHandler\Formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;
use Override;

class EpubHandler extends EbookHandler {
    
	#[Override]
	protected function getActualExt(): string {

		return "epub";
	}
	
}