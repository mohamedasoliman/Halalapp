# HalalApp Deployment Guide

This document explains how to deploy the HalalApp Laravel application to the HostGator server.

---

## Quick Deploy (One Command)

If everything is set up correctly, deploy with:

```bash
./deploy.sh
```

This will:
1. Push your local changes to GitHub
2. Pull the changes on the server
3. Install dependencies
4. Run migrations
5. Clear and rebuild caches

---

## Server Details

| Item | Value |
|------|-------|
| **Host** | `108.167.141.140` |
| **Username** | `halalapp` |
| **App Path** | `/home5/halalapp/halalapp` |
| **PHP Version** | 8.3 |
| **Live URL** | https://halalapp.info |

---

## SSH Access

### Prerequisites
Your Mac must have the SSH key set up. The key is located at:
- Private key: `~/.ssh/id_ed25519`
- Public key: `~/.ssh/id_ed25519.pub`

### Connect to Server

```bash
ssh halalapp@108.167.141.140
```

You should connect without a password prompt. If it asks for a password, see the [Troubleshooting](#troubleshooting) section.

### Navigate to App Directory

Once connected:
```bash
cd ~/halalapp
```

---

## Manual Deployment Steps

If the deploy script doesn't work, follow these steps manually:

### Step 1: Commit and Push Locally

```bash
# On your Mac, in the Halalapp directory
git add .
git commit -m "Your commit message"
git push origin main
```

### Step 2: SSH into Server

```bash
ssh halalapp@108.167.141.140
```

### Step 3: Pull Changes on Server

```bash
cd ~/halalapp
git fetch origin
git reset --hard origin/main
```

### Step 4: Install Dependencies

```bash
php ~/.composer/2022-10-27_14-39-29-2.4.4-old.phar install --no-dev --optimize-autoloader
```

### Step 5: Run Migrations

```bash
php artisan migrate --force
```

### Step 6: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
```

### Step 7: Fix Permissions (if needed)

```bash
chmod -R 775 storage bootstrap/cache
```

---

## Important Files on Server

| File/Directory | Purpose | Notes |
|----------------|---------|-------|
| `.env` | Environment config | **NEVER overwrite** - contains DB credentials |
| `storage/` | Logs, cache, uploads | Keep permissions 775 |
| `bootstrap/cache/` | Framework cache | Keep permissions 775 |
| `public/upload/` | User uploads | Not in git, preserved on deploy |

---

## Useful Commands

### Check Laravel Version
```bash
ssh halalapp@108.167.141.140 "cd ~/halalapp && php artisan --version"
```

### View Recent Logs
```bash
ssh halalapp@108.167.141.140 "tail -100 ~/halalapp/storage/logs/laravel.log"
```

### Check Disk Space
```bash
ssh halalapp@108.167.141.140 "df -h"
```

### Restart Queue Workers (if using)
```bash
ssh halalapp@108.167.141.140 "cd ~/halalapp && php artisan queue:restart"
```

---

## Rollback a Deployment

If something goes wrong, rollback to the previous commit:

```bash
ssh halalapp@108.167.141.140 << 'EOF'
cd ~/halalapp
git log --oneline -5          # Find the commit to rollback to
git reset --hard HEAD~1       # Go back 1 commit
php artisan migrate:rollback  # Rollback database (if needed)
php artisan config:cache
php artisan route:cache
EOF
```

Or restore from the backup folder:
```bash
ssh halalapp@108.167.141.140 "cp -r ~/halalapp_backup_20260130/* ~/halalapp/"
```

---

## Troubleshooting

### SSH Key Not Working

If SSH asks for a password:

1. **Check your key exists:**
   ```bash
   ls -la ~/.ssh/id_ed25519
   ```

2. **If missing, regenerate:**
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -N "" -C "halalapp-deploy"
   ```

3. **Add the public key to HostGator:**
   - Log into cPanel → SSH Access → Import Key
   - Name: `mac_deploy`
   - Paste contents of `~/.ssh/id_ed25519.pub`
   - Click Import, then Authorize

### Composer Fails

The server uses an older Composer at:
```
~/.composer/2022-10-27_14-39-29-2.4.4-old.phar
```

Run it with:
```bash
php ~/.composer/2022-10-27_14-39-29-2.4.4-old.phar install
```

### Permission Denied Errors

Fix storage permissions:
```bash
ssh halalapp@108.167.141.140 "chmod -R 775 ~/halalapp/storage ~/halalapp/bootstrap/cache"
```

### Git Merge Conflicts

If git shows conflicts, force reset to GitHub version:
```bash
ssh halalapp@108.167.141.140 "cd ~/halalapp && git fetch origin && git reset --hard origin/main"
```

**Warning:** This discards any server-only changes. The `.env` file is in `.gitignore` so it won't be affected.

### Database Migration Fails

Check the error in the logs:
```bash
ssh halalapp@108.167.141.140 "tail -50 ~/halalapp/storage/logs/laravel.log"
```

To rollback a failed migration:
```bash
ssh halalapp@108.167.141.140 "cd ~/halalapp && php artisan migrate:rollback"
```

---

## Server Backups

A backup was created at: `~/halalapp_backup_20260130`

To create a new backup before deploying:
```bash
ssh halalapp@108.167.141.140 "cp -r ~/halalapp ~/halalapp_backup_$(date +%Y%m%d_%H%M%S)"
```

---

## GitHub Repository

- **URL:** https://github.com/mohamedasoliman/Halalapp
- **Branch:** `main`

To check if local and server are in sync:
```bash
# Local commit
git log -1 --oneline

# Server commit
ssh halalapp@108.167.141.140 "cd ~/halalapp && git log -1 --oneline"
```

---

## Contact / Help

If you encounter issues not covered here:
1. Check Laravel logs: `~/halalapp/storage/logs/laravel.log`
2. Check server error log: `~/halalapp/error_log`
3. Check PHP version: `php -v`
