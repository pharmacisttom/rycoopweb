"""Read-only HTTP checks plus upload previews; never confirms or edits live data.

Set MEMBER_ADMIN_USER and MEMBER_ADMIN_PASSWORD, then run against the local app.
"""
import csv
import http.cookiejar
import io
import json
import os
import re
import urllib.error
import urllib.request

BASE = os.environ.get('MEMBER_TEST_URL', 'http://127.0.0.1:3000')
client = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(path, data=None, token=None, raw=False, content_type=None):
    headers = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
    if token:
        headers['X-CSRF-TOKEN'] = token
    if data is not None:
        headers['Content-Type'] = content_type or 'application/json'
        if not raw:
            data = json.dumps(data).encode()
    try:
        result = client.open(urllib.request.Request(BASE + path, data=data, headers=headers), timeout=30)
        body = result.read()
        try:
            return result.status, json.loads(body)
        except json.JSONDecodeError:
            diagnostic = re.sub(r'\d{13}', '[REDACTED]', body[:600].decode('utf-8', errors='replace'))
            raise RuntimeError(f'Unexpected response {result.status}: {diagnostic}') from None
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)


def passed(condition, label):
    assert condition, label
    print('PASS: ' + label)


passed(request('/api/admin/members/dashboard')[0] == 401, 'guest denied member management')
_, csrf = request('/csrf-token')
status, result = request('/login', {
    'username': os.environ['MEMBER_ADMIN_USER'],
    'password': os.environ['MEMBER_ADMIN_PASSWORD'], 'ajax': '1'}, csrf['token'])
passed(status == 200 and result['user']['role'] == 'member_admin'
       and result['redirect'] == '/admin/members/dashboard', 'membership administrator login')
status, overview = request('/api/admin/members/dashboard')
passed(status == 200 and int(overview['members']['total']) > 0, 'real membership overview')
status, result = request('/api/admin/members/health')
passed(status == 200 and result['health']['ready'], 'authenticated deployment health check')
status, members = request('/api/admin/members?year=2568&page=1')
passed(status == 200 and members['items'] and all(
    m['id_card_masked'].startswith('*********') for m in members['items']), 'year filter and identity masking')
member_id = members['items'][0]['id']
passed(members['year'] == '2568' and all('net' in m and 'total_income' in m and 'total_deductions' in m for m in members['items']), 'selected year financial columns are complete')
status, detail = request('/api/admin/members/' + str(member_id))
passed(status == 200 and detail['dividends'] and detail['version'], 'member detail with historic dividends')
passed(request('/api/admin/members/' + str(member_id), {})[0] == 419, 'edit requires CSRF')
_, csrf = request('/csrf-token')
passed(request('/api/admin/members/' + str(member_id), {}, csrf['token'])[0] == 422,
       'empty edit rejected without writes')
for path in ['/api/admin/users', '/api/admin/dashboard', '/api/admin/news', '/api/member/dividends']:
    passed(request(path)[0] == 403, 'restricted role denied ' + path)

record = detail['dividends'][0]['record']
member = detail['member']
headers = ['ปีบัญชี', 'เลขบัตรประชาชน', 'เลขทะเบียนสมาชิก', 'ชื่อนามสกุล', 'สังกัด']
headers += list(record['income']) + ['รวมรายการรับทั้งหมด']
headers += list(record['deductions']) + ['รวมรายจ่าย', 'ยอดเงินคงเหลือ', 'เงินที่ได้รับ', 'อัตราเงินปันผล', 'อัตราเงินเฉลี่ยคืน']
row = [record['year'], member['id_card'], member['member_no'], member['first_name'], member['department'] or '']
row += list(record['income'].values()) + [record['total_income']]
row += list(record['deductions'].values())
row += [record[key] for key in ['total_deductions', 'net', 'received', 'dividend_rate', 'refund_rate']]


def preview(rows):
    stream = io.StringIO(newline='')
    csv.writer(stream).writerows([headers] + rows)
    boundary = 'MemberHttpTestBoundary'
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="year"\r\n\r\n{record["year"]}\r\n'
            f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="http-test.csv"\r\n'
            'Content-Type: text/csv; charset=utf-8\r\n\r\n').encode()
    body += stream.getvalue().encode('utf-8-sig') + f'\r\n--{boundary}--\r\n'.encode()
    return request('/api/admin/dividends/preview', body, csrf['token'], raw=True,
                   content_type='multipart/form-data; boundary=' + boundary)


status, result = preview([row])
passed(status == 200 and result['counts']['unchanged'] == 1, 'upload preview compares existing year without changing records')
status, result = preview([row, row])
passed(status == 422 and result['errors'][0]['id_card'] == member['id_card']
       and result['errors'][0]['row'] == 3, 'duplicate upload reports exact identity and row')
status, result = request('/api/admin/dividends/confirm', {'token': 'invalid'}, csrf['token'])
passed(status == 422, 'invalid or cleared preview cannot be confirmed')
status, after = request('/api/admin/members/dashboard')
passed(status == 200 and after['years'] == overview['years'] and after['runs'] == overview['runs'],
       'HTTP checks leave live dividend records and import history unchanged')
print('Member administration HTTP tests passed.')
