-- Add new fields to applications table for updated form structure
-- Migration: 008_add_new_form_fields.sql
-- Created: March 2, 2026

USE tesi2_portal;

-- Add researchers_name field
ALTER TABLE applications 
ADD COLUMN researchers_name VARCHAR(255) NULL COMMENT 'Name of researcher(s) involved in the study';

-- Add contact_number field
ALTER TABLE applications 
ADD COLUMN contact_number VARCHAR(50) NULL COMMENT 'Contact number for communication';

-- Add research_type field
ALTER TABLE applications 
ADD COLUMN research_type VARCHAR(255) NULL COMMENT 'Type of research (social, technical, other)';

-- Add other_type field
ALTER TABLE applications 
ADD COLUMN other_type VARCHAR(100) NULL COMMENT 'Other research type specification';

-- Add indexes for new fields
ALTER TABLE applications 
ADD INDEX idx_researchers_name (researchers_name),
ADD INDEX idx_contact_number (contact_number),
ADD INDEX idx_research_type (research_type);
