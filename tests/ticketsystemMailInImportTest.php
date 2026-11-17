<?php
/**
 * DB-less tests of reply by e-mail (the signed reference, the MIME parser, the cutting of quoted text, the checks of
 * automatic and unauthenticated mail) and of the pure parts of the WHMCS import.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemMailInImportTest extends TestCase
{
    const SECRET = 'test-secret-0123456789abcdef0123456789abcdef';

    public function testReferenceIsSignedAndFound()
    {
        $ref = ticketsystemReplyToken::reference( '7x-2026-00042', 123, self::SECRET );
        $this->assertMatchesRegularExpression( '/^ts\.7X-2026-00042\.123\.[a-f0-9]{20}$/', $ref );
        $mid = ticketsystemReplyToken::messageId( '7X-2026-00042', 9, 123, self::SECRET, 'example.com' );
        $this->assertSame( '<' . $ref . '.9@example.com>', $mid );
        $found = ticketsystemReplyToken::find( array( 'Re: something', $mid ), self::SECRET );
        $this->assertSame( array( array( 'number' => '7X-2026-00042', 'user_id' => 123 ) ), $found );
        // another person, another ticket or another secret: not found
        $forged = str_replace( 'ts.7X-2026-00042.123.', 'ts.7X-2026-00042.124.', $ref );
        $this->assertSame( array(), ticketsystemReplyToken::find( array( $forged ), self::SECRET ) );
        $this->assertSame( array(), ticketsystemReplyToken::find( array( $ref ), 'other' ) );
        $this->assertSame( 'support+' . $ref . '@example.com', ticketsystemReplyToken::replyAddress( 'support+%token@example.com', '7X-2026-00042', 123, self::SECRET ) );
        $this->assertSame( '', ticketsystemReplyToken::replyAddress( 'support@example.com', '7X-2026-00042', 123, self::SECRET ) );
        $this->assertLessThanOrEqual( 64, strlen( 'support+' . ticketsystemReplyToken::reference( '7X-2026-123456789', 4294967295, self::SECRET ) ), 'a local part fits RFC 5321' );
    }

    public function testSubjectNumber()
    {
        $this->assertSame( '7X-2026-00042', ticketsystemReplyToken::subjectNumber( 'Re: [7X-2026-00042] Mail stops', '7X' ) );
        $this->assertSame( '7X-2026-00042', ticketsystemReplyToken::subjectNumber( 'AW: Ticket 7x-2026-00042', '7X' ) );
        $this->assertSame( '', ticketsystemReplyToken::subjectNumber( 'Re: [AB-2026-00042] Mail stops', '7X' ) );
        $this->assertSame( '', ticketsystemReplyToken::subjectNumber( 'Order 17X-2026-000421', '7X' ) );
    }

    public function testStripQuoted()
    {
        $ref = ticketsystemReplyToken::reference( '7X-2026-00042', 5, self::SECRET );
        $text = "Thanks, that worked.\nBest\n\nOn Mon, 16 Nov 2026 at 10:00, Help desk <support@example.com>\nwrote:\n> old text\n> more";
        $this->assertSame( "Thanks, that worked.\nBest", ticketsystemReplyToken::stripQuoted( $text ) );
        $this->assertSame( 'Danke!', ticketsystemReplyToken::stripQuoted( "Danke!\n\nAm 16.11.2026 um 10:00 schrieb Help desk <a@b.c>:\n> alt" ) );
        $this->assertSame( 'Yes.', ticketsystemReplyToken::stripQuoted( "Yes.\n-- \nJane, ACME Ltd\n+1 555" ) );
        $this->assertSame( 'Ok', ticketsystemReplyToken::stripQuoted( "Ok\n\n-----Original Message-----\nFrom: x\nSent: y" ) );
        $this->assertSame( 'Ok', ticketsystemReplyToken::stripQuoted( "Ok\n\nFrom: Help desk <a@b.c>\nSent: Monday\nTo: me" ) );
        $this->assertSame( 'Ok', ticketsystemReplyToken::stripQuoted( "Ok\n$ref\n\nSent from my phone" ) );
    }

    private function mail( array $headers, $body = "Hello\n" )
    {
        $h = '';
        foreach ( $headers + array( 'From' => 'Jane <Jane@Example.com>', 'To' => 'support@example.com', 'Subject' => 'Re: [7X-2026-00042] Test',
                                    'Message-ID' => '<m1@example.com>' ) as $k => $v )
            $h .= "$k: $v\r\n";
        return $h . "\r\n" . $body;
    }

    public function testParserTextAttachmentsAndHeaders()
    {
        $raw = $this->mail( array( 'MIME-Version' => '1.0', 'Content-Type' => 'multipart/mixed; boundary="b1"', 'In-Reply-To' => '<ts.x@example.com>',
                                   'Subject' => '=?UTF-8?B?UmU6IFs3WC0yMDI2LTAwMDQyXSBHcsO8w59l?=' ),
            "--b1\r\nContent-Type: multipart/alternative; boundary=\"b2\"\r\n\r\n--b2\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nGr=C3=BC=C3=9Fe\r\n--b2\r\nContent-Type: text/html\r\n\r\n<p>x</p>\r\n--b2--\r\n"
            . "--b1\r\nContent-Type: application/pdf; name=\"a.pdf\"\r\nContent-Disposition: attachment; filename=\"../a.pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . base64_encode( '%PDF-1' ) . "\r\n--b1--\r\n" );
        $m = ticketsystemMailParser::parse( $raw );
        $this->assertSame( 'jane@example.com', $m['from'] );
        $this->assertSame( 'Re: [7X-2026-00042] Grüße', $m['subject'] );
        $this->assertSame( 'Grüße', $m['text'] );
        $this->assertSame( array( '<ts.x@example.com>' ), $m['references'] );
        $this->assertCount( 1, $m['attachments'] );
        $this->assertSame( '%PDF-1', $m['attachments'][0]['data'] );
        $this->assertSame( 'a.pdf', ticketsystemMailIn::cleanName( $m['attachments'][0]['name'] ) );
        $html = ticketsystemMailParser::parse( $this->mail( array( 'Content-Type' => 'text/html' ), '<div>Hi<br>there</div><blockquote>old</blockquote>' ) );
        $this->assertStringStartsWith( "Hi\nthere", $html['text'] );
    }

    public function testAutomaticAndAuthentication()
    {
        $p = function ( array $h ) { return ticketsystemMailParser::parse( $this->mail( $h ) ); };
        $this->assertFalse( ticketsystemMailIn::isAutomatic( $p( array() ) ) );
        $this->assertTrue( ticketsystemMailIn::isAutomatic( $p( array( 'Auto-Submitted' => 'auto-replied' ) ) ) );
        $this->assertTrue( ticketsystemMailIn::isAutomatic( $p( array( 'Precedence' => 'bulk' ) ) ) );
        $this->assertTrue( ticketsystemMailIn::isAutomatic( $p( array( 'X-Ticket-Number' => '7X-2026-00042' ) ) ), 'our own ticket mail' );
        $this->assertTrue( ticketsystemMailIn::isAutomatic( $p( array( 'From' => 'MAILER-DAEMON@example.com' ) ) ) );
        $this->assertFalse( ticketsystemMailIn::authenticationFailed( $p( array( 'Authentication-Results' => 'mx; spf=pass smtp.mailfrom=example.com; dkim=pass; dmarc=pass' ) ) ) );
        $this->assertTrue( ticketsystemMailIn::authenticationFailed( $p( array( 'Authentication-Results' => 'mx; spf=fail smtp.mailfrom=example.com' ) ) ) );
        $this->assertTrue( ticketsystemMailIn::authenticationFailed( $p( array( 'Authentication-Results' => 'mx; dmarc=fail header.from=example.com' ) ) ) );
    }

    public function testWhmcsText()
    {
        $this->assertSame( "Line 1\nLine 2\n\nNext & more", ticketsystemWhmcsImport::text( "Line 1<br />Line 2</p><p>Next &amp; more</p>" ) );
        $this->assertSame( "a\n\nb", ticketsystemWhmcsImport::text( "a\r\n\r\n\r\n\r\nb" ) );
        $this->assertSame( 0, ticketsystemWhmcsImport::time( '0000-00-00 00:00:00' ) );
        $this->assertSame( strtotime( '2024-05-11 14:03:29' ), ticketsystemWhmcsImport::time( '2024-05-11 14:03:29' ) );
    }

    public function testWhmcsExportShape()
    {
        list( $e, $err ) = ticketsystemWhmcsImport::checkExport( array( 'source' => 'whmcs', 'counts' => array() ) );
        $this->assertNull( $e );
        $this->assertStringContainsString( 'counts only', $err );
        list( $e ) = ticketsystemWhmcsImport::checkExport( array( 'source' => 'other', 'tickets' => array() ) );
        $this->assertNull( $e );
        list( $e ) = ticketsystemWhmcsImport::checkExport( array( 'source' => 'whmcs', 'tickets' => array() ) );
        $this->assertIsArray( $e );
        $t = ticketsystemWhmcsImport::normalize( array( 'id' => 3, 'replies' => array( array( 'id' => 9 ) ) ) );
        $this->assertSame( 0, $t['userid'] );
        $this->assertFalse( $t['replies'][0]['staff'] );
        $this->assertSame( '(Imported from WHMCS ticket #ABC-1, General Enquiries, Closed.)',
                           ticketsystemWhmcsImport::originLine( array( 'tid' => 'ABC-1', 'id' => 3, 'status' => 'Closed' ), 'General Enquiries', 'Closed' ) );
    }

    public function testWhmcsMapping()
    {
        $settings = new ticketsystemSettings( array(
            'StatusSettings' => array( 'StatusSet' => '7x' ),
            'CategorySettings' => array( 'Categories' => array( 'general' => 'General question', 'billing' => 'Billing' ), 'Default' => 'general' ),
            'PrioritySettings' => array( 'Priorities' => array( 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High' ), 'Default' => 'medium' ),
            'ImportSettings' => array( 'StatusMap' => array( 'Open' => 'open' ), 'DepartmentMap' => array( '4' => 'billing' ),
                                       'StaffUserMap' => array( '2' => '77' ), 'ImportUserID' => '55' ) ) );
        $i = new ticketsystemWhmcsImport( true, array(), $settings );
        $this->assertSame( 'closed', $i->status( 'Answered' ) );
        $this->assertSame( 'closed', $i->status( 'Closed' ) );
        $this->assertSame( 'open', $i->status( 'open' ) );
        $this->assertSame( 'high', $i->priority( 'High' ) );
        $this->assertSame( 'medium', $i->priority( 'Critical' ) );
        $this->assertSame( 'billing', $i->category( 4, 'Accounts' ) );
        $this->assertSame( 'billing', $i->category( 1, 'billing' ) );
        $this->assertSame( 'general', $i->category( 1, 'General Enquiries' ) );
        $this->assertSame( 77, $i->staffUser( 2 ) );
        $this->assertSame( 55, $i->staffUser( 3 ) );
        $this->assertSame( array( 0, '', 'skipped_guest' ), $i->opener( 0 ) );
    }
}
