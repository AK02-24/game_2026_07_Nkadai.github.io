<?php
$content = file_get_contents('index.html');
$content = str_replace("\r\n", "\n", $content);

// 1. handlePollResponse 全体の置換
$oldPollResponse = '        // --- ポーリングレスポンスの処理 ---
        function handlePollResponse(response) {
            if (response.status !== \'success\') return;

            const serverState = response.data;
            const oldPhase = gameState.phase;

            // 対戦相手の名前特定
            if (myRole === \'host\') {
                opponentName = "2P (Client)";
            } else {
                opponentName = "1P (Host)";
            }

            // ホスト・クライアントの通信ステータス同期
            gameState.host_status = serverState.host_status;
            gameState.client_status = serverState.client_status;
            gameState.current_turn = serverState.current_turn;

            // 1. ロビー待機中のマッチング成功判定
            if (document.getElementById(\'screen-lobby\').classList.contains(\'active\')) {
                if (gameState.host_status === \'waiting\' && gameState.client_status === \'waiting\') {
                    stopWorker();
                    gameState.phase = \'char_select\';
                    goToScreen(\'screen-char-select\');
                    return;
                }
            }';

$newPollResponse = '        // --- ポーリングレスポンスの処理 ---
        function handlePollResponse(response) {
            if (response.status !== \'success\') return;

            // response.data は php レスポンス全体 {status: "success", data: {...}}
            if (!response.data || response.data.status !== \'success\') return;
            const serverState = response.data.data; // 本物のセッションデータオブジェクトを参照！
            const oldPhase = gameState.phase;

            // 対戦相手の名前特定
            if (myRole === \'host\') {
                opponentName = "2P (Client)";
            } else {
                opponentName = "1P (Host)";
            }

            // ホスト・クライアントの通信ステータス同期
            gameState.host_status = serverState.host_status;
            gameState.client_status = serverState.client_status;
            gameState.current_turn = serverState.current_turn;

            // 1. ロビー待機中のマッチング成功判定
            if (document.getElementById(\'screen-lobby\').classList.contains(\'active\')) {
                if (gameState.host_status === \'waiting\' && gameState.client_status === \'waiting\') {
                    stopWorker();
                    
                    // ゲームステートの初期化とホスト代表送信
                    gameState.phase = \'char_select\';
                    gameState.host_ready = false;
                    gameState.client_ready = false;
                    gameState.host_char = null;
                    gameState.client_char = null;

                    if (myRole === \'host\') {
                        sendGameState();
                    }

                    goToScreen(\'screen-char-select\');
                    return;
                }
            }';

$content = str_replace($oldPollResponse, $newPollResponse, $content);

file_put_contents('index.html', str_replace("\n", "\r\n", $content));
echo "Successfully fixed poll response JSON hierarchy and match initializer in index.html!\n";
