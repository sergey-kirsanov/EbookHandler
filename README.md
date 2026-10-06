# MediaWiki EbookHandler extension

## About extension

This is [MediaWiki](https://www.mediawiki.org/wiki/MediaWiki) Media handler extension to get a preview: read metadata and extract covers from eBooks.

-   eBooks: `.epub`, `.fb2`, `.mobi`

This extension is based on [kiwilan\php-ebook](https://github.com/kiwilan/php-ebook), so read first its page for requirements and other notes including license.

## Requirements

-   **PHP version** `>=8.1` and so the version of MediaWiki which runs on this php version (1.43.1 in my case)
-   **PHP extensions**:
    -   [`zip`](https://www.php.net/manual/en/book.zip.php)
    -   [`xml`](https://www.php.net/manual/en/book.xml.php)
-   **Binaries**
    - /usr/bin/convert (ImageMagick) for making thumbnails of covers

## Features

-   Support multiple formats
-   🔎 Read metadata from eBooks
-   🖼️ Extract covers from eBooks and makes thumbnails for them

### Roadmap

-   Other formats?
-   More metadata?

## Installation

You can install the extension by cloning code into extensions folder of your MediaWiki installation or by downloading zip and extracting it into the same folder:

```bash
cd extensions
git clone git@github.com:sergey-kirsanov/EbookHandler.git
```

After that you must install `kiwilan/php-ebook` into your MediaWiki installation using composer require (run following command from the root folder of your MediaWiki):
```bash
composer require kiwilan/php-ebook
```

## Usage

First of all you must be sure that epub, fb2 and mobi files correctly uploaded to your MediaWiki.
On the File page of MediaWiki there should be proper Mime type shown for these files.

| **Mime type**                  | **Extension**              |
| ------------------------------ | -------------------------- |
| application/epub+zip           | *.epub                     |
| application/x-fictionbook+xml  | *.fb2                      |
| application/x-mobipocket-ebook | *.mobi                     |

If this is not the case you must follow [this](https://www.mediawiki.org/wiki/Manual:MIME_type_detection) guidance.

> [!NOTE]
>
> On my installation (MediaWiki 1.43.1) just following had to be added to LocalSetting.php (not sure if all options are safe to be used in production):
>
> ```php
> $wgMimeDetectorCommand = "file -bi";
>
> $wgFileExtensions = array_merge( $wgFileExtensions,
>     array( 'epub', 'mobi', 'fb2' )
> );
>
> $wgHooks['MimeMagicInit'][] = static function ( $mime ) {
>    $mime->addExtraTypes( 'application/epub+zip epub' );
>    $mime->addExtraTypes( 'application/x-fictionbook+xml fb2' );
>    $mime->addExtraTypes( 'application/x-mobipocket-ebook mobi' );
> };
>
> $wgHooks['MimeMagicImproveFromExtension'][] = static function ( $mimeAnalyzer, $ext, &$mime ) {
>
>    if ( in_array( $ext, ['epub'] ) ) {
>        $mime = 'application/epub+zip';
>    }
>
>    if ( in_array( $ext, ['fb2'] ) ) {
>        $mime = 'application/x-fictionbook+xml';
>    }
>
>    if ( in_array( $ext, ['mobi'] ) ) {
>        $mime = 'application/x-mobipocket-ebook';
>    }
> };
> ```

If everything is OK with Mime types then you should just add this to your LocalSettings.php:
```php
wfLoadExtension( 'EbookHandler' );
```

Now you could navigate to File page with some eBook and you must see its cover (if present in file) and metadata at bottom of this page.
[[File|xxx.epub]] and [[File|xxx.epub|thumb]] links should also work and you will see cover picture on your MediaWiki page.   

<img width="353" height="546" alt="fb2-thumb" src="https://github.com/user-attachments/assets/7c6f878e-49b0-45a9-97f6-d750eb921294" />

### Metadata

For the time being there is only common metadata extracted like Title, Author etc.

<img width="435" height="555" alt="common-metadata" src="https://github.com/user-attachments/assets/d38f9e6d-ad5d-4c1c-92d7-e7484fb65222" />

### Cover

Cover can be extracted from eBook but in some cases it is not available in the book itself or it could be some issue.

## Testing

For the time being only manual testing is supported (it can be used to check if EbookReader class can extract cover and metadata from particular file).

Follow these steps to do it:

1. Prepare environment

Go to root folder of the EbookHandler extension and run:

```bash
composer update
```

Now, you should have all dependencies installed.

2. Edit ./test/manualtest.php file accordingly.

3. Run php manualtest.php command and see the results.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently and a list of known issues.

## Credits

-   Inspired by djvuhandler from Tim Starling and PDfHandler by Martin Seidel (Xarax)
-   [Ewilan Rivière](https://github.com/ewilan-riviere) author of `kiwilan/php-ebook` package
-   [All Contributors](../../contributors)
