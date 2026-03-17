@echo off
echo Registering NLB Seller Map Daily Backup Task...
schtasks /create /tn "NLB SellerMap - Daily DB Backup" /tr "C:\xampp\htdocs\SellerMap\backups\run_backup.bat" /sc DAILY /st 00:00 /ru SYSTEM /f
echo.
echo Done! Task registered to run daily at 12:00 AM midnight.
pause
