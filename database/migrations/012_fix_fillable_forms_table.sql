USE tesi2_portal;

DROP TABLE IF EXISTS fillable_forms;

CREATE TABLE fillable_forms (
    form_id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(20) NOT NULL,
    form_type VARCHAR(50) NOT NULL,
    form_data LONGTEXT NOT NULL,
    file_generated TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_queue (queue_number),
    INDEX idx_form_type (form_type)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;