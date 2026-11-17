<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The files of tickets (doc/attachments.md, [AttachmentSettings]): checked (size, extension, content type, the virus
 * scanner of ScanCommand), stored under a random name below StorageDirectory (two levels of hashed folders, never in
 * the public storage, 0640) and recorded in ticketsystem_attachment. ticketsystem/file/<id> serves a file only to a
 * reader of its ticket, a file of an internal note only to staff, always as a download (Content-Disposition:
 * attachment, nosniff).
 */
class ticketsystemFiles
{
    /** extension => the content types its content may have (finfo); an extension not listed accepts its own kind */
    const TYPES = array(
        'pdf' => array( 'application/pdf' ),
        'png' => array( 'image/png' ), 'jpg' => array( 'image/jpeg' ), 'jpeg' => array( 'image/jpeg' ), 'gif' => array( 'image/gif' ),
        'webp' => array( 'image/webp' ),
        'txt' => array( 'text/' ), 'log' => array( 'text/' ), 'csv' => array( 'text/', 'application/csv' ), 'json' => array( 'text/', 'application/json' ),
        'xml' => array( 'text/xml', 'application/xml', 'text/plain' ), 'eml' => array( 'message/rfc822', 'text/' ),
        'zip' => array( 'application/zip' ), 'gz' => array( 'application/gzip', 'application/x-gzip' ),
        'docx' => array( 'application/vnd.openxmlformats-officedocument', 'application/zip' ),
        'xlsx' => array( 'application/vnd.openxmlformats-officedocument', 'application/zip' ),
        'pptx' => array( 'application/vnd.openxmlformats-officedocument', 'application/zip' ),
        'odt' => array( 'application/vnd.oasis.opendocument', 'application/zip' ),
        'ods' => array( 'application/vnd.oasis.opendocument', 'application/zip' ),
    );

    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ?ticketsystemSettings $settings = null )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    public function enabled()
    {
        return $this->settings->value( 'AttachmentSettings', 'Enabled', 'enabled' ) === 'enabled';
    }

    public function maxBytes()
    {
        return max( 1, (int)$this->settings->value( 'AttachmentSettings', 'MaxFileSize', 25 ) ) * 1048576;
    }

    public function maxFiles()
    {
        return max( 1, (int)$this->settings->value( 'AttachmentSettings', 'MaxFiles', 5 ) );
    }

    /** @return string[] */
    public function extensions()
    {
        return array_values( array_filter( array_map( 'strtolower', (array)$this->settings->value( 'AttachmentSettings', 'AllowedExtensions', array() ) ) ) );
    }

    /** What the forms show: enabled, accept, max_files, max_mb, types. */
    public function formInfo()
    {
        $ext = $this->extensions();
        return array( 'enabled' => $this->enabled() && $ext, 'accept' => implode( ',', array_map( function ( $e ) { return '.' . $e; }, $ext ) ),
                      'max_files' => $this->maxFiles(), 'max_mb' => (int)( $this->maxBytes() / 1048576 ), 'types' => implode( ', ', $ext ) );
    }

    public function directory()
    {
        $d = trim( (string)$this->settings->value( 'AttachmentSettings', 'StorageDirectory', 'var/ticketsystem/attachments' ) );
        if ( $d === '' )
            $d = 'var/ticketsystem/attachments';
        return rtrim( $d[0] === '/' ? $d : eZSys::rootDir() . '/' . $d, '/' );
    }

    /** A file name safe to keep and to send: no path, no control characters, at most 200 characters. */
    public static function cleanName( $name )
    {
        $name = basename( str_replace( '\\', '/', (string)$name ) );
        $name = preg_replace( '/[\x00-\x1F\x7F"<>:|?*\/]+/u', '_', $name );
        $name = trim( $name, " .\t" );
        if ( $name === '' )
            $name = 'file';
        return mb_substr( $name, -200 );
    }

    /**
     * Checks one file (pure apart from finfo and the scanner): '' when it may be stored, else the reason.
     *
     * @param string $name the file name given
     * @param string $path where its content is now
     * @param int $size
     */
    public function check( $name, $path, $size )
    {
        if ( $size <= 0 )
            return 'The file is empty.';
        if ( $size > $this->maxBytes() )
            return sprintf( 'The file is larger than %d MB.', (int)( $this->maxBytes() / 1048576 ) );
        $ext = strtolower( pathinfo( (string)$name, PATHINFO_EXTENSION ) );
        if ( $ext === '' || !in_array( $ext, $this->extensions(), true ) )
            return 'Files of this type are not accepted.';
        $mime = self::sniff( $path );
        if ( !self::typeMatches( $ext, $mime ) )
            return 'The content of the file does not match its type.';
        $scan = $this->scan( $path );
        if ( $scan !== '' )
            return $scan;
        return '';
    }

    public static function sniff( $path )
    {
        if ( !function_exists( 'finfo_open' ) )
            return 'application/octet-stream';
        $f = finfo_open( FILEINFO_MIME_TYPE );
        $m = $f ? (string)finfo_file( $f, $path ) : '';
        return $m !== '' ? $m : 'application/octet-stream';
    }

    /** Whether a sniffed content type fits an extension (pure). */
    public static function typeMatches( $ext, $mime )
    {
        $ext = strtolower( (string)$ext );
        if ( !isset( self::TYPES[$ext] ) )
            return strpos( (string)$mime, 'application/x-' ) !== 0 && strpos( (string)$mime, 'text/html' ) !== 0;
        foreach ( self::TYPES[$ext] as $prefix )
            if ( strpos( (string)$mime, $prefix ) === 0 )
                return true;
        // an empty text file or a short log is sniffed as application/octet-stream or x-empty by some libmagic versions
        return in_array( $ext, array( 'txt', 'log', 'csv' ), true ) && in_array( (string)$mime, array( 'application/x-empty', 'inode/x-empty' ), true );
    }

    /** The virus scan: '' when clean (or no scanner and none required), else the reason. */
    public function scan( $path )
    {
        $cmd = trim( (string)$this->settings->value( 'AttachmentSettings', 'ScanCommand', '' ) );
        if ( $cmd === '' )
            return $this->settings->value( 'AttachmentSettings', 'RequireScan', 'disabled' ) === 'enabled' ? 'Files cannot be checked right now. Please try again later.' : '';
        $line = str_replace( '%file', escapeshellarg( $path ), $cmd );
        $out = array();
        $rc = 1;
        @exec( $line . ' 2>&1', $out, $rc );
        return self::scanVerdict( $rc );
    }

    /**
     * The answer to a scanner's exit code (pure apart from the log): 0 clean (''); 1 a virus was found (ClamAV and most
     * scanners); anything else the scanner failed, and the file is refused as well, since it could not be checked.
     */
    public static function scanVerdict( $rc )
    {
        $rc = (int)$rc;
        if ( $rc === 0 )
            return '';
        if ( class_exists( 'eZLog' ) )
            eZLog::write( 'attachment refused by the scanner (exit ' . $rc . ( $rc === 1 ? ': infected' : ': scanner error' ) . ')', 'ticketsystem.log' );
        return $rc === 1 ? 'The file did not pass the virus check.' : 'Files cannot be checked right now. Please try again later.';
    }

    /**
     * The files of a form field (PHP's $_FILES layout of name="TicketFiles[]"): array( files (name, path, size), errors ).
     */
    public function fromUpload( $field = 'TicketFiles' )
    {
        $files = array();
        $errors = array();
        if ( !$this->enabled() || !isset( $_FILES[$field] ) || !is_array( $_FILES[$field]['name'] ) )
            return array( $files, $errors );
        $u = $_FILES[$field];
        foreach ( $u['name'] as $i => $name )
        {
            $err = (int)$u['error'][$i];
            if ( $err === UPLOAD_ERR_NO_FILE || (string)$name === '' )
                continue;
            if ( $err !== UPLOAD_ERR_OK || !is_uploaded_file( $u['tmp_name'][$i] ) )
            {
                $errors[] = sprintf( '%s: the upload failed.', self::cleanName( $name ) );
                continue;
            }
            $files[] = array( 'name' => self::cleanName( $name ), 'path' => (string)$u['tmp_name'][$i], 'size' => (int)$u['size'][$i] );
        }
        if ( count( $files ) > $this->maxFiles() )
        {
            $errors[] = sprintf( 'At most %d files can be attached at once.', $this->maxFiles() );
            $files = array();
        }
        foreach ( $files as $k => $f )
            if ( ( $why = $this->check( $f['name'], $f['path'], $f['size'] ) ) !== '' )
            {
                $errors[] = $f['name'] . ': ' . $why;
                unset( $files[$k] );
            }
        return array( array_values( $files ), $errors );
    }

    /**
     * Stores checked files of a ticket and records them.
     *
     * @param array $files name, path, size (checked by check())
     * @param int $postId the content object of the post (the ticket object for the opening post), 0 for a note
     * @param int $noteId the note, 0 for a post
     * @return ticketsystemAttachment[]
     */
    public function store( ticketsystemTicket $t, array $files, $postId, $noteId, $uploaderId )
    {
        $out = array();
        foreach ( $files as $f )
        {
            $data = @file_get_contents( $f['path'] );
            if ( !is_string( $data ) )
                continue;
            $key = bin2hex( random_bytes( 16 ) );
            $dir = $this->directory() . '/' . substr( $key, 0, 2 ) . '/' . substr( $key, 2, 2 );
            if ( !is_dir( $dir ) && !@mkdir( $dir, 0750, true ) && !is_dir( $dir ) )
            {
                eZDebug::writeError( 'cannot create the attachment directory', __METHOD__ );
                continue;
            }
            $target = $dir . '/' . $key;
            if ( @file_put_contents( $target, $data, LOCK_EX ) !== strlen( $data ) )
                continue;
            @chmod( $target, 0640 );
            $a = ticketsystemAttachment::make( array(
                'ticket_id' => (int)$t->attribute( 'id' ), 'post_id' => (int)$postId, 'note_id' => (int)$noteId,
                'uploader_id' => (int)$uploaderId, 'name' => self::cleanName( $f['name'] ), 'mime' => self::sniff( $target ),
                'filesize' => strlen( $data ), 'sha256' => hash( 'sha256', $data ), 'storage_key' => $key, 'created' => time() ) );
            $a->store();
            $out[] = $a;
        }
        return $out;
    }

    /** The path of a stored file, or '' when it is missing. */
    public function path( ticketsystemAttachment $a )
    {
        $key = (string)$a->attribute( 'storage_key' );
        if ( !preg_match( '/^[a-f0-9]{32}$/D', $key ) )
            return '';
        $p = $this->directory() . '/' . substr( $key, 0, 2 ) . '/' . substr( $key, 2, 2 ) . '/' . $key;
        return is_file( $p ) ? $p : '';
    }

    /** Removes the file of an attachment (when its ticket goes). */
    public function remove( ticketsystemAttachment $a )
    {
        $p = $this->path( $a );
        if ( $p !== '' )
            @unlink( $p );
        $a->remove();
    }

    /** The rows for the templates, grouped by post object id and note id. */
    public static function byPost( array $attachments )
    {
        $out = array( 'post' => array(), 'note' => array() );
        foreach ( $attachments as $a )
        {
            $row = array( 'id' => (int)$a->attribute( 'id' ), 'name' => (string)$a->attribute( 'name' ),
                          'size' => (int)$a->attribute( 'filesize' ), 'size_label' => self::sizeLabel( (int)$a->attribute( 'filesize' ) ) );
            if ( (int)$a->attribute( 'note_id' ) > 0 )
                $out['note'][(int)$a->attribute( 'note_id' )][] = $row;
            else
                $out['post'][(int)$a->attribute( 'post_id' )][] = $row;
        }
        return $out;
    }

    public static function sizeLabel( $bytes )
    {
        if ( $bytes >= 1048576 )
            return sprintf( '%.1f MB', $bytes / 1048576 );
        return max( 1, (int)ceil( $bytes / 1024 ) ) . ' KB';
    }
}
