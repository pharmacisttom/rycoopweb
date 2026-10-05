<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;
$records = json_decode(file_get_contents(__DIR__ . '/../storage/private/dividends.json'), true, 512, JSON_THROW_ON_ERROR);
$found = 0;
foreach ($records as $record) {
    $row = Database::first('SELECT d.details_json, u.password FROM member_dividends d JOIN members m ON m.id = d.member_id JOIN users u ON u.id = m.user_id WHERE m.id_card = ? AND d.year = ?', [$record['id_card'], $record['year']]);
    if (!$row || json_decode($row['details_json'], true)['net'] !== $record['net']) { throw new RuntimeException('Database/source mismatch'); }
    $found++;
}
echo "Verified {$found} source records against database.\n";
echo json_encode(Database::query('SELECT year, COUNT(*) AS records FROM member_dividends GROUP BY year ORDER BY year')), "\n";
$member = Database::first('SELECT u.password FROM users u JOIN members m ON m.user_id = u.id WHERE m.id_card = ?', [$records[0]['id_card']]);
if (!password_verify('สมาชิกตัวอย่าง', $member['password'])) { throw new RuntimeException('Initial password verification failed'); }
echo "Initial member password hash verified.\n";
