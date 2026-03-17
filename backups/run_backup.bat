@echo off
:: NLB Seller Map - Daily Database Backup
:: This runs at midnight via Windows Task Scheduler

set PHP_EXE=C:\xampp\php\php.exe
set SCRIPT=C:\xampp\htdocs\SellerMap\cron_db_backup.php
set LOG=C:\xampp\htdocs\SellerMap\backups\task_runner.log

echo [%DATE% %TIME%] Starting backup... >> "%LOG%"
"%PHP_EXE%" "%SCRIPT%" >> "%LOG%" 2>&1
echo [%DATE% %TIME%] Done. >> "%LOG%"
