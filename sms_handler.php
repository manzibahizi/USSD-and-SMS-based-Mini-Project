<?php
// SMS Handler for Africa's Talking
class SMSHandler {
    private $db;
    private $apiKey;
    private $username;
    private $senderId;

    public function __construct($db, $apiKey, $username, $senderId) {
        $this->db = $db;
        $this->apiKey = $apiKey;
        $this->username = $username;
        $this->senderId = $senderId;
    }

    public function sendSMS($phoneNumber, $message) {
        // Log SMS attempt
        $this->logSMS($phoneNumber, $message, 'pending');

        // Format phone number (remove leading 0 and add country code if needed)
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        // Prepare Africa's Talking API request
        $url = "https://api.africastalking.com/version1/messaging";
        $data = [
            'username' => $this->username,
            'to' => $phoneNumber,
            'message' => $message,
            'from' => $this->senderId
        ];

        $headers = [
            'apiKey: ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ];

        // Send SMS
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Update SMS log
        $status = ($httpCode == 201) ? 'sent' : 'failed';
        $this->updateSMSLog($phoneNumber, $message, $status);

        return $httpCode == 201;
    }

    private function formatPhoneNumber($phoneNumber) {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If number starts with 0, replace with country code
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '254' . substr($phoneNumber, 1);
        }
        
        // If number doesn't start with country code, add it
        if (substr($phoneNumber, 0, 3) !== '254') {
            $phoneNumber = '254' . $phoneNumber;
        }
        
        return $phoneNumber;
    }

    private function logSMS($phoneNumber, $message, $status) {
        $stmt = $this->db->prepare("
            INSERT INTO sms_logs (phone_number, message, status) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sss", $phoneNumber, $message, $status);
        $stmt->execute();
    }

    private function updateSMSLog($phoneNumber, $message, $status) {
        $stmt = $this->db->prepare("
            UPDATE sms_logs 
            SET status = ? 
            WHERE phone_number = ? AND message = ? AND status = 'pending'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->bind_param("sss", $status, $phoneNumber, $message);
        $stmt->execute();
    }

    public function getSMSHistory($phoneNumber, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT * FROM sms_logs 
            WHERE phone_number = ? 
            ORDER BY sent_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param("si", $phoneNumber, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Usage example:
/*
$db = new mysqli('localhost', 'root', '', 'accesscare_db');
$smsHandler = new SMSHandler(
    $db,
    'your_api_key',
    'sandbox', // or your Africa's Talking username
    'DOCTOR' // your alphanumeric sender ID
);

// Send SMS
$smsHandler->sendSMS('0712345678', 'Your appointment has been confirmed');

// Get SMS history
$history = $smsHandler->getSMSHistory('0712345678');
*/
?> 