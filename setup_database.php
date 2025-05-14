<?php
// Database setup script
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'accesscare_db';

// Create connection
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully\n";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully\n";
} else {
    echo "Error creating database: " . $conn->error . "\n";
}

// Select database
$conn->select_db($dbname);

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(15) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        user_type ENUM('patient', 'doctor') NOT NULL,
        specialization VARCHAR(100),
        availability ENUM('available', 'not_available') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_phone VARCHAR(15) NOT NULL,
        doctor_category VARCHAR(50) NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        doctor_phone VARCHAR(15),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_phone) REFERENCES users(phone_number),
        FOREIGN KEY (doctor_phone) REFERENCES users(phone_number)
    )",
    
    "CREATE TABLE IF NOT EXISTS doctor_specializations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(15) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(50) NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

// Create tables
foreach ($tables as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table created successfully\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
}

// Create indexes
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_phone_number ON users(phone_number)",
    "CREATE INDEX IF NOT EXISTS idx_appointment_date ON appointments(appointment_date)",
    "CREATE INDEX IF NOT EXISTS idx_doctor_category ON appointments(doctor_category)",
    "CREATE INDEX IF NOT EXISTS idx_status ON appointments(status)",
    "CREATE INDEX IF NOT EXISTS idx_sms_phone ON sms_logs(phone_number)"
];

// Create indexes
foreach ($indexes as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Index created successfully\n";
    } else {
        echo "Error creating index: " . $conn->error . "\n";
    }
}

// Insert default specializations
$specializations = [
    ['General Physician', 'General medical practitioner'],
    ['Specialist', 'Medical specialist'],
    ['Dentist', 'Dental care specialist']
];

$stmt = $conn->prepare("INSERT IGNORE INTO doctor_specializations (name, description) VALUES (?, ?)");
foreach ($specializations as $spec) {
    $stmt->bind_param("ss", $spec[0], $spec[1]);
    if ($stmt->execute()) {
        echo "Specialization added: " . $spec[0] . "\n";
    } else {
        echo "Error adding specialization: " . $stmt->error . "\n";
    }
}

echo "Database setup completed successfully\n";
$conn->close();
?> 