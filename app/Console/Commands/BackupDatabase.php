<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=7 : Number of backups to keep}';
    protected $description = 'Backup the database and rotate old backups';

    public function handle(): int
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            return $this->backupSqlite($connection);
        }

        if ($driver === 'mysql') {
            return $this->backupMysql($connection);
        }

        $this->error("Unsupported database driver: {$driver}");
        return self::FAILURE;
    }

    private function backupSqlite(string $connection): int
    {
        $dbPath = config("database.connections.{$connection}.database");

        if (!file_exists($dbPath)) {
            $this->error("SQLite database not found at: {$dbPath}");
            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        $this->ensureBackupDir($backupDir);

        $filename = 'backup_' . date('Y-m-d_His') . '.sqlite';
        $backupPath = $backupDir . '/' . $filename;

        copy($dbPath, $backupPath);

        $this->info("SQLite backup created: {$filename}");
        $this->rotateBackups($backupDir, '.sqlite');

        return self::SUCCESS;
    }

    private function backupMysql(string $connection): int
    {
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port", 3306);
        $database = config("database.connections.{$connection}.database");
        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");

        $backupDir = storage_path('app/backups');
        $this->ensureBackupDir($backupDir);

        $filename = 'backup_' . date('Y-m-d_His') . '.sql';
        $backupPath = $backupDir . '/' . $filename;

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($backupPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('mysqldump failed: ' . implode("\n", $output));
            @unlink($backupPath);
            return self::FAILURE;
        }

        $size = round(filesize($backupPath) / 1024, 1);
        $this->info("MySQL backup created: {$filename} ({$size} KB)");
        $this->rotateBackups($backupDir, '.sql');

        return self::SUCCESS;
    }

    private function ensureBackupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function rotateBackups(string $dir, string $extension): void
    {
        $keep = (int) $this->option('keep');
        $files = glob($dir . '/backup_*' . $extension);
        sort($files);

        $toDelete = count($files) - $keep;
        if ($toDelete > 0) {
            for ($i = 0; $i < $toDelete; $i++) {
                unlink($files[$i]);
                $this->info('Removed old backup: ' . basename($files[$i]));
            }
        }
    }
}
