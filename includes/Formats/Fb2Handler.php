<?php

namespace MediaWiki\Extension\EbookHandler\Formats;

use MediaWiki\Extension\EbookHandler\EbookHandler;
use Override;

class Fb2Handler extends EbookHandler {
    
	#[Override]
	protected function getActualExt(): string {

		return "fb2";
	}

}