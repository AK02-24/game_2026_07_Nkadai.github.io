<?php
$content = file_get_contents('index.html');
$content = str_replace("\r\n", "\n", $content);

// 1. lockCharacter オンラインデータ構造の置換
$oldLockChar = '            } else {
                // オンライン対戦
                document.getElementById(\'btn-lock-char\').disabled = true;
                document.getElementById(\'char-select-status\').innerText = "同期中...";

                let updateData = {
                    action: \'update\',
                    session_id: sessionId
                };

                if (myRole === \'host\') {
                    updateData.host_char = selectedCharacterId;
                    updateData.host_ready = true;
                    gameState.host_char = selectedCharacterId;
                    gameState.host_ready = true;
                } else {
                    updateData.client_char = selectedCharacterId;
                    updateData.client_ready = true;
                    gameState.client_char = selectedCharacterId;
                    gameState.client_ready = true;
                }

                fetch(\'./api_session.php\', {
                    method: \'POST\',
                    headers: { \'Content-Type\': \'application/json\' },
                    body: JSON.stringify(updateData)
                });
            }';

$newLockChar = '            } else {
                // オンライン対戦
                document.getElementById(\'btn-lock-char\').disabled = true;
                document.getElementById(\'char-select-status\').innerText = "同期中...";

                if (myRole === \'host\') {
                    gameState.host_char = selectedCharacterId;
                    gameState.host_ready = true;
                } else {
                    gameState.client_char = selectedCharacterId;
                    gameState.client_ready = true;
                }

                let updateData = {
                    action: \'update\',
                    session_id: sessionId,
                    game_state: gameState
                };

                fetch(\'./api_session.php\', {
                    method: \'POST\',
                    headers: { \'Content-Type\': \'application/json\' },
                    body: JSON.stringify(updateData)
                });
            }';

$content = str_replace($oldLockChar, $newLockChar, $content);

// 2. handlePollResponse ロビーマッチング成立部分の置換
$oldLobbyMatch = '            // 1. ロビー待機中のマッチング成功判定
            if (oldPhase === \'init\' && document.getElementById(\'screen-lobby\').classList.contains(\'active\')) {
                if (gameState.host_status === \'waiting\' && gameState.client_status === \'waiting\') {
                    stopWorker();
                    gameState.phase = \'char_select\';
                    goToScreen(\'screen-char-select\');
                    return;
                }
            }';

$newLobbyMatch = '            // 1. ロビー待機中のマッチング成功判定
            if (document.getElementById(\'screen-lobby\').classList.contains(\'active\')) {
                if (gameState.host_status === \'waiting\' && gameState.client_status === \'waiting\') {
                    stopWorker();
                    gameState.phase = \'char_select\';
                    goToScreen(\'screen-char-select\');
                    return;
                }
            }';

$content = str_replace($oldLobbyMatch, $newLobbyMatch, $content);

file_put_contents('index.html', str_replace("\n", "\r\n", $content));
echo "Successfully repaired online matching and character select synchronization in index.html!\n";
