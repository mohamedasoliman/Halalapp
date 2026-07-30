<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        if (! file_exists($dbPath)) {
            $this->error("SQLite database not found at: {$dbPath}");

            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        $this->ensureBackupDir($backupDir);

        $filename = 'backup_'.date('Y-m-d_His').'.sqlite';
        $backupPath = $backupDir.'/'.$filename;

        copy($dbPath, $backupPath);
        chmod($backupPath, 0600);

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

        $filename = 'backup_'.date('Y-m-d_His').'.sql';
        $backupPath = $backupDir.'/'.$filename;

        $defaultsPath = tempnam($backupDir, '.mysql-client-');
        if ($defaultsPath === false) {
            $this->error('Could not create a protected temporary MySQL configuration file.');

            return self::FAILURE;
        }

        chmod($defaultsPath, 0600);
        file_put_contents($defaultsPath, implode(PHP_EOL, [
            '[client]',
            'host="'.$this->escapeMysqlOption($host).'"',
            'port='.(int) $port,
            'user="'.$this->escapeMysqlOption($username).'"',
            'password="'.$this->escapeMysqlOption($password).'"',
            '',
        ]));

        $command = sprintf(
            'mysqldump --defaults-extra-file=%s --single-transaction --quick --skip-lock-tables %s > %s 2>&1',
            escapeshellarg($defaultsPath),
            escapeshellarg($database),
            escapeshellarg($backupPath)
        );

        try {
            exec($command, $output, $returnCode);
        } finally {
            @unlink($defaultsPath);
        }

        if ($returnCode !== 0) {
            $this->error('mysqldump failed: '.implode("\n", $output));
            @unlink($backupPath);

            return self::FAILURE;
        }

        chmod($backupPath, 0600);
        $size = round(filesize($backupPath) / 1024, 1);
        $this->info("MySQL backup created: {$filename} ({$size} KB)");
        $this->rotateBackups($backupDir, '.sql');

        return self::SUCCESS;
    }

    private function ensureBackupDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        chmod($dir, 0700);
    }

    private function rotateBackups(string $dir, string $extension): void
    {
        $keep = (int) $this->option('keep');
        $files = glob($dir.'/backup_*'.$extension);
        sort($files);

        $toDelete = count($files) - $keep;
        if ($toDelete > 0) {
            for ($i = 0; $i < $toDelete; $i++) {
                unlink($files[$i]);
                $this->info('Removed old backup: '.basename($files[$i]));
            }
        }
    }

    private function escapeMysqlOption(mixed $value): string
    {
        return str_replace(
            ['\\', '"', "\r", "\n"],
            ['\\\\', '\\"', '', ''],
            (string) $value
        );
    }
}
