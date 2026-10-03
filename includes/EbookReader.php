<?php

namespace MediaWiki\Extension\EpubHandler;

use Kiwilan\Ebook\Ebook;

class EbookReader {
    
    private ?Ebook $mBook = null;

    private array $mCoverSize = [0, 0];
    
    /**
	 * @param string $epubFilePath
	 */
	public function __construct( $epubFilePath ) {
		$this->read($epubFilePath);
	}

    private function read(string $epubFilePath) {
        
        if (!Ebook::isValid($epubFilePath)) {
            return;
        }    
        
        $this->mBook = Ebook::read($epubFilePath);

        if ($this->mBook->hasCover()) {
            $imageSize = getimagesizefromstring($this->mBook->getCover()->getContents());
            $this->mCoverSize = [$imageSize[0], $imageSize[1]];
        }
    }

    public function saveCoverImageAs(string $coverFilePath) {
        
        if ($coverFilePath == null) {
            return null;
        }    

        $coverImage = $this->mBook->getCover();
        if ($coverImage == null){
            return null;
        }

        $coverImagePath = $coverImage->getPath();
        if ($coverImagePath == null) {
            return null;
        }

        if ($coverImage->saveTo($coverFilePath)){
             return $coverFilePath;
        }

        return null;
    }

    public function getCoverSize(){

        return $this->mCoverSize;
    }

    public function getPageCount() {

        return $this->mBook->getPagesCount();
    }

    public function getMetadata() {
        
        if ($this->mBook == null) {
            return null;
        }

        $data = [];

        $data['EbookHandler-Title'] = $this->mBook->getTitle();
        $data['EbookHandler-Description'] = $this->mBook->getDescription();
        $data['EbookHandler-Author'] = $this->mBook->getAuthorMain() != null ? $this->mBook->getAuthorMain()->getName() : null;
        $data['EbookHandler-CreatedAt'] = $this->mBook->getCreatedAt() != null ? $this->mBook->getCreatedAt()->format("d.m.Y H:i:s") : null;
        $data['EbookHandler-Language'] = $this->mBook->getLanguage();
        $data['EbookHandler-Publisher'] = $this->mBook->getPublisher();
        $data['EbookHandler-PublishDate'] = $this->mBook->getPublishDate() != null ? $this->mBook->getPublishDate()->format("d.m.Y") : null;
        foreach ($this->mBook->getIdentifiers() as $id) {
            $data['EbookHandler-Identifier'] = $id->getScheme() . " " . $id->getValue();
        }
        $data['EbookHandler-Copyright'] = $this->mBook->getCopyright(100);

        return $data;
    }
}