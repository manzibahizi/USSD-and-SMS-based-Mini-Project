<?php
if (isset($_POST['sessionId'])) {
    session_id($_POST['sessionId']);
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// USSD Handler for AccessCare
require_once 'config.php';
require_once 'menu_handler.php';

// Get the USSD parameters
$sessionId = $_POST['sessionId'] ?? '';
$serviceCode = $_POST['serviceCode'] ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? '';
$text = $_POST['text'] ?? '';

// Initialize database connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Initialize menu handler
$menuHandler = new MenuHandler($db, $phoneNumber);

// Process the USSD request
if (empty($text)) {
    // First request - show main menu
    $response = $menuHandler->getMainMenu();
} else {
    // Subsequent requests - process user input
    $textArray = explode('*', $text);
    $response = $menuHandler->getSubMenu($textArray);
}

// Send response
header('Content-type: text/plain');
echo $response;

// Close database connection
$db->close();
?> 