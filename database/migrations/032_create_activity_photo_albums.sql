-- Additive, TEST-first. Do not apply automatically to production.
CREATE TABLE IF NOT EXISTS activity_albums (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(255) NOT NULL,
 description TEXT NULL,
 event_date DATE NULL,
 location VARCHAR(255) NULL,
 status ENUM('draft','published') NOT NULL DEFAULT 'draft',
 cover_photo_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 published_by BIGINT UNSIGNED NULL,
 published_at DATETIME NULL,
 deleted_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_activity_published (status,deleted_at,event_date,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_photos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 album_id BIGINT UNSIGNED NOT NULL,
 media_id BIGINT UNSIGNED NULL,
 path VARCHAR(500) NOT NULL,
 caption VARCHAR(500) NULL,
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_activity_photo_path (path),
 INDEX idx_activity_photos_sort (album_id,sort_order,id),
 CONSTRAINT fk_activity_photos_album FOREIGN KEY (album_id) REFERENCES activity_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
