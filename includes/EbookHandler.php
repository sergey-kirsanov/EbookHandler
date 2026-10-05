<?php

namespace MediaWiki\Extension\EbookHandler;

use File;
use ImageHandler;
use BitmapMetadataHandler;
use MediaTransformError;
use MediaTransformOutput;
use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\PoolCounter\PoolCounterWorkViaCallback;
use ThumbnailImage;
use TransformParameterError;

/**
 * Copyright © 2026 Sergey Kirsanov <sergey@kirsanov.info>
 *
 * Inspired by djvuhandler from Tim Starling and PDfHandler by Martin Seidel (Xarax)
 * Modified and written by Sergey Kirsanov
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

abstract class EbookHandler extends ImageHandler {

	/**
	 * 10MB is considered a large file
	 */
	private const LARGE_FILE = 1e7;

	/**
	 * Key for getHandlerState for value of type EbookReader
	 */
	private const STATE_EBOOK_READER = 'ebookReader';

	/**
	 * Key for getHandlerState for dimension info
	 */
	private const STATE_DIMENSION_INFO = 'ebookDimensionInfo';

	/**
	 * @param File $file
	 * @return bool
	 */
	public function mustRender( $file ) {
		return true;
	}

	/**
	 * @param File $file
	 * @return bool
	 */
	public function isMultiPage( $file ) {
		// It effectively disables pages preview
		// which is not implemented yet
		return false;
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return bool
	 */
	public function validateParam( $name, $value ) {
		if ( $name === 'page' && trim( $value ) !== (string)intval( $value ) ) {
			// Extra junk on the end of page, probably actually a caption
			// e.g. [[File:Foo.pdf|thumb|Page 3 of the document shows foo]]
			return false;
		}
		if ( in_array( $name, [ 'width', 'height', 'page' ] ) ) {
			return ( $value > 0 );
		}
		return false;
	}

	/**
	 * @param array $params
	 * @return bool|string
	 */
	public function makeParamString( $params ) {
		$page = $params['page'] ?? 1;
		if ( !isset( $params['width'] ) ) {
			return false;
		}
		return "page{$page}-{$params['width']}px";
	}

	/**
	 * @param string $str
	 * @return array|bool
	 */
	public function parseParamString( $str ) {
		$m = [];

		if ( preg_match( '/^page(\d+)-(\d+)px$/', $str, $m ) ) {
			return [ 'width' => $m[2], 'page' => $m[1] ];
		}

		return false;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function getScriptParams( $params ) {
		return [
			'width' => $params['width'],
			'page' => $params['page'],
		];
	}

	/**
	 * @return array
	 */
	public function getParamMap() {
		return [
			'img_width' => 'width',
			'img_page' => 'page',
		];
	}

	/**
	 * @param File $image
	 * @param string $dstPath
	 * @param string $dstUrl
	 * @param array $params
	 * @param int $flags
	 * @return MediaTransformError|MediaTransformOutput|ThumbnailImage|TransformParameterError
	 */
	public function doTransform( $image, $dstPath, $dstUrl, $params, $flags = 0 ) {
		global $wgEbookHandlerPostProcessor, $wgEbookHandlerOutputExtension, $wgEbookHandlerJpegQuality;

		if ( !$this->normaliseParams( $image, $params ) ) {
			return new TransformParameterError( $params );
		}

		$width = (int)$params['width'];
		$height = (int)$params['height'];
		$page = (int)$params['page'];

		if ( $page > $this->pageCount( $image ) ) {
			return $this->doThumbError( $width, $height, 'ebookhandler-page-error' );
		}

		if ( $flags & self::TRANSFORM_LATER ) {
			return new ThumbnailImage( $image, $dstUrl, false, [
				'width' => $width,
				'height' => $height,
				'page' => $page,
			] );
		}

		if ( !wfMkdirParents( dirname( $dstPath ), null, __METHOD__ ) ) {
			return $this->doThumbError( $width, $height, 'thumbnail_dest_directory' );
		}

		$ebookReader = $this->getEbookReaderForFile($image);

		if ( $ebookReader == null ) {
			// could not download original
			return $this->doThumbError( $width, $height, 'filemissing' );
		}

		$res = $ebookReader->saveCoverImageAs($dstPath);

		if ( !$res ) {
			$err = sprintf( 'thumbnail failed on %s: image %s does not have cover "',
				wfHostname(), $image->getName() );
			wfDebugLog( 'thumbnail', $err);
			return new MediaTransformError( 'ebookhandler-no-cover', 100, 50 );
		}

		$cmd = wfEscapeShellArg(
			$wgEbookHandlerPostProcessor,
			"-depth",
			"8",
			"-quality",
			$wgEbookHandlerJpegQuality,
			"-format",
			$wgEbookHandlerOutputExtension,
			"-resize",
			(string)$width,
			$dstPath,
			$dstPath
		);
		
		wfDebug( __METHOD__ . ": $cmd\n" );
		$retval = '';

		$err = wfShellExecWithStderr( $cmd, $retval );

		$removed = $this->removeBadFile( $dstPath, $retval );

		if ( $retval != 0 || $removed ) {
			wfDebugLog( 'thumbnail',
				sprintf( 'thumbnail failed on %s: error %d "%s" from "%s"',
				wfHostname(), $retval, trim( $err ), $cmd ) );
			return new MediaTransformError( 'thumbnail_error', $width, $height, $err );
		}

		return new ThumbnailImage( $image, $dstUrl, $dstPath, [
			'width' => $width,
			'height' => $height,
			'page' => $page,
		] );
	}

	/**
	 * @param \MediaHandlerState $state
	 * @param string $path
	 * @return array|bool
	 */
	public function getSizeAndMetadata( $state, $path ) {
		
		$ebookReader = $this->getEbookReader($state, $path);

		if ($ebookReader == null) {
			return false;
		}

		$metadata = $ebookReader->GetMetadata();

		$meta = new BitmapMetadataHandler();
		$meta->addMetadata( $metadata, 'native' );
		$data = [];
		$data['mergedMetadata'] = $meta->getMetadataArray();

		$size = $ebookReader->getCoverSize();
		$sizes = self::getPageSize( $size );
		if ( $sizes ) {
			return $sizes + [ 'metadata' => $data ];
		}

		return [ 'metadata' => $data ];
	}

	/**
	 * @param string $ext
	 * @param string $mime
	 * @param null $params
	 * @return array
	 */
	public function getThumbType( $ext, $mime, $params = null ) {
		global $wgEbookHandlerOutputExtension;
		static $mime;

		if ( !isset( $mime ) ) {
			$magic = MediaWikiServices::getInstance()->getMimeAnalyzer();
			$mime = $magic->guessTypesForExtension( $wgEbookHandlerOutputExtension );
		}
		return [ $wgEbookHandlerOutputExtension, $mime ];
	}

	/**
	 * @param File $file
	 * @return bool|int
	 */
	public function isFileMetadataValid( $file ) {
		$data = $file->getMetadataItems( [ 'mergedMetadata' ] );

		if ( !isset( $data['mergedMetadata'] ) ) {
			return self::METADATA_COMPATIBLE;
		}

		return self::METADATA_GOOD;
	}

	/**
	 * @param File $image
	 * @param bool|IContextSource $context Context to use (optional)
	 * @return bool|array
	 */
	public function formatMetadata( $image, $context = false ) {
		$mergedMetadata = $image->getMetadataItem( 'mergedMetadata' );

		if ( !is_array( $mergedMetadata ) || !count( $mergedMetadata ) ) {
			return false;
		}

		// Inherited from MediaHandler.
		$formatted = $this->formatMetadataHelper( $mergedMetadata, $context );

		return $formatted;
	}

	/**
	 * @param File $image
	 * @return bool|int
	 */
	public function pageCount( File $image ) {
		// TODO: For the time being (we do not have API to get also pages separately from e-book)
		// so we limit to one page
		return 1;
	}

	/**
	 * @param File $image
	 * @param int $page
	 * @return array|bool
	 */
	public function getPageDimensions( File $image, $page ) {
		// MW starts pages at 1, as they are stored here
		$index = $page;

		$info = $this->getDimensionInfo( $image );
		if ( $info && isset( $info['dimensionsByPage'][$index] ) ) {
			return $info['dimensionsByPage'][$index];
		}

		return false;
	}

	/**
	 * @param File $image
	 * @param int $page
	 * @return string|bool
	 */
	public function getPageText( File $image, $page ) {
		// TODO: Bbecause we do not have the API to read content of particular page yet	
		return false;
	}

	public function getWarningConfig( $file ) {
		return null;
	}

	public function useSplitMetadata() {
		return true;
	}

	/**
	 * @param int $width
	 * @param int $height
	 * @param string $msg
	 * @return MediaTransformError
	 */
	protected function doThumbError( $width, $height, $msg ) {
		return new MediaTransformError( 'thumbnail_error',
			$width, $height, wfMessage( $msg )->text() );
	}

	/** @inheritDoc */
	protected function formatTag( string $key, $vals, $context = false ) {
		switch ( $key ) {
			default:
				break;
		}

		// Use default formatting
		return false;
	}

	/**
	 * @param File $file
	 * @return bool|mixed
	 */
	protected function getDimensionInfo( File $file ) {
		$info = $file->getHandlerState( self::STATE_DIMENSION_INFO );
		if ( !$info ) {
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$ebookReader = $this->getEbookReaderForFile($file);
			
			$info = $cache->getWithSetCallback(
				$cache->makeKey( 'file-ebook-dimensions', $file->getSha1() ),
				$cache::TTL_MONTH,
				static function () use ( $ebookReader ) {

					$dimsByPage = [];
					// TODO: For the time being (we do not have API to get also pages separately from e-book)
					// so we limit to one page
					$count = 1;
					
					for ( $i = 1; $i <= $count; $i++ ) {
							// TODO: For the time being (we do not have API to get also pages separately from e-book)
							// so we use cover size
							$dimsByPage[$i] = $ebookReader != null ? $ebookReader->getCoverSize() : [50,50];
					}

					return [ 'pageCount' => $count, 'dimensionsByPage' => $dimsByPage ];
				}
			);
		}
		$file->setHandlerState( self::STATE_DIMENSION_INFO, $info );
		return $info;
	}

	abstract protected function getHandlerExtension(): string;

	/**
	 * @param \MediaHandlerState $state
	 * @param string $path
	 * @return EbookReader
	 */
	private function getEbookReader(\MediaHandlerState $state, string $path ): EbookReader {
		$ebookReader = $state->getHandlerState( self::STATE_EBOOK_READER );
		if ( $ebookReader == null ) {
			$ebookReader = $this->createEbookReader($path);
			$state->setHandlerState( self::STATE_EBOOK_READER, $ebookReader );
		}
		return $ebookReader;
	}

	/**
	 * @param File $file
	 * @return EbookReader
	 */
	private function getEbookReaderForFile(File $file ): ?EbookReader {
		$ebookReader = $file->getHandlerState( self::STATE_EBOOK_READER );
		if ( $ebookReader == null ) {
			$ebookReader = $this->createEbookReaderForFile($file);
			$file->setHandlerState( self::STATE_EBOOK_READER, $ebookReader );
		}
		return $ebookReader;
	}

	private function createEbookReaderForFile(File $file): ?EbookReader {
		// Provide a way to pool count limit the number of downloaders.
		if ( $file->getSize() >= self::LARGE_FILE ) {
			$work = new PoolCounterWorkViaCallback( 'GetLocalFileCopy', sha1( $file->getName() ),
				[
					'doWork' => static function () use ( $file ) {
						return $file->getLocalRefPath();
					}
				]
			);
			$srcPath = $work->execute();
		} else {
			$srcPath = $file->getLocalRefPath();
		}

		if ($srcPath === false) { //File probably deleted
			return null;
		}

		$ebookReader = $this->createEbookReader($srcPath);

		return $ebookReader;
	}

	/**
	 * @param string $path
	 * @return EbookReader
	 */
	private function createEbookReader(string $path): EbookReader{
		$ebookReader = null;	

		$ext = pathinfo($path, PATHINFO_EXTENSION);
		if ( $ext === "" ) {
			$tmpFile = $path . '.' . $this->getHandlerExtension();
			if (copy($path, $tmpFile)) {
				$ebookReader = new EbookReader($tmpFile, true);
			}
		}
		else {
			$ebookReader = new EbookReader($path);
		}

		return $ebookReader;
	}

	private static function getPageSize( array $size ) {
		global $wgEbookHandlerDpi;

			$width  = intval($size[0] / 72 * $wgEbookHandlerDpi );
			$height = intval($size[1] / 72 * $wgEbookHandlerDpi );
			return [
				'width' => $width,
				'height' => $height
			];
	}
}
