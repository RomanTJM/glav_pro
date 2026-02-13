SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- CRM Stages Schema
-- Designed for scalability: proper indexes, event sourcing pattern, minimal locking

CREATE TABLE IF NOT EXISTS companies (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    stage       VARCHAR(20) NOT NULL DEFAULT 'C0',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_stage (stage),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS company_events (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id  INT UNSIGNED NOT NULL,
    event_type  VARCHAR(50) NOT NULL,
    event_data  JSON DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_company_id (company_id),
    INDEX idx_company_type (company_id, event_type),
    INDEX idx_created (created_at),

    CONSTRAINT fk_events_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stage_transitions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id      INT UNSIGNED NOT NULL,
    from_stage      VARCHAR(20) NOT NULL,
    to_stage        VARCHAR(20) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_company_id (company_id),
    INDEX idx_created (created_at),

    CONSTRAINT fk_transitions_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
