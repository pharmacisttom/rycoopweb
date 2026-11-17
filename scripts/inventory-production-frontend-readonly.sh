#!/usr/bin/env bash
# Read-only production FRONTEND baseline inventory.
# Run as coopadmin on the VPS. Never writes to production files, nginx or database.
set -Eeuo pipefail

[[ "$(id -u)" -ne 0 ]] || { echo "STOP: run as ordinary SSH user" >&2; exit 1; }
PROD_CONF="/etc/nginx/sites-enabled/rycoopweb-main-ssl"
TEST_CONF="/etc/nginx/sites-enabled/rycoopweb-test-v2"
[[ -r "$PROD_CONF" || -n "$(sudo test -r "$PROD_CONF" && echo yes)" ]] || { echo "STOP: Production nginx config unavailable" >&2; exit 1; }

OUT="${1:-$HOME/rycoop-frontend-production-baseline.json}"
[[ "$OUT" = "$HOME"/* ]] || { echo "STOP: output must be under HOME" >&2; exit 1; }
[[ ! -e "$OUT" ]] || { echo "STOP: report exists: $OUT" >&2; exit 1; }
CONF_SNAPSHOT="$(mktemp)"
trap 'rm -f "$CONF_SNAPSHOT"' EXIT

sudo cat "$PROD_CONF" > "$CONF_SNAPSHOT"
python3 - "$CONF_SNAPSHOT" "$OUT" <<'PY'
import datetime,hashlib,json,os,pathlib,re,subprocess,sys
conf=pathlib.Path(sys.argv[1]).read_text()
out=pathlib.Path(sys.argv[2])
if 'www.rayongcoop.org' not in conf and 'rayongcoop.org' not in conf:
    raise SystemExit('STOP: nginx config lacks production hostname')
if 'test.rayongcoop.org' in conf and 'server_name www.rayongcoop.org' not in conf:
    raise SystemExit('STOP: nginx config is not verified production')
roots=[x.strip().strip('"\'') for x in re.findall(r'^\s*root\s+([^;]+);',conf,re.M)]
roots=[x for x in roots if x.startswith('/var/www/rycoopweb/') and '..' not in pathlib.PurePosixPath(x).parts]
if not roots:
    raise SystemExit('STOP: no /var/www/rycoopweb production root detected; inspect nginx manually')
# No automatic inference of GitHub branch: live files are source of truth.
dirs=sorted(set(roots))
allowed_names=('src','public','resources','app','components','pages')
observed=[]
for root in dirs:
    p=pathlib.Path(root)
    row={'nginx_root':str(p),'resolved_root':str(p.resolve(strict=False)),'exists':p.is_dir()}
    if p.is_dir():
        for rel in ('index.php','index.html','app/index.html','assets/manifest.json','app/manifest.json'):
            f=p/rel
            if f.is_file() and not f.is_symlink():
                row.setdefault('frontend_files',[]).append({'path':str(f),'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),'bytes':f.stat().st_size})
        release=p.parent
        row['release_parent']=str(release)
        row['release_dir']=str(release.parent)
        for source in (release,release.parent):
            git=source/'.git'
            if git.exists():
                try:
                    sha=subprocess.check_output(['git','-C',str(source),'rev-parse','HEAD'],stderr=subprocess.DEVNULL,timeout=3,text=True).strip()
                    row.setdefault('source_git_revisions',[]).append({'path':str(source),'sha':sha})
                except Exception: pass
        # Inventory only safe file metadata and hashes of frontend build JS/CSS.
        asset_dir=p/'app/assets'
        if asset_dir.is_dir():
            assets=[]
            for f in sorted(asset_dir.iterdir()):
                if f.is_file() and f.suffix in ('.js','.css'):
                    assets.append({'filename':f.name,'bytes':f.stat().st_size,'sha256':hashlib.sha256(f.read_bytes()).hexdigest()})
            row['build_assets_count']=len(assets)
            row['build_assets']=assets[:250]
    observed.append(row)
result={
 'inventory_version':'production-frontend-rc01',
 'collected_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),
 'production_host':'www.rayongcoop.org',
 'nginx_conf_path':'/etc/nginx/sites-enabled/rycoopweb-main-ssl',
 'configured_prod_roots':observed,
 'warning':'This inventories active files, not an inferred GitHub branch. No database or secrets included.',
 'next_action':'Compare deployed SHA/source snapshot to repo before editing frontend. Do not copy TEST UI wholesale.'
}
out.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
out.chmod(0o600)
print('PASS: READ-ONLY PRODUCTION FRONTEND BASELINE INVENTORY')
print('REPORT='+str(out))
for row in observed:
    print('ACTIVE_ROOT='+row['nginx_root'])
    print('RELEASE_PARENT='+row.get('release_parent','unavailable'))
    print('BUILD_ASSETS='+str(row.get('build_assets_count','unavailable')))
    for entry in row.get('source_git_revisions',[]): print('GIT_SHA='+entry['sha'])
print('PRODUCTION UNCHANGED; TEST UNCHANGED')
PY
