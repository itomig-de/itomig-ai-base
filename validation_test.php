<?php
// --- File: /var/www/html/itop320pro3/extensions/itomig-ai-base/validation_test.php ---

// Bootstrap iTop environment
require_once(__DIR__ . '/../../approot.inc.php');
require_once(APPROOT.'/application/startup.inc.php');

use Itomig\iTop\Extension\AIBase\Service\AIService;
use DBObject;

// --- Test Setup ---
$iTicketId = 1; // IMPORTANT: Change this to a valid Ticket ID in your iTop instance
$oTicket = DBObject::Get('Ticket', $iTicketId);

if (!$oTicket) {
    die("Error: Ticket with ID {$iTicketId} not found. Please update the ID in the script.\n");
}

echo "--- Starting Conversational Test with Ticket: " . $oTicket->GetName() . " ---
\n";

// --- First Turn ---
echo "--- Turn 1 ---
";
$oAIService = new AIService();

// The caller is responsible for managing the history
$aHistory = [];
$aHistory[] = ['role' => 'user', 'content' => 'Please summarize the description of this ticket in one sentence.'];

$aResult1 = $oAIService->ContinueConversation($aHistory, $oTicket);
$sResponse1 = $aResult1['response'];
$aHistory = $aResult1['history']; // Update history with the latest state

echo "User: Please summarize the description of this ticket in one sentence.\n";
echo "AI: " . $sResponse1 . "\n\n";

// --- Second Turn ---
echo "--- Turn 2 ---
";
$aHistory[] = ['role' => 'user', 'content' => 'Thank you. Now, what is its current status?'];

$aResult2 = $oAIService->ContinueConversation($aHistory, $oTicket);
$sResponse2 = $aResult2['response'];
$aHistory = $aResult2['history']; // Final history state

echo "User: Thank you. Now, what is its current status?\n";
echo "AI: " . $sResponse2 . "\n\n";

echo "--- Final History ---
";
print_r($aHistory);
