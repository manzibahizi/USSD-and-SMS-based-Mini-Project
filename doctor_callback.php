<?php
// Doctor Response Callback Handler
require_once 'config.php';
require_once 'menu_handler.php';
require_once 'sms_handler.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'doctor_ussd_errors.log');

// Function to log errors
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, 'doctor_ussd_errors.log');
}

// Function to send error response
function sendErrorResponse($message) {
    echo "END " . $message;
    exit;
}

try {
    // Get callback parameters
    $sessionId = $_POST['sessionId'] ?? '';
    $serviceCode = $_POST['serviceCode'] ?? '';
    $phoneNumber = $_POST['phoneNumber'] ?? '';
    $text = $_POST['text'] ?? '';

    // Validate required parameters
    if (empty($sessionId) || empty($serviceCode) || empty($phoneNumber)) {
        sendErrorResponse("Missing required parameters");
    }

    // Initialize database connection with retry
    $maxRetries = 3;
    $retryCount = 0;
    $db = null;

    while ($retryCount < $maxRetries) {
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }
            break;
        } catch (Exception $e) {
            $retryCount++;
            if ($retryCount == $maxRetries) {
                logError("Database connection failed after $maxRetries attempts: " . $e->getMessage());
                sendErrorResponse("System is temporarily unavailable. Please try again later.");
            }
            sleep(1); // Wait 1 second before retrying
        }
    }

    // Initialize menu handler and SMS handler
    $menuHandler = new MenuHandler($db, $phoneNumber);
    $smsHandler = new SMSHandler($db, API_KEY, 'sandbox', ALPHANUMERIC_CODE);

    // Process doctor's response
    $textArray = explode('*', $text);
    $level = count($textArray);

    // Handle appointment response
    if ($level >= 3 && $textArray[0] == '1') { // View pending appointments
        $appointmentId = $textArray[1];
        $response = $textArray[2]; // 1 for approve, 2 for reject

        // Get appointment details
        $stmt = $db->prepare("
            SELECT a.*, u.name as patient_name, u.phone_number as patient_phone
            FROM appointments a 
            JOIN users u ON a.patient_phone = u.phone_number 
            WHERE a.id = ? 
            AND a.doctor_phone = ? 
            AND a.status = 'pending'
        ");
        $stmt->bind_param("is", $appointmentId, $phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $appointment = $result->fetch_assoc();
            
            // Update appointment status
            $newStatus = ($response == '1') ? 'approved' : 'rejected';
            $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $appointmentId);
            
            if ($stmt->execute()) {
                // Send SMS notification to patient
                $message = ($newStatus == 'approved') 
                    ? "Your appointment with Dr. " . $appointment['doctor_name'] . " for " . 
                      $appointment['appointment_date'] . " at " . $appointment['appointment_time'] . 
                      " has been approved."
                    : "Your appointment with Dr. " . $appointment['doctor_name'] . " for " . 
                      $appointment['appointment_date'] . " at " . $appointment['appointment_time'] . 
                      " has been rejected.";
                
                $smsHandler->sendSMS($appointment['patient_phone'], $message);
                
                echo "END Appointment has been " . $newStatus . ". Patient has been notified.";
            } else {
                logError("Failed to update appointment status: " . $stmt->error);
                sendErrorResponse("Failed to process appointment. Please try again.");
            }
        } else {
            sendErrorResponse("Invalid appointment ID or appointment already processed.");
        }
    } else {
        // Handle other menu options
        $response = $menuHandler->getSubMenu($textArray);
        echo $response;
    }

} catch (Exception $e) {
    logError("Error in doctor callback: " . $e->getMessage());
    sendErrorResponse("An error occurred. Please try again later.");
} finally {
    if (isset($db)) {
        $db->close();
    }
}
?> 