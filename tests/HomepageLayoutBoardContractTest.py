#!/usr/bin/env python3
"""TEST-only static contract checks for board terms and homepage layout."""
from pathlib import Path

routes = Path("config/routes.php").read_text()
homepage = Path("src/pages/HomePage.jsx").read_text()
board = Path("src/pages/BoardPage.jsx").read_text()
editor = Path("src/pages/PublicContentAdminPage.jsx").read_text()
service = Path("app/Services/PublicContentService.php").read_text()
layout = Path("app/Services/HomepageLayoutService.php").read_text()
controller = Path("app/Controllers/Admin/HomepageLayoutController.php").read_text()
admin_page = Path("src/pages/AdminHomepageLayoutPage.jsx").read_text()

for route in ("/api/public/layout", "api/admin/layout", "/draft", "/publish"):
    assert route in routes, route
for key in ("hero", "quick_services", "statistics", "products", "calculator", "welfare"):
    assert f"showSection('{key}')" in homepage, key
    assert f"'{key}' =>" in layout, key
assert "site_settings" in layout and "VERSION_CONFLICT" in controller
assert "requireFreshStepUpMfa" in controller and "role_slug" in controller
assert "setLayout(candidate)" in homepage
assert "เลือกชุดคณะกรรมการ" in board
assert "group.term" in board and "activeGroups" in board
assert "copyBoardToTerm" in editor and "uploadBoardPhoto" in editor
assert "mediaLibraryApi.upload" in editor
assert "person.term===row.term" in editor
assert "if ($term !== null)" in service
assert "memberId" in service
assert "บันทึกร่าง" in admin_page and "อนุมัติเผยแพร่" in admin_page
print("PASS: layout / board source contract checks")
