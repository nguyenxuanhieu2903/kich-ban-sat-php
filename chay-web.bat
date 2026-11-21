@echo off
chcp 65001 >nul
echo ================================================================
echo  DANG KHOI CHAY HE THONG KICH BAN SAT (PORT 3000)...
echo ================================================================
start http://127.0.0.1:3000/
node frontend/serve.mjs
pause
