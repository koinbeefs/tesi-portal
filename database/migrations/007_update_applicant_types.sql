-- Update applicant types to separate Faculty Researchers
-- Migration: 007_update_applicant_types.sql
-- Created: March 2, 2026

USE tesi2_portal;

-- Update applicant_type enum to include new faculty researcher classifications
ALTER TABLE applications 
MODIFY COLUMN applicant_type ENUM('student', 'graduate_student', 'faculty_university', 'faculty_external', 'faculty', 'researcher', 'faculty_researcher') NOT NULL COMMENT 'Applicant type with fee structure';

-- Update system settings for new fee structure
UPDATE system_settings 
SET setting_value = '0,500,0,1000' 
WHERE setting_key = 'receipt_required_types';

-- Add new system settings for faculty researcher classifications
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('fee_student', '0', 'Application fee for students'),
('fee_graduate_student', '500', 'Application fee for graduate students'),
('fee_faculty_university', '0', 'Application fee for university funded faculty researchers'),
('fee_faculty_external', '1000', 'Application fee for externally funded faculty researchers'),
('receipt_required_types', 'graduate_student,faculty_external', 'Applicant types that require receipt upload');
