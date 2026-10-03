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
    }

    public function saveCoverImageAs(string $coverFilePath) {
        
        if ($this->mBook == null) {
            return null;
        }

        if ($coverFilePath == null) {
            return null;
        }    

        $coverImage = $this->mBook->getCover();
        if ($coverImage == null){
            return null;
        }

        $dir = pathinfo($coverFilePath, PATHINFO_DIRNAME);
        $name = pathinfo($coverFilePath, PATHINFO_FILENAME);

        $coverImagePath = $coverImage->getPath();
        if ($coverImagePath == null) {
            return null;
        }

        $extension = pathinfo($coverImagePath, PATHINFO_EXTENSION);

        $dstFilePath = $dir . "/" . $name . "." . $extension; 

        if ($coverImage->saveTo($dstFilePath)){
             $size = getimagesize($dstFilePath);
             $this->mCoverSize = [$size[0], $size[1]];
             return $dstFilePath;
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

        $data = array();

        $data['Title'] = $this->mBook->getTitle() ?? "";
        $data['Description'] = $this->mBook->getDescription() ?? "";
        $data['Author'] = $this->mBook->getAuthorMain() != null ? $this->mBook->getAuthorMain()->getName(): "";
        $data['CreatedAt'] = $this->mBook->getCreatedAt() != null ? $this->mBook->getCreatedAt()->format("d.m.Y") : "";
        $data['Language'] = $this->mBook->getLanguage() ?? "";
        $data['Publisher'] = $this->mBook->getPublisher() ?? "";
        $data['PublishDate'] = $this->mBook->getPublishDate() != null ? $this->mBook->getPublishDate()->format("d.m.Y") : "";
        foreach ($this->mBook->getIdentifiers() as $id) {
            $data['ID ' . $id->getScheme()] = $id->getValue();
        }

        return $data;
    }
}