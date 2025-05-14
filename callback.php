<?php
// Africa's Talking USSD Callback Handler
require_once 'config.php';
require_once 'menu_handler.php';
require_once 'sms_handler.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'ussd_errors.log');

// Function to log errors
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, 'ussd_errors.log');
}

// Function to send error response
function sendErrorResponse($message) {
    header('Content-type: text/plain');
    echo "END " . $message;
    exit;
}

try {
    // Get the callback parameters
    $sessionId = $_POST['sessionId'] ?? '';
    $serviceCode = $_POST['serviceCode'] ?? '';
    $phoneNumber = $_POST['phoneNumber'] ?? '';
    $text = $_POST['text'] ?? '';

    // Validate required parameters
    if (empty($sessionId) || empty($serviceCode) || empty($phoneNumber)) {
        logError("Missing parameters - Session: $sessionId, Service: $serviceCode, Phone: $phoneNumber");
        sendErrorResponse("Dear customer, please try again. If the problem persists, contact support.");
        exit;
    }

    // Initialize database connection with retry
    $maxRetries = 3;
    $retryCount = 0;
    $db = null;

    while ($retryCount < $maxRetries) {
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$db->connect_error) {
                break;
            }
            $retryCount++;
            if ($retryCount < $maxRetries) {
                sleep(1); // Wait 1 second before retrying
            }
        } catch (Exception $e) {
            logError("Database connection attempt $retryCount failed: " . $e->getMessage());
            $retryCount++;
            if ($retryCount < $maxRetries) {
                sleep(1);
            }
        }
    }

    if ($db === null || $db->connect_error) {
        logError("Database connection failed after $maxRetries attempts");
        sendErrorResponse("Dear customer, the system is temporarily unavailable. Please try again in a few minutes.");
        exit;
    }

    // Set connection timeout
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    // Initialize menu handler
    $menuHandler = new MenuHandler($db, $phoneNumber);

    // Initialize SMS handler
    $smsHandler = new SMSHandler($db, API_KEY, 'sandbox', ALPHANUMERIC_CODE);

    // Process the USSD request
    if (empty($text)) {
        // First request - show main menu
        $response = $menuHandler->getMainMenu();
    } else {
        // Subsequent requests - process user input
        $textArray = explode('*', $text);
        $response = $menuHandler->getSubMenu($textArray);
        
        // Check if this is an appointment booking completion
        if (strpos($response, "Your appointment has been booked successfully") !== false) {
            try {
                // Send SMS confirmation with retry
                $message = "Thank you for booking an appointment with AccessCare. " .
                          "Your appointment is pending doctor approval. " .
                          "You will receive another SMS once approved.";
                
                $smsSent = false;
                $smsRetries = 2;
                
                while ($smsRetries > 0 && !$smsSent) {
                    if ($smsHandler->sendSMS($phoneNumber, $message)) {
                        $smsSent = true;
                    } else {
                        $smsRetries--;
                        if ($smsRetries > 0) {
                            sleep(1);
                        }
                    }
                }
                
                if (!$smsSent) {
                    logError("Failed to send SMS to $phoneNumber after retries");
                }
            } catch (Exception $e) {
                logError("SMS sending error: " . $e->getMessage());
                // Continue with the USSD response even if SMS fails
            }
        }
    }

    // Send response
    header('Content-type: text/plain');
    echo $response;

} catch (Exception $e) {
    logError("Unexpected error: " . $e->getMessage());
    sendErrorResponse("Dear customer, please try again. If the problem persists, contact support.");
} finally {
    // Close database connection if it exists
    if (isset($db) && $db instanceof mysqli) {
        $db->close();
    }
}
?> 