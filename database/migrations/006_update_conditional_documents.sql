-- Update required documents to make official receipt conditional
-- Migration: 006_update_conditional_documents.sql
-- Created: March 2, 2026

USE tesi2_portal;

-- Update official_receipt to be conditional based on applicant type
UPDATE required_documents 
SET is_conditional = 1, 
    conditional_field = 'applicant_type',
    description = 'Clear scanned or screenshot copy of the receipt of payment (required for Graduate Students and Faculty/Researchers only)'
WHERE document_type = 'official_receipt';

-- Insert condition logic for document requirements
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('fee_student', '0', 'Application fee for students'),
('fee_graduate_student', '500', 'Application fee for graduate students'),
('fee_faculty_researcher', '1000', 'Application fee for faculty and researchers'),
('receipt_required_types', 'graduate_student,faculty_researcher', 'Applicant types that require receipt upload');
