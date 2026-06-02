-- Add similarity_index and test_count columns to applications table
-- Migration: 011_add_similarity_index_column.sql

USE tesi2_portal;

-- Add similarity_index column for storing similarity score percentage
ALTER TABLE applications 
ADD COLUMN similarity_index DECIMAL(5,2) NULL COMMENT 'Similarity index score percentage (0-100)' AFTER current_status;

-- Add test_count column for tracking number of similarity tests performed
ALTER TABLE applications 
ADD COLUMN test_count INT DEFAULT 0 COMMENT 'Number of similarity tests performed' AFTER similarity_index;

-- Add index for similarity_index for performance
ALTER TABLE applications 
ADD INDEX idx_similarity_index (similarity_index);
