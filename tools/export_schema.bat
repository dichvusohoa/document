@echo off

echo ========================================
echo Export database schema
echo ========================================

echo.
echo Exporting tables.sql ...

D:\xampp\mysql\bin\mysqldump.exe ^
-u root ^
--default-character-set=utf8mb4 ^
--no-data ^
--skip-routines ^
--skip-events ^
--result-file="D:\Projects\PHP\document\database\schema\tables.sql" ^
dichvuqu_document

echo.
echo Exporting routines.sql ...

D:\xampp\mysql\bin\mysqldump.exe ^
-u root ^
--default-character-set=utf8mb4 ^
--no-data ^
--no-create-info ^
--routines ^
--skip-triggers ^
--skip-events ^
--result-file="D:\Projects\PHP\document\database\schema\routines.sql" ^
dichvuqu_document

echo.
echo ========================================
echo Export completed.
echo ========================================

pause