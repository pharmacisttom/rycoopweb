#!/usr/bin/env python3
"""Static source contract for editable navigation copy; run with PHP/React quality gates."""
from pathlib import Path

routes=Path('config/routes.php').read_text()
navbar=Path('src/components/layout/Navbar.jsx').read_text()
service=Path('app/Services/SiteNavigationCopyService.php').read_text()
controller=Path('app/Controllers/Admin/SiteNavigationCopyController.php').read_text()
editor=Path('src/pages/AdminNavigationCopyPage.jsx').read_text()
app=Path('src/App.jsx').read_text()
shell=Path('src/components/admin/AdminControlCenterShell.jsx').read_text()

for route in ('/api/public/navigation-copy','api/admin/navigation-copy','/draft','/publish'):
    assert route in routes, route
for key in ('about_desc','board_desc','staff_desc','finance_desc','dividends_desc','documents_title'):
    assert f"navText('{key}'" in navbar, key
    assert f"'{key}' =>" in service, key
assert 'ชุดที่ 32' not in navbar
assert '5.25% - 5.50%' not in navbar
assert 'มั่นคงสูง</span>' not in navbar
assert "replaceAll('{term}', currentBoardTerm)" in navbar
assert 'requireFreshStepUpMfa' in controller and 'role_slug' in controller
assert "SiteNavigationCopyService::published()" in controller
assert 'site_settings' in service and 'VERSION_CONFLICT' in service
assert 'AuditService::log' in service
assert 'AdminNavigationCopyPage' in app
assert '/admin/navigation-copy' in shell
assert 'body.values = sections' in editor
print('PASS: navigation copy database → API → navbar and admin source contract')
