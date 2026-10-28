<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Exceptions\SecurityGateException;
use App\Services\AdminControlCenterService;
use App\Services\AuditService;
use App\Services\DualControlSecurityService;
use App\Services\Logger;
use PDO;
use ZipArchive;

class BackupController extends Controller
{
    private string $backupDir;
    private string $uploadDir;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->backupDir = dirname(__DIR__, 3) . '/storage/backups';
        $this->uploadDir = dirname(__DIR__, 3) . '/storage/uploads';

        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0775, true);
        }
    }

    public function setBackupDir(string $backupDir): void
    {
        $this->backupDir = $backupDir;
    }

    public function index(): void
    {
        $backups = [];
        $totalSize = 0;
        $lastBackupTime = null;

        if (is_dir($this->backupDir)) {
            $files = glob($this->backupDir . '/*.*');
            $validFiles = array_filter($files, function ($f) {
                return preg_match('/\.(sql|zip)$/i', $f);
            });

            // Sort newest first
            usort($validFiles, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            foreach ($validFiles as $f) {
                $filename = basename($f);
                $size = filesize($f);
                $mtime = filemtime($f);
                $totalSize += $size;

                if ($lastBackupTime === null || $mtime > $lastBackupTime) {
                    $lastBackupTime = $mtime;
                }

                $type = 'database';
                $icon = 'bi-filetype-sql';
                $badge = 'bg-primary';
                $typeLabel = 'ฐานข้อมูล (SQL)';

                if (str_starts_with($filename, 'backup_full_')) {
                    $type = 'full';
                    $icon = 'bi-file-earmark-zip-fill';
                    $badge = 'bg-purple';
                    $typeLabel = 'สำรองข้อมูลสมบูรณ์ (Full)';
                } elseif (str_starts_with($filename, 'backup_uploads_') || str_ends_with($filename, '.zip')) {
                    $type = 'uploads';
                    $icon = 'bi-folder-symlink-fill';
                    $badge = 'bg-info text-dark';
                    $typeLabel = 'ไฟล์เอกสาร & สื่อ (Uploads)';
                }

                $backups[] = [
                    'filename' => $filename,
                    'size' => $size,
                    'created_at' => date('Y-m-d H:i:s', $mtime),
                    'type' => $type,
                    'icon' => $icon,
                    'badge' => $badge,
                    'typeLabel' => $typeLabel,
                ];
            }
        }

        // Database stats
        $pdo = Database::connect();
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $dbName = config('database.connections.mysql.database', 'rayongcoop_db');

        $dbStats = Database::first("SELECT 
            SUM(data_length + index_length) AS db_size,
            COUNT(*) as table_count
            FROM information_schema.TABLES 
            WHERE table_schema = ?", [$dbName]);

        $this->render('admin.backups.index', [
            'title' => 'ระบบสำรองและกู้คืนข้อมูล (System Backup & Restore)',
            'backups' => $backups,
            'totalSize' => $totalSize,
            'lastBackupTime' => $lastBackupTime ? date('Y-m-d H:i:s', $lastBackupTime) : null,
            'tableCount' => count($tables),
            'dbSize' => (int) ($dbStats['db_size'] ?? 0),
        ], 'layouts.admin');
    }

    public function createBackup(): void
    {
        try {
            $filename = 'backup_db_' . date('Y_m_d_His') . '.sql';
            $target = "{$this->backupDir}/{$filename}";

            $this->dumpDatabase($target);

            AuditService::log('backups', 'create_database', $filename, null, ['size' => filesize($target)]);
            Logger::info("Database backup created: {$filename} (" . filesize($target) . " bytes)");

            Session::flash('success', "สำรองฐานข้อมูลเรียบร้อยแล้ว: {$filename}");
        } catch (\Throwable $e) {
            Logger::error("Database backup failed: " . $e->getMessage());
            Session::flash('error', "เกิดข้อผิดพลาดในการสำรองฐานข้อมูล: " . $e->getMessage());
        }

        $this->redirect(url('admin/backups'));
    }

    public function createStorageBackup(): void
    {
        try {
            $filename = 'backup_uploads_' . date('Y_m_d_His') . '.zip';
            $target = "{$this->backupDir}/{$filename}";

            $zip = new ZipArchive();
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("ไม่สามารถสร้างไฟล์ ZIP ได้");
            }

            if (is_dir($this->uploadDir)) {
                $this->addFolderToZip($this->uploadDir, $zip, 'uploads');
            }

            $zip->close();

            AuditService::log('backups', 'create_uploads', $filename, null, ['size' => filesize($target)]);
            Logger::info("Uploads backup created: {$filename} (" . filesize($target) . " bytes)");

            Session::flash('success', "สำรองไฟล์เอกสารและสื่อเรียบร้อยแล้ว: {$filename}");
        } catch (\Throwable $e) {
            Logger::error("Uploads backup failed: " . $e->getMessage());
            Session::flash('error', "เกิดข้อผิดพลาดในการสำรองไฟล์: " . $e->getMessage());
        }

        $this->redirect(url('admin/backups'));
    }

    public function createFullBackup(): void
    {
        try {
            $timestamp = date('Y_m_d_His');
            $tempSqlFile = "{$this->backupDir}/temp_dump_{$timestamp}.sql";
            $zipFilename = "backup_full_{$timestamp}.zip";
            $target = "{$this->backupDir}/{$zipFilename}";

            // 1. Dump SQL
            $this->dumpDatabase($tempSqlFile);

            // 2. Zip SQL + uploads
            $zip = new ZipArchive();
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("ไม่สามารถสร้างไฟล์ ZIP สำรองแบบสมบูรณ์ได้");
            }

            $zip->addFile($tempSqlFile, "database_dump_{$timestamp}.sql");

            if (is_dir($this->uploadDir)) {
                $this->addFolderToZip($this->uploadDir, $zip, 'uploads');
            }

            $zip->close();

            // Clean up temporary SQL file
            if (file_exists($tempSqlFile)) {
                @unlink($tempSqlFile);
            }

            AuditService::log('backups', 'create_full', $zipFilename, null, ['size' => filesize($target)]);
            Logger::info("Full system backup created: {$zipFilename} (" . filesize($target) . " bytes)");

            Session::flash('success', "สำรองข้อมูลระบบทั้งหมดแบบสมบูรณ์เรียบร้อยแล้ว: {$zipFilename}");
        } catch (\Throwable $e) {
            Logger::error("Full backup failed: " . $e->getMessage());
            Session::flash('error', "เกิดข้อผิดพลาดในการสำรองระบบสมบูรณ์: " . $e->getMessage());
        }

        $this->redirect(url('admin/backups'));
    }

    public function download(string $filename): void
    {
        $filename = basename($filename);
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(sql|zip)$/i', $filename)) {
            Session::flash('error', 'ชื่อไฟล์ไม่ถูกต้อง');
            $this->redirect(url('admin/backups'));
            return;
        }

        $filePath = realpath($this->backupDir . '/' . $filename);
        $realBackupDir = realpath($this->backupDir);

        if ($filePath && file_exists($filePath) && $realBackupDir && str_starts_with($filePath, $realBackupDir)) {
            AuditService::log('backups', 'download', $filename);

            $mimeType = str_ends_with($filename, '.zip') ? 'application/zip' : 'application/sql';
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            readfile($filePath);
            exit;
        }

        Session::flash('error', 'ไม่พบไฟล์สำรองข้อมูลที่ระบุ');
        $this->redirect(url('admin/backups'));
    }

    /**
     * Restore Database from Backup — Strictly Enforces Dual-Control Approval,
     * Server-Side Step-Up MFA, Target/Integrity Binding, and Policy Kill-Switch.
     */
    public function restoreBackup(): void
    {
        $correlationId = AdminControlCenterService::correlationId();
        $filename = basename((string) ($this->request->input('filename') ?? $this->request->input('file') ?? ''));
        $approvalRequestId = (int) ($this->request->input('approval_request_id') ?? $this->request->input('approval_id') ?? 0);
        $stepUpCode = $this->request->input('step_up_code') !== null
            ? (string) $this->request->input('step_up_code')
            : ($this->request->input('totp_code') !== null ? (string) $this->request->input('totp_code') : null);

        try {
            $result = DualControlSecurityService::executeApprovedRestore(
                $approvalRequestId,
                (int) (Auth::id() ?? 0),
                $filename,
                $stepUpCode,
                $this->backupDir,
                null,
                $this->request->ip()
            );

            if ($this->request->isAjax()) {
                $this->response->json([
                    'success' => true,
                    'correlation_id' => $correlationId,
                    'message' => "กู้คืนฐานข้อมูลจากไฟล์ {$filename} สำเร็จเรียบร้อยแล้ว",
                    'data' => $result,
                ]);
                return;
            }

            Session::flash('success', "กู้คืนฐานข้อมูลจากไฟล์ {$filename} สำเร็จเรียบร้อยแล้ว");
        } catch (SecurityGateException $e) {
            if ($this->request->isAjax()) {
                $this->response->json([
                    'success' => false,
                    'code' => $e->getErrorCode(),
                    'correlation_id' => $correlationId,
                    'message' => $e->getMessage(),
                ], $e->getHttpStatus());
                return;
            }
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error("Database restore failed [{$correlationId}]: " . $e->getMessage());
            if ($this->request->isAjax()) {
                $this->response->json([
                    'success' => false,
                    'code' => 'RESTORE_EXECUTION_FAILED',
                    'correlation_id' => $correlationId,
                    'message' => 'เกิดข้อผิดพลาดในการกู้คืนฐานข้อมูล',
                ], 500);
                return;
            }
            Session::flash('error', 'เกิดข้อผิดพลาดในการกู้คืนฐานข้อมูล');
        }

        $this->redirect(url('admin/backups'));
    }

    /**
     * Delete Backup File — Requires Backend RBAC Permission + Fresh Step-Up MFA.
     */
    public function deleteBackup(): void
    {
        $correlationId = AdminControlCenterService::correlationId();
        $filename = basename((string) $this->request->input('filename'));
        $stepUpCode = $this->request->input('step_up_code') !== null
            ? (string) $this->request->input('step_up_code')
            : null;

        try {
            $userId = (int) (Auth::id() ?? 0);
            if (!DualControlSecurityService::hasExecutePermission($userId, 'backup.delete')) {
                throw new SecurityGateException(
                    'คุณไม่มีสิทธิ์ลบไฟล์สำรองข้อมูล (Permission Denied)',
                    403,
                    'BACKUP_DELETE_PERMISSION_DENIED'
                );
            }

            DualControlSecurityService::requireFreshStepUpMfa(
                $userId,
                $stepUpCode,
                'backup_delete',
                true,
                $this->request->ip()
            );

            if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(sql|zip)$/i', $filename)) {
                throw new SecurityGateException('ชื่อไฟล์ไม่ถูกต้อง', 422, 'INVALID_BACKUP_FILENAME');
            }

            $filePath = realpath($this->backupDir . '/' . $filename);
            $realBackupDir = realpath($this->backupDir);

            if ($filePath && file_exists($filePath) && $realBackupDir && str_starts_with($filePath, $realBackupDir)) {
                @unlink($filePath);
                DualControlSecurityService::logSecurityAudit('backups', 'delete', $filename, [
                    'deleted_by' => $userId,
                ]);
                Logger::info("Backup file deleted [{$correlationId}]: {$filename}");
                if ($this->request->isAjax()) {
                    $this->response->json([
                        'success' => true,
                        'correlation_id' => $correlationId,
                        'message' => "ลบไฟล์สำรอง {$filename} เรียบร้อยแล้ว",
                    ]);
                    return;
                }
                Session::flash('success', "ลบไฟล์สำรอง {$filename} เรียบร้อยแล้ว");
            } else {
                throw new SecurityGateException('ไม่พบไฟล์สำรองข้อมูลที่ต้องการลบ', 404, 'BACKUP_FILE_NOT_FOUND');
            }
        } catch (SecurityGateException $e) {
            if ($this->request->isAjax()) {
                $this->response->json([
                    'success' => false,
                    'code' => $e->getErrorCode(),
                    'correlation_id' => $correlationId,
                    'message' => $e->getMessage(),
                ], $e->getHttpStatus());
                return;
            }
            Session::flash('error', $e->getMessage());
        }

        $this->redirect(url('admin/backups'));
    }

    private function dumpDatabase(string $targetFile): void
    {
        $pdo = Database::connect();
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $dump = "-- ========================================================\n";
        $dump .= "-- RayongCoop Digital Portal Database Backup\n";
        $dump .= "-- Generated Date: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- App Version: " . config('app.version') . "\n";
        $dump .= "-- ========================================================\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $dump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $dump .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "-- Table structure for `{$table}`\n";
            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $dump .= $createTable['Create Table'] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $dump .= "-- Dumping data for table `{$table}`\n";
                foreach ($rows as $row) {
                    $fields = array_map(function ($val) use ($pdo) {
                        return $val === null ? 'NULL' : $pdo->quote((string) $val);
                    }, array_values($row));
                    $dump .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $fields) . ");\n";
                }
                $dump .= "\n";
            }
        }

        $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $dump .= "-- End of Backup\n";

        if (file_put_contents($targetFile, $dump) === false) {
            throw new \Exception("ไม่สามารถบันทึกไฟล์สำรองฐานข้อมูลได้");
        }
    }

    private function addFolderToZip(string $folder, ZipArchive $zip, string $localPrefix = ''): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(realpath($folder)) + 1);
            $zipPath = $localPrefix ? $localPrefix . '/' . str_replace('\\', '/', $relativePath) : str_replace('\\', '/', $relativePath);

            if ($file->isDir()) {
                $zip->addEmptyDir($zipPath);
            } elseif ($file->isFile()) {
                $zip->addFile($filePath, $zipPath);
            }
        }
    }
}
