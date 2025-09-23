<?php
// --- File: function_calling_test.php ---

// Bootstrap iTop environment
require_once(__DIR__ . '/../../approot.inc.php');
require_once(APPROOT.'/application/startup.inc.php');

use Itomig\iTop\Extension\AIBase\Service\AIService;
use Itomig\iTop\Extension\AIBase\Helper\AITools;

echo "--- Starting Function Calling Test ---
";

// 1. Instantiate the AIService as usual
$oAIService = new AIService();

// 2. Use the new, clean registration method to register the getCurrentDate tool
$oAIService->registerTool(
    'getCurrentDate',
    AITools::class,
    'Use this function to get the current date and time.'
);

// 3. Start a conversation that should trigger the tool
$aHistory = [['role' => 'user', 'content' => 'Hello, can you tell me the date and time please?']];
$aResult = $oAIService->ContinueConversation($aHistory);

// 4. Print the final, user-friendly response
echo "User: Hello, can you tell me the date and time please?\n";
echo "AI: " . $aResult['response'] . "\n\n";

echo "--- Final History ---
";
print_r($aResult['history']);

echo "--- Test Complete ---
";


