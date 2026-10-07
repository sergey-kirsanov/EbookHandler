<?php

namespace MediaWiki\Extension\EbookHandler;

use Kiwilan\Ebook\Ebook;
use Exception;

class EbookReader {
    
    private string $mBookFilePath;
    private bool $mTempFile = false;
    private ?Ebook $mBook = null;

    private array $mCoverSize = [0, 0];
    
    /**
	 * @param string $ebookFilePath
	 */
	public function __construct( string $ebookFilePath, bool $tempFile = false ) {
        $this->mBookFilePath = $ebookFilePath;
        $this->mTempFile = $tempFile;
        $this->read($ebookFilePath);
	}

    public function __destruct()
    {
        if ($this->mTempFile) {
            unlink($this->mBookFilePath);
        }
    }

    private function read(string $ebookFilePath) {
        if (!Ebook::isValid($ebookFilePath)) {
            return;
        }    

		$numTries = 5;
		while($this->mBook  == null && $numTries > 0) {
			try {
				$this->mBook = Ebook::read($ebookFilePath);
			}
			catch(Exception) { // Do not know why but simple xml could raise 'Document is empty' exception first time
				$numTries--;
			}
		}

        if ($this->mBook == null) {
            // Do it again and this time it will fail with a stack trace
            $this->mBook = Ebook::read($ebookFilePath);
        }

        if ($this->mBook->hasCover()) {
            $imageSize = getimagesizefromstring($this->mBook->getCover()->getContents());
            $this->mCoverSize = [$imageSize[0], $imageSize[1]];
        }
    }

    public function saveCoverImageAs(string $coverFilePath): bool {
        if ($coverFilePath == null) {
            return false;
        }    

        $coverImage = $this->mBook->getCover();
        if ($coverImage == null){
            return false;
        }

        if ($coverImage->saveTo($coverFilePath)){
             return true;
        }

        return false;
    }

    public function getBookFilePath(): string {
        return $this->mBookFilePath;
    }

    public function getCoverSize(): array {
        return $this->mCoverSize;
    }

    public function getPageCount(): ?int {
        return $this->mBook->getPagesCount();
    }

    public function getMetadata(): array {
        $data = [];

        $data['EbookHandler-Title'] = $this->mBook->getTitle();
        $data['EbookHandler-Description'] = $this->mBook->getDescription();
        $data['EbookHandler-Author'] = $this->mBook->getAuthorMain() != null ? $this->mBook->getAuthorMain()->getName() : null;
        $data['EbookHandler-CreatedAt'] = $this->mBook->getCreatedAt() != null ? $this->mBook->getCreatedAt()->format("d.m.Y H:i:s") : null;
        $data['EbookHandler-Language'] = $this->mBook->getLanguage();
        $data['EbookHandler-Publisher'] = $this->mBook->getPublisher();
        $data['EbookHandler-PublishDate'] = $this->mBook->getPublishDate() != null ? $this->mBook->getPublishDate()->format("d.m.Y") : null;
        $data['EbookHandler-Series'] = $this->mBook->getSeries();
        $data['EbookHandler-Volume'] = $this->mBook->getVolume();
        foreach ($this->mBook->getIdentifiers() as $id) {
            $data['EbookHandler-Identifier'] = $id->getScheme() . " " . $id->getValue();
        }
        $data['EbookHandler-Copyright'] = $this->mBook->getCopyright(100);
        
        $tags = "";
        foreach ($this->mBook->getTags() as $key => $val) {
            $tags .= ' ' . trim($val);
        }
        $data['EbookHandler-Tags'] = trim($tags);

        foreach ($this->mBook->getExtras() as $key => $val) {
            $data['EbookHandler-Extra'] = $key . ' ' . trim($val);
        }

        return $data;
    }
}