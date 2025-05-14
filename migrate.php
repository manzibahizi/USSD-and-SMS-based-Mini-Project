<?php
// Database migration script
$db = new mysqli('localhost', 'root', '', 'accesscare_db');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Create migrations table if it doesn't exist
$db->query("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Get list of executed migrations
$executedMigrations = [];
$result = $db->query("SELECT migration_name FROM migrations");
while ($row = $result->fetch_assoc()) {
    $executedMigrations[] = $row['migration_name'];
}

// Define migrations
$migrations = [
    '001_create_users_table' => "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(15) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            user_type ENUM('patient', 'doctor') NOT NULL,
            specialization VARCHAR(100),
            availability ENUM('available', 'not_available') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    
    '002_create_appointments_table' => "
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
        )
    ",
    
    '003_create_doctor_specializations_table' => "
        CREATE TABLE IF NOT EXISTS doctor_specializations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    
    '004_insert_default_specializations' => "
        INSERT INTO doctor_specializations (name, description) VALUES
        ('General Physician', 'General medical practitioner'),
        ('Specialist', 'Medical specialist'),
        ('Dentist', 'Dental care specialist')
    ",
    
    '005_add_indexes' => "
        CREATE INDEX idx_phone_number ON users(phone_number);
        CREATE INDEX idx_appointment_date ON appointments(appointment_date);
        CREATE INDEX idx_doctor_category ON appointments(doctor_category);
        CREATE INDEX idx_status ON appointments(status);
    "
];

// Execute pending migrations
foreach ($migrations as $migrationName => $sql) {
    if (!in_array($migrationName, $executedMigrations)) {
        echo "Executing migration: $migrationName\n";
        
        // Start transaction
        $db->begin_transaction();
        
        try {
            // Execute migration
            if ($db->multi_query($sql)) {
                do {
                    // Store result
                    if ($result = $db->store_result()) {
                        $result->free();
                    }
                } while ($db->more_results() && $db->next_result());
            }
            
            // Record migration
            $stmt = $db->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
            $stmt->bind_param("s", $migrationName);
            $stmt->execute();
            
            // Commit transaction
            $db->commit();
            echo "Migration completed successfully\n";
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            echo "Migration failed: " . $e->getMessage() . "\n";
        }
    }
}

echo "All migrations completed\n";
$db->close();
?> 