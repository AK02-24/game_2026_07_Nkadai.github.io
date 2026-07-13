@echo off
chcp 65001 > NUL
echo ===================================================
echo   迷路ゲーム サーバーを起動します (Port: 50000)
echo   ※同じWi-Fi(LAN)に繋がっている他のPCやスマホからも接続できます。
echo   
echo   接続URL: http://localhost:50000
echo   外部端末からは、localhostの部分をご自身のPCのIPアドレス
echo   (例: 192.168.x.x) に置き換えてアクセスしてください。
echo ===================================================
C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe -S 0.0.0.0:50000
pause
