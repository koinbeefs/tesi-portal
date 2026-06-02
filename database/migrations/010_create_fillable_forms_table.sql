-- Create fillable_forms table for storing QF-39 and other form data
-- Migration: 010_create_fillable_forms_table.sql

USE tesi2_portal;

CREATE TABLE IF NOT EXISTS fillable_forms (
    form_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    form_type VARCHAR(50) NOT NULL,
    form_data LONGTEXT NOT NULL,
    file_generated TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_number) REFERENCES applications (queue_number) ON DELETE CASCADE,
    INDEX idx_queue (queue_number),
    INDEX idx_form_type (form_type)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;