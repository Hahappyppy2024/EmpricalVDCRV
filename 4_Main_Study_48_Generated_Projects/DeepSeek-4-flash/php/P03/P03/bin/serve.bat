@echo off
rem P03 E-commerce System - start the built-in PHP web server (Windows).
rem Usage: bin\serve.bat
php -S 0.0.0.0:8080 -t public public\index.php
