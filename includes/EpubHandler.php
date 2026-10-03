<?php

namespace MediaWiki\Extension\EpubHandler;

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
 * Inspired by djvuhandler from Tim Starling and PDfHandler by Xarax
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

class EpubHandler extends ImageHandler {
	/**
	 * Keep in sync with epubhandler.messages in extension.json
	 *
	 * @see getWarningConfig
	 */
	private const MESSAGES = [
		'main' => 'epub-file-page-warning',
		'header' => 'epub-file-page-warning-header',
		'info' => 'epub-file-page-warning-info',
		'footer' => 'epub-file-page-warning-footer',
	];

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
	private const STATE_DIMENSION_INFO = 'epubDimensionInfo';

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
		return true;
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
	 * @param int $width
	 * @param int $height
	 * @param string $msg
	 * @return MediaTransformError
	 */
	protected function doThumbError( $width, $height, $msg ) {
		return new MediaTransformError( 'thumbnail_error',
			$width, $height, wfMessage( $msg )->inContentLanguage()->text() );
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
		global $wgEpubHandlerPostProcessor, $wgEpubHandlerDpi, $wgEpubHandlerJpegQuality;

		if ( !$this->normaliseParams( $image, $params ) ) {
			return new TransformParameterError( $params );
		}

		$width = (int)$params['width'];
		$height = (int)$params['height'];
		$page = (int)$params['page'];

		if ( $page > $this->pageCount( $image ) ) {
			return $this->doThumbError( $width, $height, 'epub_page_error' );
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

		// Thumbnail extraction is very inefficient for large files.
		// Provide a way to pool count limit the number of downloaders.
		if ( $image->getSize() >= self::LARGE_FILE ) {
			$work = new PoolCounterWorkViaCallback( 'GetLocalFileCopy', sha1( $image->getName() ),
				[
					'doWork' => static function () use ( $image ) {
						return $image->getLocalRefPath();
					}
				]
			);
			$srcPath = $work->execute();
		} else {
			$srcPath = $image->getLocalRefPath();
		}

		if ( $srcPath === false ) {
			// could not download original
			return $this->doThumbError( $width, $height, 'filemissing' );
		}

		$ebookReader = new EbookReader($srcPath);
		$image->setHandlerState(self::STATE_EBOOK_READER, $ebookReader);

		$tmpFileName = "/tmp/asfadsfsdf.gif";
		$realTmpFileName = $ebookReader->saveCoverImageAs($tmpFileName);

		var_dump($realTmpFileName);
		
		if ( $realTmpFileName == null ) {
			$err = sprintf( 'thumbnail failed on %s: image %s does not have cover "',
				wfHostname(), $image->getName() );
			wfDebugLog( 'thumbnail', $err);
			return new MediaTransformError( 'thumbnail_error', $width, $height, $err );
		}

		$cmd = wfEscapeShellArg(
			$wgEpubHandlerPostProcessor,
			"-depth",
			"8",
			"-quality",
			$wgEpubHandlerJpegQuality,
			"-resize",
			'"'. (string)$width . 'x"',
			$realTmpFileName,
			$dstPath
		);
		$cmd .= ")";

		wfDebug( __METHOD__ . ": $cmd\n" );
		$retval = '';
		$err = wfShellExecWithStderr( $cmd, $retval );

		unlink($realTmpFileName);

		$removed = $this->removeBadFile( $dstPath, $retval );

		if ( $retval != 0 || $removed ) {
			print("Bad thumb file");
			wfDebugLog( 'thumbnail',
				sprintf( 'thumbnail failed on %s: error %d "%s" from "%s"',
				wfHostname(), $retval, trim( $err ), $cmd ) );
			return new MediaTransformError( 'thumbnail_error', $width, $height, $err );
		}

		sleep(5);

		return new ThumbnailImage( $image, $dstUrl, $dstPath, [
			'width' => $width,
			'height' => $height,
			'page' => $page,
		] );
	}

	/**
	 * @param \MediaHandlerState $state
	 * @param string $path
	 * @return EbookReader
	 */
	private function getEbookReader( $state, $path ) {
		$ebookReader = $state->getHandlerState( self::STATE_EBOOK_READER );
		if ( !$ebookReader ) {
			$ebookReader = new EbookReader($path);
			$state->setHandlerState( self::STATE_EBOOK_READER, $ebookReader );
		}
		return $ebookReader;
	}

	/**
	 * @param \MediaHandlerState $state
	 * @param string $path
	 * @return array|bool
	 */
	public function getSizeAndMetadata( $state, $path ) {
		$ebookReader = $this->getEbookReader( $state, $path );
		$metadata = $ebookReader->GetMetadata();

		$meta = new BitmapMetadataHandler();
		$meta->addMetadata( $metadata, 'native' );
		$data = $meta->getMetadataArray();
		//$data['mergedMetadata'] = $meta->getMetadataArray();

		$tmpFilePath = "/tmp/asfasfasf.png";
		$coverPath = $ebookReader->saveCoverImageAs($tmpFilePath);
		if ($coverPath != null) {
			unlink($coverPath);
		}
		$size = $ebookReader->getCoverSize();
		$sizes = EpubHandler::getPageSize( $size );
		if ( $sizes ) {
			return $sizes + [ 'metadata' => $data ];
		}

		return [ 'metadata' => $metadata ];
	}

	private static function getPageSize( $size ) {
		global $wgEpubHandlerDpi;

			$width  = intval($size[0] / 72 * $wgEpubHandlerDpi );
			$height = intval($size[1] / 72 * $wgEpubHandlerDpi );
			return [
				'width' => $width,
				'height' => $height
			];
	}

	/**
	 * @param string $ext
	 * @param string $mime
	 * @param null $params
	 * @return array
	 */
	public function getThumbType( $ext, $mime, $params = null ) {
		global $wgEpubOutputExtension;
		static $mime;

		if ( !isset( $mime ) ) {
			$magic = MediaWikiServices::getInstance()->getMimeAnalyzer();
			$mime = $magic->guessTypesForExtension( $wgEpubOutputExtension );
		}
		return [ $wgEpubOutputExtension, $mime ];
	}

	/**
	 * @param File $file
	 * @return bool|int
	 */
	public function isFileMetadataValid( $file ) {
		$data = $file->getMetadataItems( [ 'mergedMetadata', 'pages' ] );
		// if ( !isset( $data['pages'] ) ) {
		// 	return self::METADATA_BAD;
		// }

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
		return $this->formatMetadataHelper( $mergedMetadata, $context );
	}

	/** @inheritDoc */
	protected function formatTag( string $key, $vals, $context = false ) {
		switch ( $key ) {
			case 'pdf-Producer':
			case 'pdf-Version':
				return htmlspecialchars( $vals );
			case 'pdf-PageSize':
				foreach ( $vals as &$val ) {
					$val = htmlspecialchars( $val );
				}
				return $vals;
			case 'pdf-Encrypted':
				// @todo: The value isn't i18n-ised; should be done here.
				// For reference, if encrypted this field's value looks like:
				// "yes (print:yes copy:no change:no addNotes:no)"
				return htmlspecialchars( $vals );
			default:
				break;
		}
		// Use default formatting
		return false;
	}

	/**
	 * @param File $image
	 * @return bool|int
	 */
	public function pageCount( File $image ) {
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
	 * @param File $file
	 * @return bool|mixed
	 */
	protected function getDimensionInfo( File $file ) {
		$info = $file->getHandlerState( self::STATE_DIMENSION_INFO );
		if ( !$info ) {
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$info = $cache->getWithSetCallback(
				$cache->makeKey( 'file-epub-dimensions', $file->getSha1() ),
				$cache::TTL_MONTH,
				static function () use ( $file ) {

					$ebookReader = $file->getHandlerState(self::STATE_EBOOK_READER);

					$dimsByPage = [];
					$count = 1;
					if ( $ebookReader ) {
						for ( $i = 1; $i <= $count; $i++ ) {
							$dimsByPage[$i] = $ebookReader->getPageSize();
						}
					}
					return [ 'pageCount' => 1, 'dimensionsByPage' => $dimsByPage ];
				}
			);
		}
		$file->setHandlerState( self::STATE_DIMENSION_INFO, $info );
		return $info;
	}

	/**
	 * @param File $image
	 * @param int $page
	 * @return bool
	 */
	public function getPageText( File $image, $page ) {
		return false;
	}

	/**
	 * Adds a warning about PDFs being potentially dangerous to the file
	 * page. Multiple messages with this base will be used.
	 * @param File $file
	 * @return array
	 */
	public function getWarningConfig( $file ) {
		return [
			'messages' => self::MESSAGES,
			'link' => '//www.mediawiki.org/wiki/Special:MyLanguage/Help:Security/PDF_files',
			'module' => 'epubhandler.messages',
		];
	}

	public function useSplitMetadata() {
		return true;
	}
}
