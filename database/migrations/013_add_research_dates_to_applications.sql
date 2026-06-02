-- Add research date columns to applications table
-- Migration: 013_add_research_dates_to_applications.sql
-- Created: May 13, 2026

USE tesi2_portal;

ALTER TABLE applications 
ADD COLUMN research_date_started DATE NULL AFTER program_course,
ADD COLUMN research_date_finished DATE NULL AFTER research_date_started;

-- Add indexes for performance
ALTER TABLE applications 
ADD INDEX idx_research_date_started (research_date_started),
ADD INDEX idx_research_date_finished (research_date_finished);
