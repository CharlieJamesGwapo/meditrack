-- Medical Records Table
-- Stores doctor's notes, prescriptions, and lab orders for each patient visit

CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT NULL,
    chief_complaint TEXT NULL,
    symptoms TEXT NULL,
    diagnosis TEXT NULL,
    prescription TEXT NULL,
    lab_tests TEXT NULL,
    vital_signs JSON NULL COMMENT 'BP, temp, pulse, weight',
    notes TEXT NULL,
    follow_up_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patient_id (patient_id),
    INDEX idx_doctor_id (doctor_id),
    INDEX idx_appointment_id (appointment_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample medical records for testing
INSERT INTO medical_records (patient_id, doctor_id, appointment_id, chief_complaint, symptoms, diagnosis, prescription, lab_tests, vital_signs, notes, follow_up_date) VALUES
(1, 1, NULL, 'Headache and fever', 'Persistent headache for 3 days, fever 38.5°C', 'Viral infection', 'Paracetamol 500mg 3x daily for 5 days', 'Complete Blood Count (CBC)', '{"blood_pressure": "120/80", "temperature": "38.5", "pulse": "82", "weight": "65"}', 'Patient advised to rest and drink plenty of fluids', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
(2, 1, NULL, 'Cough and cold', 'Dry cough, runny nose for 2 days', 'Upper respiratory tract infection', 'Amoxicillin 500mg 3x daily for 7 days, Cough syrup 10ml 3x daily', NULL, '{"blood_pressure": "118/75", "temperature": "37.2", "pulse": "78", "weight": "58"}', 'Avoid cold drinks, use humidifier', DATE_ADD(CURDATE(), INTERVAL 5 DAY));
