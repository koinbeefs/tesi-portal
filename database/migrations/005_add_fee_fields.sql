-- Add application fee and receipt fields to applications table
-- Migration: 005_add_fee_fields.sql
-- Created: March 2, 2026

USE tesi2_portal;

-- Add application_fee column
ALTER TABLE applications 
ADD COLUMN application_fee DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Application fee amount based on applicant type';

-- Add receipt_path column for storing official receipt file path
ALTER TABLE applications 
ADD COLUMN receipt_path VARCHAR(500) NULL COMMENT 'Path to uploaded official receipt file';

-- Update applicant_type enum to include new values
ALTER TABLE applications 
MODIFY COLUMN applicant_type ENUM('student', 'graduate_student', 'faculty_researcher', 'faculty', 'researcher') NOT NULL COMMENT 'Applicant type with fee structure';

-- Add indexes for performance
ALTER TABLE applications 
ADD INDEX idx_application_fee (application_fee),
ADD INDEX idx_receipt_path (receipt_path);
