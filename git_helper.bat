@echo off
chcp 65001 > nul
cls
echo ==================================================
echo   GitHub コミット＆プッシュ支援ツール
echo ==================================================
echo.

:: Gitユーザー名・メールアドレスの設定確認
for /f "tokens=*" %%i in ('git config user.name') do set GIT_USER=%%i
for /f "tokens=*" %%i in ('git config user.email') do set GIT_EMAIL=%%i

echo 現在のGit設定:
echo   ユーザー名: %GIT_USER%
echo   メールアドレス: %GIT_EMAIL%
echo.
echo ※ GitHubに登録している名前とメールアドレスを設定すると、
echo   コミット履歴が正しくあなたのアカウントに紐づきます。
echo.

set /p config_choice="Gitの設定を変更しますか？ (y/n): "
if /i "%config_choice%"=="y" (
    set /p new_user="新しいユーザー名: "
    set /p new_email="新しいメールアドレス: "
    if not "%new_user%"=="" (
        git config --global user.name "%new_user%"
        set GIT_USER=%new_user%
    )
    if not "%new_email%"=="" (
        git config --global user.email "%new_email%"
        set GIT_EMAIL=%new_email%
    )
    echo 設定を更新しました。
    echo 現在の設定 - ユーザー名: %GIT_USER%, メールアドレス: %GIT_EMAIL%
    echo.
)

echo [1/4] 現在のファイルの変更状況を確認します...
echo --------------------------------------------------
git status
echo --------------------------------------------------
echo.

:ask_add
set /p add_choice="変更されたファイルをすべてコミット対象（ステージ）に追加しますか？ (y/n): "
if /i "%add_choice%"=="y" (
    git add -A
    echo すべての変更を追加しました。
) else if /i "%add_choice%"=="n" (
    echo 追加をスキップしました。既存のコミット対象のみで進めます。
) else (
    echo y か n で入力してください。
    goto ask_add
)
echo.

echo [2/4] コミットメッセージを入力してください。
echo (例: 最初のコミット, バグ修正, index.htmlの更新 など)
:input_msg
set /p commit_message="メッセージ: "
if "%commit_message%"=="" (
    echo メッセージを入力してください。
    goto input_msg
)
echo.

echo [3/4] ローカルリポジトリに保存（コミット）します...
git commit -m "%commit_message%"
if %errorlevel% neq 0 (
    echo.
    echo [エラー] コミットに失敗しました。
    echo 変更がないか、コミットメッセージが空の可能性があります。
    pause
    exit /b %errorlevel%
)
echo コミットが完了しました。
echo.

echo [4/4] GitHubにアップロード（プッシュ）します...
echo --------------------------------------------------
git push origin main
if %errorlevel% neq 0 (
    echo --------------------------------------------------
    echo.
    echo [警告/エラー] GitHubへのプッシュに失敗しました。
    echo 以下の原因が考えられます：
    echo 1. GitHubの認証（SSHキーの設定やログイン）が完了していない
    echo 2. リモートリポジトリに新しい変更があり、先に取得（プル）する必要がある
    echo.
    echo ※ プルを試す場合は「git pull origin main」を実行してください。
    echo ※ SSH接続エラーの場合は、GitHubに公開鍵が登録されているか確認してください。
    echo.
) else (
    echo --------------------------------------------------
    echo.
    echo プッシュが成功しました！GitHubで確認してください。
)
echo.
pause
