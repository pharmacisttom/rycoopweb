INSERT INTO permissions(name,module,action) VALUES
('ดูข่าว','news','view'),('เพิ่มข่าว','news','create'),('แก้ไขข่าว','news','update'),('ลบข่าว','news','delete'),
('ดูคำถาม','faqs','view'),('เพิ่มคำถาม','faqs','create'),('แก้ไขคำถาม','faqs','update'),('ลบคำถาม','faqs','delete'),
('ดูสมาชิก','members','view'),('แก้ไขสมาชิก','members','update'),
('แก้ไขปันผล','dividends','update'),('นำเข้าปันผล สร้างและอัปเดต','dividends','import')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON
(p.module='members' AND p.action IN ('view','update')) OR
(p.module='dividends' AND p.action IN ('update','import'))
WHERE r.slug='member_admin';
