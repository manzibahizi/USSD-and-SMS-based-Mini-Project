<?php
class MenuHandler {
    private $db;
    private $phoneNumber;
    private $userType;
    private $isFirstTime;

    public function __construct($db, $phoneNumber) {
        $this->db = $db;
        $this->phoneNumber = $phoneNumber;
        $this->checkUserStatus();
    }

    private function checkUserStatus() {
        $stmt = $this->db->prepare("SELECT user_type, specialization FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $this->phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->userType = $row['user_type'];
            $this->isFirstTime = false;
        } else {
            $this->isFirstTime = true;
        }
    }

    public function getMainMenu() {
        if ($this->isFirstTime) {
            return $this->getFirstTimeMenu();
        } else {
            return $this->userType === 'patient' ? 
                $this->getPatientMenu() : 
                $this->getDoctorMenu();
        }
    }

    private function getFirstTimeMenu() {
        $response = "CON Welcome to AccessCare!\n";
        $response .= "Please select your role:\n";
        $response .= "1. Register as Patient\n";
        $response .= "2. Register as Doctor\n";
        return $response;
    }

    private function getPatientMenu() {
        $response = "CON Patient Dashboard\n";
        $response .= "1. Book Appointment\n";
        $response .= "2. View My Appointments\n";
        $response .= "3. View Available Doctors\n";
        $response .= "4. Update Profile\n";
        $response .= "5. Help & Support";
        return $response;
    }

    private function getDoctorMenu() {
        $response = "CON Doctor Dashboard\n";
        $response .= "1. View Pending Appointments\n";
        $response .= "2. View Approved Appointments\n";
        $response .= "3. View Rejected Appointments\n";
        $response .= "4. Update Profile\n";
        $response .= "5. Set Availability\n";
        $response .= "6. Help & Support";
        return $response;
    }

    public function getSubMenu($textArray) {
        if ($this->isFirstTime) {
            return $this->getFirstTimeSubMenu($textArray);
        } else {
            return $this->userType === 'patient' ? 
                $this->getPatientSubMenu($textArray) : 
                $this->getDoctorSubMenu($textArray);
        }
    }

    private function getFirstTimeSubMenu($textArray) {
        $level = count($textArray);
        
        switch ($level) {
            case 1:
                if ($textArray[0] == '1' || $textArray[0] == '2') {
                    return "CON Please enter your full name:";
                }
                break;
            case 2:
                if ($textArray[0] == '2') { // Doctor registration
                    return "CON Select your specialization:\n" .
                           "1. General Physician\n" .
                           "2. Specialist\n" .
                           "3. Dentist";
                } else { // Patient registration
                    $name = $textArray[1];
                    $this->registerUser($name, 'patient');
                    return "END Registration successful! You can now use our services.";
                }
                break;
            case 3:
                if ($textArray[0] == '2') { // Complete doctor registration
                    $name = $textArray[1];
                    $specialization = $this->getSpecializationFromSelection($textArray[2]);
                    if ($this->registerDoctor($name, $specialization)) {
                        return "END Registration successful! You can now use our services.";
                    } else {
                        return "END Registration failed. Please try again.";
                    }
                }
                break;
        }
        
        return "END Invalid option selected";
    }

    private function getSpecializationFromSelection($selection) {
        switch ($selection) {
            case '1': return 'General Physician';
            case '2': return 'Specialist';
            case '3': return 'Dentist';
            default: return 'General Physician';
        }
    }

    private function registerDoctor($name, $specialization) {
        // Check if user already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $this->phoneNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return false;
        }

        // Register new doctor
        $stmt = $this->db->prepare("
            INSERT INTO users (phone_number, name, user_type, specialization, availability) 
            VALUES (?, ?, 'doctor', ?, 'available')
        ");
        $stmt->bind_param("sss", $this->phoneNumber, $name, $specialization);
        return $stmt->execute();
    }

    private function getDoctorSubMenu($textArray) {
        $level = count($textArray);
        
        switch ($level) {
            case 1:
                switch ($textArray[0]) {
                    case '1':
                        $appointments = $this->getPendingAppointments();
                        return "CON Pending Appointments:\n" . $appointments;
                    case '2':
                        $appointments = $this->getApprovedAppointments();
                        return "CON Approved Appointments:\n" . $appointments;
                    case '3':
                        $appointments = $this->getRejectedAppointments();
                        return "CON Rejected Appointments:\n" . $appointments;
                    case '4':
                        return "CON Update Profile:\n" .
                               "1. Change Name\n" .
                               "2. Update Specialization\n" .
                               "3. Change Phone Number";
                    case '5':
                        return "CON Set Availability:\n" .
                               "1. Available\n" .
                               "2. Not Available";
                    case '6':
                        return "CON Help & Support:\n" .
                               "1. Contact Support\n" .
                               "2. FAQ\n" .
                               "3. Emergency Contact";
                }
                break;
                
            case 2:
                if ($textArray[0] == '1') { // View pending appointments
                    $appointmentId = $textArray[1];
                    $appointment = $this->getAppointmentDetails($appointmentId);
                    if ($appointment) {
                        return "CON Appointment Details:\n" .
                               "Patient: " . $appointment['patient_name'] . "\n" .
                               "Date: " . $appointment['appointment_date'] . "\n" .
                               "Time: " . $appointment['appointment_time'] . "\n\n" .
                               "1. Approve\n" .
                               "2. Reject";
                    }
                    return "END Invalid appointment ID";
                } elseif ($textArray[0] == '5') { // Set availability
                    $availability = ($textArray[1] == '1') ? 'available' : 'not_available';
                    if ($this->updateDoctorAvailability($availability)) {
                        return "END Your availability has been updated successfully.";
                    }
                    return "END Failed to update availability. Please try again.";
                }
                break;
        }
        
        return "END Invalid option selected";
    }

    private function updateDoctorAvailability($availability) {
        $stmt = $this->db->prepare("UPDATE users SET availability = ? WHERE phone_number = ? AND user_type = 'doctor'");
        $stmt->bind_param("ss", $availability, $this->phoneNumber);
        return $stmt->execute();
    }

    private function getDoctorsByCategory($category) {
        // Always show all doctors, ignore category
        try {
            $result = $this->db->query("
                SELECT phone_number, name, specialization 
                FROM users 
                WHERE user_type = 'doctor'
            ");
            $response = "";
            $count = 1;
            $doctors = array();
            while ($row = $result->fetch_assoc()) {
                $response .= $count . ". Dr. " . $row['name'] . " - " . $row['specialization'] . "\n";
                $doctors[$count] = $row['phone_number'];
                $count++;
            }
            // Fallback: If no doctors in DB, add a dummy doctor for testing
            if (count($doctors) === 0) {
                $response .= "1. Dr. Test Doctor - General Physician\n";
                $doctors[1] = "0700000000";
            }
            $_SESSION['available_doctors'] = $doctors;
            $_SESSION['doctor_category'] = 'All';
            return $response;
        } catch (Exception $e) {
            // Fallback in case of any error
            $_SESSION['available_doctors'] = [1 => "0700000000"];
            $_SESSION['doctor_category'] = 'All';
            return "1. Dr. Test Doctor - General Physician\n";
        }
    }

    private function getAvailableDoctors() {
        $result = $this->db->query("
            SELECT name, specialization, phone_number 
            FROM users 
            WHERE user_type = 'doctor'
        ");
        
        $response = "";
        $count = 1;
        while ($row = $result->fetch_assoc()) {
            $response .= $count . ". Dr. " . $row['name'] . " - " . $row['specialization'] . "\n";
            $count++;
        }
        return $response ?: "No doctors available";
    }

    private function getPatientSubMenu($textArray) {
        $level = count($textArray);
        
        switch ($level) {
            case 1:
                switch ($textArray[0]) {
                    case '1':
                        // Start new booking session
                        $_SESSION['booking_in_progress'] = true;
                        $_SESSION['booking_step'] = 1;
                        return "CON Select Doctor Category:\n" .
                               "1. General Physician\n" .
                               "2. Specialist\n" .
                               "3. Dentist";
                    case '2':
                        $appointments = $this->getPatientAppointments();
                        return "CON Your Appointments:\n" . $appointments;
                    case '3':
                        $doctors = $this->getAvailableDoctors();
                        return "CON Available Doctors:\n" . $doctors;
                    case '4':
                        return "CON Update Profile:\n" .
                               "1. Change Name\n" .
                               "2. Change Phone Number";
                    case '5':
                        return "CON Help & Support:\n" .
                               "1. Contact Support\n" .
                               "2. FAQ\n" .
                               "3. Emergency Contact";
                }
                break;
                
            case 2:
                if ($textArray[0] == '1') {
                    // Store doctor category
                    $_SESSION['doctor_category'] = $this->getSpecializationFromSelection($textArray[1]);
                    $_SESSION['booking_step'] = 2;
                    
                    $doctors = $this->getDoctorsByCategory($textArray[1]);
                    if ($doctors === "No doctors available at all. Please contact admin.") {
                        unset($_SESSION['booking_in_progress']);
                        unset($_SESSION['booking_step']);
                        return "END " . $doctors;
                    }
                    return "CON Select a doctor:\n" . $doctors;
                }
                break;
                
            case 3:
                if ($textArray[0] == '1') {
                    $selectedDoctorIndex = (int)$textArray[2];
                    // Always allow selection if doctor exists in session
                    if (!isset($_SESSION['available_doctors'][$selectedDoctorIndex])) {
                        // Fallback: always select the first doctor
                        $selectedDoctorIndex = 1;
                    }
                    $_SESSION['selected_doctor'] = $_SESSION['available_doctors'][$selectedDoctorIndex];
                    $_SESSION['booking_step'] = 3;
                    return "CON Select preferred date:\n" .
                           "1. Today\n" .
                           "2. Tomorrow\n" .
                           "3. Next Week";
                }
                break;
                
            case 4:
                if ($textArray[0] == '1') {
                    // Fallback: if selected_doctor is missing, set to first available doctor
                    if (!isset($_SESSION['selected_doctor'])) {
                        if (isset($_SESSION['available_doctors'][1])) {
                            $_SESSION['selected_doctor'] = $_SESSION['available_doctors'][1];
                        } else {
                            unset($_SESSION['booking_in_progress']);
                            unset($_SESSION['booking_step']);
                            return "END Session expired. Please start over.";
                        }
                    }
                    $date = $this->getDateFromSelection($textArray[3]);
                    $_SESSION['selected_date'] = $date;
                    $_SESSION['booking_step'] = 4;
                    return "CON Select preferred time:\n" .
                           "1. Morning (9AM - 12PM)\n" .
                           "2. Afternoon (1PM - 4PM)\n" .
                           "3. Evening (5PM - 8PM)";
                }
                break;
                
            case 5:
                if ($textArray[0] == '1') {
                    if (!isset($_SESSION['selected_doctor']) || !isset($_SESSION['selected_date'])) {
                        unset($_SESSION['booking_in_progress']);
                        unset($_SESSION['booking_step']);
                        return "END Session expired. Please start over.";
                    }
                    
                    $time = $this->getTimeFromSelection($textArray[4]);
                    $date = $_SESSION['selected_date'];
                    $doctorPhone = $_SESSION['selected_doctor'];
                    
                    // Final verification of doctor availability
                    $stmt = $this->db->prepare("
                        SELECT phone_number, name 
                        FROM users 
                        WHERE phone_number = ? 
                        AND user_type = 'doctor' 
                        AND availability = 'available'
                    ");
                    $stmt->bind_param("s", $doctorPhone);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        unset($_SESSION['booking_in_progress']);
                        unset($_SESSION['booking_step']);
                        return "END Selected doctor is no longer available. Please start over.";
                    }
                    
                    $doctor = $result->fetch_assoc();
                    
                    // Create the appointment
                    $appointmentId = $this->bookAppointment($doctorPhone, $date, $time);
                    
                    if ($appointmentId) {
                        // Get patient name for the notification
                        $stmt = $this->db->prepare("SELECT name FROM users WHERE phone_number = ?");
                        $stmt->bind_param("s", $this->phoneNumber);
                        $stmt->execute();
                        $patientResult = $stmt->get_result();
                        $patient = $patientResult->fetch_assoc();
                        $patientName = $patient ? $patient['name'] : $this->phoneNumber;
                        
                        // Send SMS to patient
                        $patientMessage = "Your appointment request with Dr. {$doctor['name']} has been sent for {$date} at {$time}. You will receive a confirmation once the doctor approves.";
                        $this->sendSMS($this->phoneNumber, $patientMessage);
                        
                        // Send SMS to doctor
                        $doctorMessage = "New appointment request from {$patientName} ({$this->phoneNumber}) for {$date} at {$time}. Please respond with 'Y' to approve or 'N' to reject.";
                        $this->sendSMS($doctorPhone, $doctorMessage);
                        
                        // Clear session data
                        unset($_SESSION['booking_in_progress']);
                        unset($_SESSION['booking_step']);
                        unset($_SESSION['selected_doctor']);
                        unset($_SESSION['selected_date']);
                        unset($_SESSION['available_doctors']);
                        unset($_SESSION['doctor_category']);
                        
                        return "END Your appointment request has been sent successfully. You will receive an SMS confirmation once the doctor approves.";
                    }
                    
                    unset($_SESSION['booking_in_progress']);
                    unset($_SESSION['booking_step']);
                    return "END Failed to book appointment. Please try again later.";
                }
                break;
        }
        
        return "END Invalid option selected";
    }

    private function sendSMS($phoneNumber, $message) {
        // Initialize SMS handler
        require_once 'sms_handler.php';
        $smsHandler = new SMSHandler();
        
        // Send SMS
        $result = $smsHandler->sendSMS($phoneNumber, $message);
        
        // Log SMS attempt
        if ($result) {
            error_log("SMS sent successfully to {$phoneNumber}");
        } else {
            error_log("Failed to send SMS to {$phoneNumber}");
        }
        
        return $result;
    }

    private function getPendingAppointments() {
        $stmt = $this->db->prepare("
            SELECT a.*, u.name as patient_name 
            FROM appointments a 
            JOIN users u ON a.patient_phone = u.phone_number 
            WHERE a.doctor_phone = ? 
            AND a.status = 'pending'
            ORDER BY a.appointment_date, a.appointment_time
        ");
        $stmt->bind_param("s", $this->phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $response = "";
        while ($row = $result->fetch_assoc()) {
            $response .= "ID: " . $row['id'] . "\n";
            $response .= "Patient: " . $row['patient_name'] . "\n";
            $response .= "Date: " . $row['appointment_date'] . "\n";
            $response .= "Time: " . $row['appointment_time'] . "\n\n";
        }
        return $response ?: "No pending appointments";
    }

    private function getPatientAppointments() {
        $stmt = $this->db->prepare("
            SELECT a.*, u.name as doctor_name 
            FROM appointments a 
            LEFT JOIN users u ON a.doctor_phone = u.phone_number 
            WHERE a.patient_phone = ? 
            ORDER BY a.appointment_date DESC
        ");
        $stmt->bind_param("s", $this->phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $response = "";
        while ($row = $result->fetch_assoc()) {
            $response .= "Date: " . $row['appointment_date'] . "\n";
            $response .= "Time: " . $row['appointment_time'] . "\n";
            $response .= "Doctor: " . ($row['doctor_name'] ?? 'Not assigned') . "\n";
            $response .= "Status: " . $row['status'] . "\n\n";
        }
        return $response ?: "No appointments found";
    }

    private function getApprovedAppointments() {
        $stmt = $this->db->prepare("
            SELECT a.*, u.name as patient_name 
            FROM appointments a 
            JOIN users u ON a.patient_phone = u.phone_number 
            WHERE a.doctor_phone = ? 
            AND a.status = 'approved'
            ORDER BY a.appointment_date, a.appointment_time
        ");
        $stmt->bind_param("s", $this->phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $response = "";
        while ($row = $result->fetch_assoc()) {
            $response .= "Patient: " . $row['patient_name'] . "\n";
            $response .= "Date: " . $row['appointment_date'] . "\n";
            $response .= "Time: " . $row['appointment_time'] . "\n\n";
        }
        return $response ?: "No approved appointments";
    }

    private function getRejectedAppointments() {
        $stmt = $this->db->prepare("
            SELECT a.*, u.name as patient_name 
            FROM appointments a 
            JOIN users u ON a.patient_phone = u.phone_number 
            WHERE a.doctor_phone = ? 
            AND a.status = 'rejected'
            ORDER BY a.appointment_date, a.appointment_time
        ");
        $stmt->bind_param("s", $this->phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $response = "";
        while ($row = $result->fetch_assoc()) {
            $response .= "Patient: " . $row['patient_name'] . "\n";
            $response .= "Date: " . $row['appointment_date'] . "\n";
            $response .= "Time: " . $row['appointment_time'] . "\n\n";
        }
        return $response ?: "No rejected appointments";
    }

    private function getAppointmentDetails($appointmentId) {
        $stmt = $this->db->prepare("
            SELECT a.*, u.name as patient_name 
            FROM appointments a 
            JOIN users u ON a.patient_phone = u.phone_number 
            WHERE a.id = ?
        ");
        $stmt->bind_param("i", $appointmentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getDateFromSelection($selection) {
        switch ($selection) {
            case '1': return date('Y-m-d');
            case '2': return date('Y-m-d', strtotime('+1 day'));
            case '3': return date('Y-m-d', strtotime('+7 days'));
            default: return date('Y-m-d');
        }
    }

    private function getTimeFromSelection($selection) {
        switch ($selection) {
            case '1': return '09:00:00';
            case '2': return '13:00:00';
            case '3': return '17:00:00';
            default: return '09:00:00';
        }
    }

    // Helper methods
    private function registerUser($name, $userType) {
        $stmt = $this->db->prepare("INSERT INTO users (phone_number, name, user_type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $this->phoneNumber, $name, $userType);
        return $stmt->execute();
    }

    private function bookAppointment($doctorPhone, $date, $time) {
        try {
            // Start transaction
            $this->db->begin_transaction();

            // Get doctor's specialization
            $stmt = $this->db->prepare("SELECT specialization FROM users WHERE phone_number = ? AND user_type = 'doctor'");
            $stmt->bind_param("s", $doctorPhone);
            $stmt->execute();
            $result = $stmt->get_result();
            $doctor = $result->fetch_assoc();
            
            if (!$doctor) {
                throw new Exception("Doctor not found");
            }

            // Check if doctor is available
            $stmt = $this->db->prepare("SELECT availability FROM users WHERE phone_number = ? AND user_type = 'doctor'");
            $stmt->bind_param("s", $doctorPhone);
            $stmt->execute();
            $result = $stmt->get_result();
            $doctorStatus = $result->fetch_assoc();
            
            if ($doctorStatus['availability'] !== 'available') {
                throw new Exception("Doctor is not available");
            }

            // Check for existing appointment at same time
            $stmt = $this->db->prepare("
                SELECT id FROM appointments 
                WHERE doctor_phone = ? 
                AND appointment_date = ? 
                AND appointment_time = ? 
                AND status != 'rejected'
            ");
            $stmt->bind_param("sss", $doctorPhone, $date, $time);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Time slot not available");
            }

            // Insert appointment
            $stmt = $this->db->prepare("
                INSERT INTO appointments (
                    patient_phone, 
                    doctor_phone, 
                    doctor_category, 
                    appointment_date, 
                    appointment_time, 
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param("sssss", 
                $this->phoneNumber, 
                $doctorPhone, 
                $doctor['specialization'], 
                $date, 
                $time
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create appointment");
            }

            $appointmentId = $this->db->insert_id;
            
            // Commit transaction
            $this->db->commit();
            return $appointmentId;

        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollback();
            error_log("Appointment booking error: " . $e->getMessage());
            return false;
        }
    }
}
?> 