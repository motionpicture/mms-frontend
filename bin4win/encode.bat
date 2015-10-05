@echo off

rem このバッチが存在するフォルダをカレントに
pushd %0\..\..\bin
cls

rem 自分の環境に合わせてphpのパスを設定すること
C:\GoogleDrive\Develop\xampp1.8.2\php\php task PreEncodeMedia tryEncode

pause
