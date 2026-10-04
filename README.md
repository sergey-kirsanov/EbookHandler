E-books media handler extension for [MediaWiki](https://www.mediawiki.org/wiki/MediaWiki)

Currently this extension allows:
* See thumbnail of cover image at regular wiki page when using [[File]] with |thumb| parameter.
* See full cover image and metadata properties retrieved from e-book file at File page of MediaWiki.

For the time being only EPUB, MOBI and FB2 are implemented. 

In order to get it working you must first make sure that MediaWiki is configured properly. You have to setup it so that every uploaded e-book will take correct Mime type.
To do so you may modify your LocalSettings.php as follows:

<code>
  $wgFileExtensions = array_merge( $wgFileExtensions,
    array( 'doc', 'docx', 'xls', 'mpp', 'pdf', 'ppt', 'xlsx', 'jpg', 
        'tiff', 'odt', 'odg', 'ods', 'odp', 'epub', 'mobi', 'fb2', 'zip'
    )
);

</code>
