#!/usr/bin/env python3
"""Static regression checks for the Public Content sidebar deep link."""
from pathlib import Path
import re

shell = Path("src/components/admin/AdminControlCenterShell.jsx").read_text()
page = Path("src/pages/PublicContentAdminPage.jsx").read_text()
router = Path("src/App.jsx").read_text()

assert "to: '/admin/public-content?section=products'" in shell
assert 'path="/admin/public-content"' in router
assert 'useSearchParams' in page
assert "searchParams.get('section')" in page
assert "available.some(item => item.key === requestedSection)" in page
assert "setSection(requestedSection)" in page
assert "const nextVal = e.target.value;" in page
assert "setSection(nextVal)" in page
assert "next.set('section',nextVal)" in page
assert "if (!sections.length || !requestedSection || requestedSection === section) return;" in page
assert "if (dirty && section)" in page
assert "current.set('section', section)" in page
assert "setValue(null)" in page
assert "หมวดข้อมูลที่ระบุใน URL ไม่สามารถเข้าถึงได้" in page
assert re.search(r"memberAdminApi\('/api/admin/public-content/sections'",page)
print("PASS: public content sidebar route / section query contract")
