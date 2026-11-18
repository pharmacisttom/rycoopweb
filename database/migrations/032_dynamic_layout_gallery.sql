-- Additive MySQL 8 migration. Run only after a verified TEST backup.
CREATE TABLE IF NOT EXISTS dynamic_layout_state (
 id INT UNSIGNED PRIMARY KEY,
 draft_json JSON NOT NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 published_revision_id BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dynamic_layout_revisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 state_json JSON NOT NULL,
 source_version BIGINT UNSIGNED NOT NULL,
 rollback_from BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dynamic_gallery_albums (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(160) NOT NULL,
 payload JSON NOT NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 archived TINYINT(1) NOT NULL DEFAULT 0,
 updated_by BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_dynamic_album_slug(slug),
 KEY idx_dynamic_album_page(archived,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dynamic_gallery_images (
 id CHAR(32) PRIMARY KEY,
 album_id BIGINT UNSIGNED NOT NULL,
 alt VARCHAR(255) NOT NULL,
 caption VARCHAR(1000) NOT NULL DEFAULT '',
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 archived TINYINT(1) NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_dynamic_image_album(album_id,archived,sort_order),
 CONSTRAINT fk_dynamic_image_album FOREIGN KEY(album_id) REFERENCES dynamic_gallery_albums(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dynamic_gallery_publications (
 revision_id BIGINT UNSIGNED NOT NULL,
 album_id BIGINT UNSIGNED NOT NULL,
 slug VARCHAR(160) NOT NULL,
 category_id CHAR(32) NULL,
 payload JSON NOT NULL,
 PRIMARY KEY(revision_id,album_id),
 UNIQUE KEY uq_dynamic_published_slug(revision_id,slug),
 KEY idx_dynamic_published_category(revision_id,category_id,album_id),
 CONSTRAINT fk_dynamic_publication FOREIGN KEY(revision_id) REFERENCES dynamic_layout_revisions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dynamic_gallery_published_images (
 revision_id BIGINT UNSIGNED NOT NULL,
 image_id CHAR(32) NOT NULL,
 PRIMARY KEY(revision_id,image_id),
 CONSTRAINT fk_dynamic_published_image_revision FOREIGN KEY(revision_id) REFERENCES dynamic_layout_revisions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO dynamic_layout_state(id,draft_json) SELECT 1,JSON_OBJECT('sections',JSON_ARRAY(),'categories',JSON_ARRAY(),'initialized',false) WHERE NOT EXISTS(SELECT 1 FROM dynamic_layout_state WHERE id=1);
INSERT INTO permissions(name,module,action,description) SELECT 'layout.view','layout','view','ดูโครงสร้างเว็บไซต์และคลังกิจกรรม' WHERE NOT EXISTS(SELECT 1 FROM permissions WHERE module='layout' AND action='view');
INSERT INTO permissions(name,module,action,description) SELECT 'layout.update','layout','update','แก้ไขร่างโครงสร้างเว็บไซต์และคลังกิจกรรม' WHERE NOT EXISTS(SELECT 1 FROM permissions WHERE module='layout' AND action='update');
INSERT INTO permissions(name,module,action,description) SELECT 'layout.publish','layout','publish','เผยแพร่และย้อนเวอร์ชันโครงสร้างเว็บไซต์' WHERE NOT EXISTS(SELECT 1 FROM permissions WHERE module='layout' AND action='publish');
