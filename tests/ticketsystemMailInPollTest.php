<?php
/**
 * The mail-in poll against the kernel's fake IMAP and POP3 servers (Exponential 6.0.15 tests/toolkit/
 * expfakemailserver.php): a message whose handling fails stops only itself and stays on the server, unread; the others
 * are handled and marked done. Skipped where the kernel's test toolkit is not found (EXP_KERNEL_ROOT, or the
 * installation this extension sits in).
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../classes/ticketsystemmailbox.php';

/** A mail-in whose handling of a message containing "boom" fails. */
class ticketsystemFailingMailIn extends ticketsystemMailIn
{
    public $handled = array();

    public function handle( $raw )
    {
        if ( strpos( $raw, 'boom' ) !== false )
            throw new RuntimeException( 'the database went away' );
        $this->handled[] = preg_match( '/^Subject: (.*)$/m', $raw, $m ) ? trim( $m[1] ) : '';
        return array( 'result' => 'stored', 'reason' => 'test', 'ticket' => '', 'exit' => 0, 'files_held' => 0 );
    }
}

class ticketsystemMailInPollTest extends TestCase
{
    protected $server;

    protected function setUp(): void
    {
        parent::setUp();
        $roots = array_filter( array( getenv( 'EXP_KERNEL_ROOT' ), dirname( __DIR__, 3 ), getcwd() ) );
        foreach ( $roots as $root )
        {
            if ( is_file( $root . '/tests/toolkit/expfakemailserver.php' ) )
            {
                require_once $root . '/tests/toolkit/expfakemailserver.php';
                break;
            }
        }
        if ( !class_exists( 'expFakeMailServer' ) )
            $this->markTestSkipped( 'the kernel test toolkit with expFakeMailServer (Exponential 6.0.15) is not found; set EXP_KERNEL_ROOT' );
        if ( !class_exists( 'eZDebug' ) || !class_exists( 'eZLog' ) )
            $this->markTestSkipped( 'needs the kernel classes (eZDebug, eZLog)' );
    }

    protected function tearDown(): void
    {
        if ( $this->server )
            $this->server->stop();
        $this->server = null;
        parent::tearDown();
    }

    public static function protocols()
    {
        return array( 'imap' => array( 'imap' ), 'pop3' => array( 'pop3' ) );
    }

    /**
     * @dataProvider protocols
     */
    #[DataProvider('protocols')]
    public function testOneFailingMessageDoesNotStopTheOthers( $protocol )
    {
        $mail = function ( $subject, $body ) {
            return "From: jo@example.invalid\r\nTo: support@example.invalid\r\nSubject: $subject\r\nMessage-ID: <$subject@example.invalid>\r\n\r\n$body\r\n";
        };
        $this->server = expFakeMailServer::start( $protocol, array( $mail( 'one', 'fine' ), $mail( 'two', 'boom' ), $mail( 'three', 'fine' ) ) );
        $a = $this->server->account();
        $box = new ticketsystemMailbox( array( 'protocol' => $protocol, 'host' => $a['server'], 'port' => $a['port'], 'encryption' => 'none',
                                               'user' => $a['user'], 'password' => $a['password'], 'timeout' => 5 ) );
        $in = new ticketsystemFailingMailIn( new ticketsystemSettings( array( 'MailInSettings' => array( 'TicketMailOnly' => 'disabled' ) ) ), 'poll' );
        $results = $in->poll( 50, $box );
        $this->assertSame( array( 'stored', 'failed', 'stored' ), array_column( $results, 'result' ) );
        $this->assertStringContainsString( 'the database went away', $results[1]['reason'] );
        $this->assertSame( array( 'one', 'three' ), $in->handled );
        $commands = implode( "\n", $this->server->commands() );
        if ( $protocol === 'pop3' )
        {
            $this->assertSame( array( 2 ), $this->server->left(), 'only the failed message stays' );
            $this->assertStringNotContainsString( 'DELE 2', $commands );
        }
        else
        {
            $this->assertSame( array( 1, 2, 3 ), $this->server->left() );
            $this->assertStringContainsString( 'UID STORE 1 +FLAGS.SILENT (\\Seen)', $commands );
            $this->assertStringContainsString( 'UID STORE 3 +FLAGS.SILENT (\\Seen)', $commands );
            $this->assertStringNotContainsString( 'UID STORE 2', $commands, 'the failed message stays unread' );
        }
    }

    public function testTheKernelRuleMarksAutomaticMail()
    {
        if ( !class_exists( 'expMailReceive' ) )
            $this->markTestSkipped( 'expMailReceive (Exponential 6.0.15) is not loaded' );
        $p = function ( array $h ) {
            return array( 'from' => 'jo@example.invalid', 'headers' => array_change_key_case( $h, CASE_LOWER ) );
        };
        $this->assertTrue( ticketsystemMailIn::isAutomatic( $p( array( 'X-Autorespond' => 'yes' ) ) ), 'the kernel rule adds X-Autorespond' );
        $this->assertTrue( ticketsystemMailIn::isAutomatic( $p( array( 'Precedence' => 'auto_reply' ) ) ) );
        $this->assertFalse( ticketsystemMailIn::isAutomatic( $p( array( 'Auto-Submitted' => 'no' ) ) ) );
    }
}
