"""Static tests for the production-only read-only baseline inventory.
No Production connections or files are touched by this test.
"""
from pathlib import Path

source=Path('scripts/inventory-production-frontend-readonly.sh').read_text()
assert "<<'PY'\n" in source, "Missing embedded inventory program"
python_source=source.split("<<'PY'\n",1)[1].split("\nPY\n",1)[0]
compile(python_source,'embedded-production-frontend-inventory.py','exec')
assert '/etc/nginx/sites-enabled/rycoopweb-main-ssl' in source
assert 'nginx_root' in python_source and 'sha256' in python_source
assert 'PRODUCTION UNCHANGED; TEST UNCHANGED' in source
for forbidden in ['systemctl reload','rsync ','mysql -','sed -i','git reset','git checkout','chmod -R']:
    assert forbidden not in source, f'Production inventory contains forbidden mutation: {forbidden}'
print('PASS: production frontend baseline tool is syntactically valid and read-only by design')
