<?php
$content = file_get_contents('index.html');
$content = str_replace("\r\n", "\n", $content);

// 1. loop() 内に updateSkeletonsAuto(gameState, Date.now()) の呼び出しを追加
$oldLoop = '            function loop() {
                if (gameState.phase === \'maze_escape\') {
                    updatePlayerMovementPoll();
                    pushConveyor(gameState, Date.now());
                    updateCannons(gameState, Date.now());
                    drawExpressionFrame(gameState.escape_time_left, GAME_CONFIG.escapeTimeSeconds);
                }';

$newLoop = '            let lastSkeletonMove = 0;
            function updateSkeletonsAuto(state, now) {
                if (state.phase !== \'maze_escape\') return;
                const isMeAttacker = isLocalDebugMode ? (localTurnOwner === state.attacker) : (myRole === state.attacker);
                if (!isMeAttacker) return;
                const interval = GAME_CONFIG.skeletonMoveIntervalMs || 800;
                if (now - lastSkeletonMove >= interval) {
                    lastSkeletonMove = now;
                    if (typeof moveSkeletons === \'function\') {
                        moveSkeletons(state);
                    }
                    if (typeof checkEnemyContact === \'function\' && checkEnemyContact(state)) {
                        const isInvincible = state.player_invincible_until && (Date.now() < state.player_invincible_until);
                        if (!isInvincible) {
                            const attackerChar = (state.attacker === \'host\') ? state.host_char : state.client_char;
                            if (attackerChar === \'defender\' && state.defender_barrier_active) {
                                state.defender_barrier_active = false;
                                state.special_used = true;
                                state.player_invincible_until = Date.now() + 3000;
                                if (typeof playConfirmSound === \'function\') playConfirmSound();
                                if (typeof spawnExplosion === \'function\') spawnExplosion(state.player_pos.x, state.player_pos.y, \'#00f2fe\');
                                if (typeof updateAbilityStatusHUD === \'function\') updateAbilityStatusHUD();
                            } else {
                                if (typeof playDamageSound === \'function\') playDamageSound();
                                if (typeof handleEscapeEnd === \'function\') handleEscapeEnd(\'failed_damage\');
                            }
                        }
                    }
                    if (!isLocalDebugMode) {
                        sendGameState();
                    }
                }
            }

            function loop() {
                if (gameState.phase === \'maze_escape\') {
                    updatePlayerMovementPoll();
                    pushConveyor(gameState, Date.now());
                    updateCannons(gameState, Date.now());
                    updateSkeletonsAuto(gameState, Date.now());
                    drawExpressionFrame(gameState.escape_time_left, GAME_CONFIG.escapeTimeSeconds);
                }';

$content = str_replace($oldLoop, $newLoop, $content);

// 2. executeMove 内の updateEnemies を updateStatues に書き換える
$oldExecuteMove = '            // 移動後にフェーズが切り替わっていたら、敵の更新はスキップします
            if (gameState.phase !== \'maze_escape\') return;

            // 移動タイミングでスケルトンや石像などの敵AIを更新
            updateEnemies(gameState, dx, dy);';

$newExecuteMove = '            // 移動後にフェーズが切り替わっていたら、敵の更新はスキップします
            if (gameState.phase !== \'maze_escape\') return;

            // 移動タイミングで石像などの敵AIを更新 (スケルトンは自律移動になりました)
            if (typeof updateStatues === \'function\') {
                updateStatues(gameState, dx, dy);
            }';

$content = str_replace($oldExecuteMove, $newExecuteMove, $content);

file_put_contents('index.html', str_replace("\n", "\r\n", $content));
echo "Successfully updated skeleton autonomous waddle patrol and statue updates in index.html!\n";
