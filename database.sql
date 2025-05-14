-- Create database
CREATE DATABASE IF NOT EXISTS accesscare_db;
USE accesscare_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(15) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    user_type ENUM('patient', 'doctor') NOT NULL,
    specialization VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Appointments table
CREATE TABLE IF NOT EXISTS appointments (
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
);

-- Doctor specializations table
CREATE TABLE IF NOT EXISTS doctor_specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default doctor specializations
INSERT INTO doctor_specializations (name, description) VALUES
('General Physician', 'General medical practitioner'),
('Specialist', 'Medical specialist'),
('Dentist', 'Dental care specialist'); 