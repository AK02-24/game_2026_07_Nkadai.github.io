<?php
$content = file_get_contents('index.html');
$content = str_replace("\r\n", "\n", $content);

// 1. handlePollResponse 同期処理の置換
$oldSync = '                if (gameState.phase === \'maze_create\') {
                    if (!isMeCreator) {
                        // 迷路データとタイマーを同期
                        gameState.maze = serverGame.maze;
                        gameState.create_time_left = serverGame.create_time_left;
                    }
                    gameState.phase = serverGame.phase;
                    gameState.host_ready = serverGame.host_ready;
                    gameState.client_ready = serverGame.client_ready;

                    // 両者とも作成完了した場合は、ホストが代表して脱出フェーズへ移行
                    if (gameState.host_ready && gameState.client_ready) {
                        if (myRole === \'host\') {
                            gameState.phase = \'maze_escape\';
                            gameState.escape_time_left = GAME_CONFIG.escapeTimeSeconds;
                            gameState.escape_status = \'playing\';
                            gameState.player_pos = { x: getStartPos(gameState).x, y: getStartPos(gameState).y };
                            gameState.special_used = false;
                            gameState.broken_walls = [];
                            gameState.stepped_crush_floors = [];
                            gameState.host_ready = false;
                            gameState.client_ready = false;
                            sendGameState();
                        }
                    }
                }
                else if (gameState.phase === \'maze_escape\') {
                    if (!isMeAttacker) {
                        // プレイヤー座標、迷路変化、能力使用、タイマー、成否を同期
                        gameState.player_pos = serverGame.player_pos;
                        gameState.maze = serverGame.maze;
                        gameState.special_used = serverGame.special_used;
                        gameState.escape_status = serverGame.escape_status;
                        gameState.escape_time_left = serverGame.escape_time_left;

                        targetPlayerPos.x = gameState.player_pos.x;
                        targetPlayerPos.y = gameState.player_pos.y;
                    }';

$newSync = '                if (gameState.phase === \'maze_create\') {
                    if (!isMeCreator) {
                        // 迷路データとタイマーを同期
                        gameState.maze = serverGame.maze;
                        gameState.create_time_left = serverGame.create_time_left;
                        gameState.start_pos = serverGame.start_pos;
                        gameState.goal_pos = serverGame.goal_pos;
                    }
                    gameState.phase = serverGame.phase;
                    gameState.host_ready = serverGame.host_ready;
                    gameState.client_ready = serverGame.client_ready;

                    // 両者とも作成完了した場合は、ホストが代表して脱出フェーズへ移行
                    if (gameState.host_ready && gameState.client_ready) {
                        if (myRole === \'host\') {
                            gameState.phase = \'maze_escape\';
                            gameState.escape_time_left = GAME_CONFIG.escapeTimeSeconds;
                            gameState.escape_status = \'playing\';
                            gameState.player_pos = { x: getStartPos(gameState).x, y: getStartPos(gameState).y };
                            gameState.special_used = false;
                            gameState.broken_walls = [];
                            gameState.stepped_crush_floors = [];
                            gameState.host_ready = false;
                            gameState.client_ready = false;
                            sendGameState();
                        }
                    }
                }
                else if (gameState.phase === \'maze_escape\') {
                    if (!isMeAttacker) {
                        // プレイヤー座標、迷路変化、能力使用、タイマー、成否を同期
                        gameState.player_pos = serverGame.player_pos;
                        gameState.maze = serverGame.maze;
                        gameState.special_used = serverGame.special_used;
                        gameState.escape_status = serverGame.escape_status;
                        gameState.escape_time_left = serverGame.escape_time_left;
                        gameState.start_pos = serverGame.start_pos;
                        gameState.goal_pos = serverGame.goal_pos;
                        gameState.tile_meta = serverGame.tile_meta || {};
                        gameState.enemies = serverGame.enemies || [];
                        gameState.player_invincible_until = serverGame.player_invincible_until || 0;
                        gameState.defender_barrier_active = serverGame.defender_barrier_active || false;

                        targetPlayerPos.x = gameState.player_pos.x;
                        targetPlayerPos.y = gameState.player_pos.y;
                    }';

$content = str_replace($oldSync, $newSync, $content);

// 2. mousedown イベントリスナー（通路移動 / 壁能力発動分岐）の置換
$oldMousedown = '                } else if (gameState.phase === \'maze_escape\') {
                    const isMeAttacker = isLocalDebugMode ? (localTurnOwner === gameState.attacker) : (myRole === gameState.attacker);
                    if (!isMeAttacker) return;
                    
                    const rect = canvas.getBoundingClientRect();
                    const x = Math.floor((e.clientX - rect.left) / (rect.width / SIZE));
                    const y = Math.floor((e.clientY - rect.top) / (rect.height / SIZE));
                    
                    const cx = gameState.player_pos.x;
                    const cy = gameState.player_pos.y;
                    
                    if (Math.abs(x - cx) + Math.abs(y - cy) === 1) {
                        if (x > cx) gameState.player_facing = ' . "'right'" . ';
                        else if (x < cx) gameState.player_facing = ' . "'left'" . ';
                        else if (y > cy) gameState.player_facing = ' . "'down'" . ';
                        else if (y < cy) gameState.player_facing = ' . "'up'" . ';
                        
                        triggerSpecialAbility();
                    }
                }';

$newMousedown = '                } else if (gameState.phase === \'maze_escape\') {
                    const isMeAttacker = isLocalDebugMode ? (localTurnOwner === gameState.attacker) : (myRole === gameState.attacker);
                    if (!isMeAttacker) return;
                    
                    const rect = canvas.getBoundingClientRect();
                    const x = Math.floor((e.clientX - rect.left) / (rect.width / SIZE));
                    const y = Math.floor((e.clientY - rect.top) / (rect.height / SIZE));
                    
                    const cx = gameState.player_pos.x;
                    const cy = gameState.player_pos.y;
                    
                    if (Math.abs(x - cx) + Math.abs(y - cy) === 1) {
                        // クリックされた方向を向く
                        if (x > cx) gameState.player_facing = \'right\';
                        else if (x < cx) gameState.player_facing = \'left\';
                        else if (y > cy) gameState.player_facing = \'down\';
                        else if (y < cy) gameState.player_facing = \'up\';
                        
                        const clickTile = gameState.maze[y][x];
                        if (clickTile === 1) {
                            // 壁なら明示的に能力を発動する
                            triggerSpecialAbility();
                        } else {
                            // 通路なら普通にその方向に歩いて移動する
                            const dx = x - cx;
                            const dy = y - cy;
                            attemptMove(dx, dy);
                        }
                    }
                }';

$content = str_replace($oldMousedown, $newMousedown, $content);

// 3. drawTopDownPlayer 点滅エフェクトの追加
$oldDrawTopDown = '        // --- 迷路脱出時のトップダウンキャラクター描画 (頭部・向き・手足歩行アニメーション) ---
        function drawTopDownPlayer(ctx, x, y, charId, facing, isMoving) {
            ctx.save();
            ctx.translate(x, y);';

$newDrawTopDown = '        // --- 迷路脱出時のトップダウンキャラクター描画 (頭部・向き・手足歩行アニメーション) ---
        function drawTopDownPlayer(ctx, x, y, charId, facing, isMoving) {
            // 無敵時間の点滅エフェクト
            const isInvincible = gameState.player_invincible_until && (Date.now() < gameState.player_invincible_until);
            ctx.save();
            if (isInvincible) {
                // 150ms周期で点滅
                const flash = Math.floor(Date.now() / 150) % 2 === 0;
                if (!flash) {
                    ctx.restore();
                    return; // 描画スキップ
                }
            }
            ctx.translate(x, y);';

$content = str_replace($oldDrawTopDown, $newDrawTopDown, $content);

// 4. updatePlayerMovementPoll 混乱床の反転適用の置換
$oldMovementPoll = '                if (moveUp) dy = -1;
                else if (moveDown) dy = 1;
                else if (moveLeft) dx = -1;
                else if (moveRight) dx = 1;
                
                if (dx !== 0 || dy !== 0) {
                    // キャラクターの向きを設定
                    if (dx === 1) gameState.player_facing = \'right\';
                    else if (dx === -1) gameState.player_facing = \'left\';
                    else if (dy === 1) gameState.player_facing = \'down\';
                    else if (dy === -1) gameState.player_facing = \'up\';
                    
                    attemptMove(dx, dy);
                }';

$newMovementPoll = '                if (moveUp) dy = -1;
                else if (moveDown) dy = 1;
                else if (moveLeft) dx = -1;
                else if (moveRight) dx = 1;
                
                if (dx !== 0 || dy !== 0) {
                    // 混乱状態の操作反転を適用
                    if (typeof applyMovementInversion === \'function\') {
                        const inv = applyMovementInversion(dx, dy);
                        dx = inv.dx;
                        dy = inv.dy;
                    }

                    // キャラクターの向きを設定
                    if (dx === 1) gameState.player_facing = \'right\';
                    else if (dx === -1) gameState.player_facing = \'left\';
                    else if (dy === 1) gameState.player_facing = \'down\';
                    else if (dy === -1) gameState.player_facing = \'up\';
                    
                    attemptMove(dx, dy);
                }';

$content = str_replace($oldMovementPoll, $newMovementPoll, $content);

// 5. triggerSpecialAbility ディフェンダー手動無効化の置換
$oldTriggerSpecial = '            if (attackerChar === \'defender\') {
                // ディフェンダー: バリアシールドを展開 (壁が目の前にある必要はありません)
                gameState.special_used = true;
                gameState.defender_barrier_active = true;
                spawnExplosion(cx, cy, \'#00f2fe\');
                playConfirmSound();
                if (isLocalDebugMode) {
                    updateAbilityStatusHUD();
                } else {
                    sendGameState();
                }
                return;
            }';

$newTriggerSpecial = '            if (attackerChar === \'defender\') {
                // ディフェンダー: 常時自動発動のため、手動キーでの発動は行いません
                return;
            }';

$content = str_replace($oldTriggerSpecial, $newTriggerSpecial, $content);

// 6. setupGamePhase ディフェンダー自動バリア化の置換
$oldSetupPhase = '            else if (gameState.phase === \'maze_escape\') {
                roleBadge.innerText = "迷路脱出フェーズ";
                roleBadge.className = "phase-badge phase-escape";
                document.getElementById(\'expression-frame\').style.display = \'block\';

                if (isMeAttacker) {';

$newSetupPhase = '            else if (gameState.phase === \'maze_escape\') {
                roleBadge.innerText = "迷路脱出フェーズ";
                roleBadge.className = "phase-badge phase-escape";
                document.getElementById(\'expression-frame\').style.display = \'block\';

                const attackerChar = (gameState.attacker === \'host\') ? gameState.host_char : gameState.client_char;
                if (attackerChar === \'defender\' && !gameState.special_used) {
                    gameState.defender_barrier_active = true;
                }

                if (isMeAttacker) {';

$content = str_replace($oldSetupPhase, $newSetupPhase, $content);

// 7. executeMove 敵接触無敵判定の置換
$oldExecuteMoveEnemy = '            // 敵が動いた結果、プレイヤーに接触したかをチェックします
            if (checkEnemyContact(gameState)) {
                const attackerChar = (gameState.attacker === \'host\') ? gameState.host_char : gameState.client_char;
                if (attackerChar === \'defender\' && gameState.defender_barrier_active) {
                    gameState.defender_barrier_active = false;
                    playConfirmSound();
                    spawnExplosion(tx, ty, \'#00f2fe\');
                    updateAbilityStatusHUD();
                } else {
                    playDamageSound();
                    handleEscapeEnd(\'failed_damage\');
                    return;
                }
            }';

$newExecuteMoveEnemy = '            // 敵が動いた結果、プレイヤーに接触したかをチェックします
            if (checkEnemyContact(gameState)) {
                const isInvincible = gameState.player_invincible_until && (Date.now() < gameState.player_invincible_until);
                if (isInvincible) {
                    // 無敵時間中はダメージを無視します
                } else {
                    const attackerChar = (gameState.attacker === \'host\') ? gameState.host_char : gameState.client_char;
                    if (attackerChar === \'defender\' && gameState.defender_barrier_active) {
                        gameState.defender_barrier_active = false;
                        gameState.special_used = true;
                        gameState.player_invincible_until = Date.now() + 3000; // 3秒間無敵にする
                        playConfirmSound();
                        spawnExplosion(tx, ty, \'#00f2fe\');
                        updateAbilityStatusHUD();
                    } else {
                        playDamageSound();
                        handleEscapeEnd(\'failed_damage\');
                        return;
                    }
                }
            }';

$content = str_replace($oldExecuteMoveEnemy, $newExecuteMoveEnemy, $content);

file_put_contents('index.html', str_replace("\n", "\r\n", $content));
echo "Successfully fixed index.html escape features, dynamic syncing, and blockage check updates!\n";
