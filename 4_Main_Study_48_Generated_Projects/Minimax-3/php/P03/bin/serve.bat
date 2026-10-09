@echo off
cd /D "D:\000_phd_graudaiton\EMSE\MiniMax3\php\P03"
:loop
php -S 127.0.0.1:8099 -t public public/index.php >> D:\000_phd_graudaiton\EMSE\MiniMax3\php\P03\data\server.log 2>&1
goto loop