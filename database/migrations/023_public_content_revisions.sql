-- Additive only. Run on a disposable database / approved TEST deployment first.
CREATE TABLE IF NOT EXISTS public_content_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(40) NOT NULL,
    payload JSON NOT NULL,
    previous_payload JSON NOT NULL,
    base_version CHAR(64) NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    effective_on DATE NOT NULL,
    expires_on DATE DEFAULT NULL,
    note VARCHAR(1000) NOT NULL,
    state ENUM('draft','published','rejected') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    published_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME DEFAULT NULL,
    INDEX idx_public_content_section (section,state,effective_on,id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (published_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(name,module,action) VALUES
('อนุมัติเผยแพร่ข่าว','news','publish'),
('ดูข้อมูลองค์กร','coop_info','view'),('แก้ร่างข้อมูลองค์กร','coop_info','update'),('เผยแพร่ข้อมูลองค์กร','coop_info','publish'),
('ดูข้อมูลบุคลากร','people','view'),('แก้ร่างข้อมูลบุคลากร','people','update'),('เผยแพร่ข้อมูลบุคลากร','people','publish'),
('ดูอัตราดอกเบี้ย','rates','view'),('แก้ร่างอัตราดอกเบี้ย','rates','update'),('เผยแพร่อัตราดอกเบี้ย','rates','publish'),
('ดูผลิตภัณฑ์','products','view'),('แก้ร่างผลิตภัณฑ์','products','update'),('เผยแพร่ผลิตภัณฑ์','products','publish')
ON DUPLICATE KEY UPDATE name=VALUES(name);
