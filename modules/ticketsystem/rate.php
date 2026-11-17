<?php
/**
 * ticketsystem/rate/<token>/<score>: the satisfaction rating from the mail "How did we do?" (doc/csat.md). No sign-in:
 * the token is signed for the ticket and works once (until the ticket has a rating). GET shows the page with the
 * score chosen and a comment field (a link checker that opens the link rates nothing); POST stores the rating, with
 * the session's form token, and the page thanks the customer. The page names the ticket's number only, never its
 * subject or anything of the account.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$settings = ticketsystemSettings::instance();
$csat = new ticketsystemCsat( $settings );
$token = isset( $Params['Token'] ) ? (string)$Params['Token'] : '';
$score = isset( $Params['Score'] ) ? (int)$Params['Score'] : 0;
if ( $score < ticketsystemCsat::MIN || $score > ticketsystemCsat::MAX )
    $score = 0;
$check = $csat->ticketForToken( $token );
$state = $check['error'] === '' ? 'form' : 'refused';
$errors = array();
$comment = '';
$done = false;

if ( $state === 'form' && isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && $http->hasPostVariable( 'TicketRate' ) )
{
    $comment = (string)$http->postVariable( 'TicketRatingComment', '' );
    $score = (int)$http->postVariable( 'TicketRating', 0 );
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
        $errors[] = 'The form had expired. Nothing was sent; please send it again.';
    else
    {
        $r = $csat->rateByToken( $token, $score, $comment );
        if ( $r['ok'] )
            $done = true;
        else
            $errors = array_values( $r['errors'] ? $r['errors'] : array( $r['message'] ) );
    }
}

$number = $check['ticket'] ? (string)$check['ticket']->attribute( 'number' ) : '';
$Result = ticketsystemView::render( eZUser::currentUser(), 'rate', 'rate', array(
    'state' => $done ? 'thanks' : $state, 'reason' => $check['error'], 'reason_text' => $check['error'] !== '' ? ticketsystemCsat::tokenMessage( $check['error'] ) : '',
    'number' => $number, 'token' => $token, 'score' => $score, 'comment' => $comment, 'labels' => ticketsystemCsat::labels(), 'ts_errors' => $errors,
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Rating' ) ) );
