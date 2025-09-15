@echo off
chcp 65001 >nul
setlocal ENABLEDELAYEDEXPANSION

set DB=self-logger.db
set TABLE=logs

if not exist %DB% (
    echo Создаю базу данных...
    sqlite3 %DB% "CREATE TABLE %TABLE%(id INTEGER PRIMARY KEY AUTOINCREMENT, user TEXT, datetime TEXT);"
)

sqlite3 %DB% "INSERT INTO %TABLE%(user, datetime) VALUES('%USERNAME%', strftime('%%Y.%%m.%%d %%H:%%M','now','localtime'));"

for /f "tokens=1" %%a in ('sqlite3 -noheader %DB% "SELECT COUNT(*) FROM %TABLE%;"') do set COUNT=%%a
for /f "tokens=*" %%a in ('sqlite3 -noheader %DB% "SELECT datetime FROM %TABLE% ORDER BY id ASC LIMIT 1;"') do set FIRST=%%a

echo Имя программы: self-logger.bat
echo Количество запусков: %COUNT%
echo Первый запуск: %FIRST%
echo ---------------------------------------------
echo User      ^| Date
echo ---------------------------------------------
for /f "tokens=1,2 delims=|" %%a in ('sqlite3 -noheader %DB% "SELECT user, datetime FROM %TABLE%;"') do (
    echo %%a    ^| %%b
)

echo --------------------------------------------
pause