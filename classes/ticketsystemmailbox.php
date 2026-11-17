<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * A small IMAP and POP3 client for the mail-in poll (doc/mail-in.md, cronjob part ticketsystem_mailin): it fetches
 * the waiting messages of one mailbox and marks each handled one, over TLS (ssl = implicit, tls = STARTTLS) or plain.
 * Plain PHP streams, no PHP extension (the imap extension left PHP's core with 8.4).
 *
 * IMAP: the unseen messages of Folder are fetched without setting \Seen (BODY.PEEK[]); a handled message gets \Seen
 * and, with a DoneFolder, is moved there. POP3: each message is fetched and, once handled, deleted.
 */
class ticketsystemMailbox
{
    /** @var resource|null */
    protected $s;
    protected $protocol;
    protected $tag = 0;
    protected $folder = 'INBOX';
    protected $capabilities = '';
    /** @var string[] the transcript of the last command (no passwords), for errors */
    public $last = '';

    /**
     * @param array $c protocol (imap|pop3), host, port, encryption (ssl|tls|none), user, password, folder, timeout
     * @throws RuntimeException
     */
    public function __construct( array $c )
    {
        $c += array( 'protocol' => 'imap', 'host' => '', 'port' => 0, 'encryption' => 'ssl', 'user' => '', 'password' => '',
                     'folder' => 'INBOX', 'timeout' => 30, 'verify_peer' => true );
        $this->protocol = strtolower( (string)$c['protocol'] ) === 'pop3' ? 'pop3' : 'imap';
        $this->folder = (string)$c['folder'] !== '' ? (string)$c['folder'] : 'INBOX';
        $enc = strtolower( (string)$c['encryption'] );
        $port = (int)$c['port'] ?: ( $this->protocol === 'imap' ? ( $enc === 'ssl' ? 993 : 143 ) : ( $enc === 'ssl' ? 995 : 110 ) );
        if ( (string)$c['host'] === '' )
            throw new RuntimeException( 'mailbox: no host' );
        $ctx = stream_context_create( array( 'ssl' => array( 'verify_peer' => (bool)$c['verify_peer'], 'verify_peer_name' => (bool)$c['verify_peer'],
                                                             'peer_name' => (string)$c['host'] ) ) );
        $this->s = @stream_socket_client( ( $enc === 'ssl' ? 'ssl://' : 'tcp://' ) . $c['host'] . ':' . $port, $errno, $errstr,
                                          (int)$c['timeout'], STREAM_CLIENT_CONNECT, $ctx );
        if ( !$this->s )
            throw new RuntimeException( "mailbox: cannot connect ($errstr)" );
        stream_set_timeout( $this->s, (int)$c['timeout'] );
        if ( $this->protocol === 'imap' )
        {
            $this->expect( $this->line(), '* OK' );
            if ( $enc === 'tls' )
            {
                $this->imap( 'STARTTLS' );
                $this->tls();
            }
            $this->imap( 'LOGIN ' . self::quote( $c['user'] ) . ' ' . self::quote( $c['password'] ), true );
            $caps = $this->imap( 'CAPABILITY' );
            $this->capabilities = strtoupper( implode( ' ', $caps ) );
            $this->imap( 'SELECT ' . self::quote( $this->folder ) );
        }
        else
        {
            $this->expect( $this->line(), '+OK' );
            if ( $enc === 'tls' )
            {
                $this->pop( 'STLS' );
                $this->tls();
            }
            $this->pop( 'USER ' . $c['user'] );
            $this->pop( 'PASS ' . $c['password'], true );
        }
    }

    protected function tls()
    {
        if ( !@stream_socket_enable_crypto( $this->s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ) )
            throw new RuntimeException( 'mailbox: STARTTLS failed' );
    }

    /** An IMAP quoted string. */
    public static function quote( $s )
    {
        return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string)$s ) . '"';
    }

    protected function line()
    {
        $l = fgets( $this->s, 65536 );
        if ( $l === false )
            throw new RuntimeException( 'mailbox: the connection closed' );
        return $l;
    }

    protected function expect( $line, $start )
    {
        if ( strpos( $line, $start ) !== 0 )
            throw new RuntimeException( 'mailbox: unexpected answer: ' . substr( trim( $line ), 0, 120 ) );
    }

    protected function write( $command, $secret = false )
    {
        $this->last = $secret ? preg_replace( '/^(\S+\s+\S+).*/', '$1 ...', $command ) : $command;
        fwrite( $this->s, $command . "\r\n" );
    }

    /**
     * Sends an IMAP command and reads its answer; literals ({n}) are read whole.
     *
     * @return string[] the untagged lines, each with its literals
     */
    protected function imap( $command, $secret = false )
    {
        $tag = 'T' . ( ++$this->tag );
        $this->write( "$tag $command", $secret );
        $out = array();
        while ( true )
        {
            $l = $this->line();
            while ( preg_match( '/\{(\d+)\}\r?\n$/', $l, $m ) )
            {
                $data = '';
                $want = (int)$m[1];
                while ( strlen( $data ) < $want )
                {
                    $chunk = fread( $this->s, $want - strlen( $data ) );
                    if ( $chunk === false || $chunk === '' )
                        throw new RuntimeException( 'mailbox: the connection closed in a message' );
                    $data .= $chunk;
                }
                $l .= $data . $this->line();
            }
            if ( strpos( $l, "$tag " ) === 0 )
            {
                if ( strpos( $l, "$tag OK" ) !== 0 )
                    throw new RuntimeException( 'mailbox: ' . ( $secret ? 'login' : strtok( $command, ' ' ) ) . ' failed: ' . substr( trim( substr( $l, strlen( $tag ) + 1 ) ), 0, 120 ) );
                return $out;
            }
            $out[] = $l;
        }
    }

    /** Sends a POP3 command; with $multi the dot-terminated answer is returned (dot-unstuffed). */
    protected function pop( $command, $secret = false, $multi = false )
    {
        $this->write( $command, $secret );
        $l = $this->line();
        if ( strpos( $l, '+OK' ) !== 0 )
            throw new RuntimeException( 'mailbox: ' . ( $secret ? 'login' : strtok( $command, ' ' ) ) . ' failed: ' . substr( trim( $l ), 0, 120 ) );
        if ( !$multi )
            return $l;
        $data = '';
        while ( ( $l = $this->line() ) !== ".\r\n" && $l !== ".\n" )
            $data .= ( isset( $l[0] ) && $l[0] === '.' ) ? substr( $l, 1 ) : $l;
        return $data;
    }

    /**
     * The ids of the waiting messages (IMAP: unseen UIDs, narrowed by the IMAP search keys $search; POP3: message
     * numbers, every message).
     *
     * @return string[]
     */
    public function waiting( $limit = 50, $search = "" )
    {
        if ( $this->protocol === 'pop3' )
        {
            $stat = $this->pop( 'STAT' );
            $n = preg_match( '/^\+OK (\d+)/', $stat, $m ) ? (int)$m[1] : 0;
            return $n ? array_map( 'strval', range( 1, min( $n, $limit ) ) ) : array();
        }
        $ids = array();
        foreach ( $this->imap( "UID SEARCH UNSEEN" . ( $search !== "" ? " $search" : "" ) ) as $l )
            if ( preg_match( '/^\* SEARCH ?(.*)$/', trim( $l ), $m ) )
                foreach ( preg_split( '/\s+/', trim( $m[1] ) ) as $id )
                    if ( ctype_digit( $id ) )
                        $ids[] = $id;
        return array_slice( $ids, 0, $limit );
    }

    /** The raw message of an id. */
    public function fetch( $id )
    {
        if ( $this->protocol === 'pop3' )
            return $this->pop( 'RETR ' . (int)$id, false, true );
        foreach ( $this->imap( 'UID FETCH ' . (int)$id . ' BODY.PEEK[]' ) as $l )
            if ( preg_match( '/\{(\d+)\}\r?\n/', $l, $m, PREG_OFFSET_CAPTURE ) )
                return substr( $l, $m[0][1] + strlen( $m[0][0] ), (int)$m[1][0] );
        throw new RuntimeException( "mailbox: message $id not found" );
    }

    /** Marks a message handled: IMAP \Seen and moved to $doneFolder when given; POP3 deleted. */
    public function done( $id, $doneFolder = '' )
    {
        if ( $this->protocol === 'pop3' )
        {
            $this->pop( 'DELE ' . (int)$id );
            return;
        }
        $this->imap( 'UID STORE ' . (int)$id . ' +FLAGS.SILENT (\\Seen)' );
        if ( $doneFolder === '' )
            return;
        if ( strpos( $this->capabilities, 'MOVE' ) !== false )
            $this->imap( 'UID MOVE ' . (int)$id . ' ' . self::quote( $doneFolder ) );
        else
        {
            $this->imap( 'UID COPY ' . (int)$id . ' ' . self::quote( $doneFolder ) );
            $this->imap( 'UID STORE ' . (int)$id . ' +FLAGS.SILENT (\\Deleted)' );
            $this->imap( 'EXPUNGE' );
        }
    }

    public function close()
    {
        if ( !$this->s )
            return;
        try
        {
            if ( $this->protocol === 'pop3' )
                $this->pop( 'QUIT' );
            else
                $this->imap( 'LOGOUT' );
        }
        catch ( Throwable $e )
        {
        }
        @fclose( $this->s );
        $this->s = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
