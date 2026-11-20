<?php
declare(strict_types=1);
// RC14 board UX source contract. Run without DB access.
$root = dirname(__DIR__);
$page = file_get_contents($root . '/src/pages/PublicContentAdminPage.jsx');
$css = file_get_contents($root . '/src/pages/BoardTermAdminRC06.css');
if ($page === false || $css === false) { fwrite(STDERR, "RC14_BOARD_UI=FAIL sources missing\n"); exit(1); }
$checks = [
 'checkbox selection' => 'boardRemoveSelection',
 'bulk draft removal' => 'function removeBoardSelection()',
 'editable board table' => 'board-admin-table',
 'image/profile control' => 'board-admin-person',
 'edit modal' => 'board-admin-modal',
 'draft save outside modal' => 'board-admin-draft-save',
 'history of changes' => 'board-admin-modal-history',
 'draft API unchanged' => '/api/admin/public-content/sections/${section}/drafts',
 'publish API unchanged' => '/api/admin/public-content/revisions/${revision.id}/publish',
 'board term audit' => 'auditBoardTerms(value)',
 'all validation issues' => 'collectBoardIssues(value',
 'validation report' => 'board-validation-report',
 'duplicate IDs reported' => 'รหัสซ้ำกับรายการที่',
 'board draft normalized to allowlist' => 'normalizeBoardDraft(value)',
 'board fields restricted before send' => 'BOARD_SERVER_FIELD_SET.has(key)',
];
foreach ($checks as $label => $needle) {
 if (strpos($page, $needle) === false) { fwrite(STDERR, "RC14_BOARD_UI=FAIL {$label}\n"); exit(1); }
}
if (strpos($css, '.board-admin-table') === false || strpos($css, '.board-admin-modal-backdrop') === false) {
 fwrite(STDERR, "RC14_BOARD_UI=FAIL stylesheet\n"); exit(1);
}
echo "RC14_BOARD_UI_SOURCE_CONTRACT=PASS\n";
