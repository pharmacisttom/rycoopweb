INSERT INTO roles (name,slug,description)
VALUES ('ผู้ดูแลระบบสมาชิก','member_admin','ดูแลข้อมูลสมาชิกและนำเข้าปันผลเฉพาะระบบสมาชิก')
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description);
