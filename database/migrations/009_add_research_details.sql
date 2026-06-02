-- Add research details to applications table
-- Migration: 009_add_research_details.sql
-- Created: March 2, 2026

USE tesi2_portal;

ALTER TABLE applications 
ADD COLUMN college VARCHAR(255) NULL AFTER other_type,
ADD COLUMN program_course VARCHAR(255) NULL AFTER college,
ADD COLUMN research_date_started DATE NULL AFTER program_course,
ADD COLUMN research_date_finished DATE NULL AFTER research_date_started;

-- Add indexes for performance
ALTER TABLE applications 
ADD INDEX idx_college (college),
ADD INDEX idx_program_course (program_course);
