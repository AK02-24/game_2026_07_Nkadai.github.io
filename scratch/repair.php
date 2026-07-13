<?php
$content = file_get_contents('index.html');
$content = str_replace("\r\n", "\n", $content);

$target = '                if (activeTool === \'wall\') {
                    // 通常壁ツール (デバッグモード専用)
                    if (!isLocalDebugMode) return;
                    // 50個制限チェック
                    if (gameState.maze[y][x] === 0 && countPlacedParts(gameState) >= 50) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                        return;
                    }
                    if (gameState.maze[y][x] === 1) {
                        gameState.maze[y][x] = 0;
                        playCancelSound();
                    } else if (gameState.maze[y][x] === 0) {
                        gameState.maze[y][x] = 1;
                        playConfirmSound();
                    }
                } else if (activeTool === \'special\') {
                    // 特殊ツール: 各自のキャラ能力に応じた罠・壁を配置 (最大2個制限)
                    if (gameState.maze[y][x] === 0) {
                        const currentSpecials = countSpecialsInMaze();
                        if (currentSpecials < 2) {
                            if (countPlacedParts(gameState) >= 50) {
                                if (typeof playLoseSound === \'function\') playLoseSound();
                                return;
                            }
                            if (creatorChar === \'crusher\') gameState.maze[y][x] = 4; // クラッシュ床
                            if (creatorChar === \'ghost\') gameState.maze[y][x] = 5;   // 隠し壁
                            if (creatorChar === \'defender\') gameState.maze[y][x] = 6;// ダメージ床
                            playConfirmSound();
                        }
                    } else if (gameState.maze[y][x] >= 4 && gameState.maze[y][x] <= 6) {
                        gameState.maze[y][x] = 0;
                        playCancelSound();
                    }
                } else {
                    // 各種パーツの設置処理
                    const def = getPartDefByTool(activeTool);
                    if (!def) return;
                    
                    const count = gameState.creator_inventory ? (gameState.creator_inventory[activeTool] || 0) : 0;
                    if (count <= 0) return;
                    
                    // 50個制限
                    if (countPlacedParts(gameState) >= 50) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    }
                    
                    // 敵・砲台の最大数制限
                    if (def.tile === TILE.SKELETON && countTileInMaze(gameState, TILE.SKELETON) >= GAME_CONFIG.skeletonMax) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    if (def.tile === TILE.STATUE && countTileInMaze(gameState, TILE.STATUE) >= GAME_CONFIG.statueMax) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    }
                    if (def.tile === TILE.CANNON && countTileInMaze(gameState, TILE.CANNON) >= GAME_CONFIG.cannonMax) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    }';

$replacement = '                if (activeTool === \'wall\') {
                    // 通常壁ツール (デバッグモード専用)
                    if (!isLocalDebugMode) return;
                    // 50個制限チェック
                    if (gameState.maze[y][x] === 0 && countPlacedParts(gameState) >= 50) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    }
                    if (gameState.maze[y][x] === 1) {
                        gameState.maze[y][x] = 0;
                        playCancelSound();
                    } else if (gameState.maze[y][x] === 0) {
                        gameState.maze[y][x] = 1;
                        playConfirmSound();
                    }
                } else if (activeTool === \'special\') {
                    // 特殊ツール: 各自のキャラ能力に応じた罠・壁を配置 (最大2個制限)
                    if (gameState.maze[y][x] === 0) {
                        const currentSpecials = countSpecialsInMaze();
                        if (currentSpecials < 2) {
                            if (countPlacedParts(gameState) >= 50) {
                                if (typeof playLoseSound === \'function\') playLoseSound();
                                return;
                            }
                            if (creatorChar === \'crusher\') gameState.maze[y][x] = 4; // クラッシュ床
                            if (creatorChar === \'ghost\') gameState.maze[y][x] = 5;   // 隠し壁
                            if (creatorChar === \'defender\') gameState.maze[y][x] = 6;// ダメージ床
                            playConfirmSound();
                        }
                    } else if (gameState.maze[y][x] >= 4 && gameState.maze[y][x] <= 6) {
                        gameState.maze[y][x] = 0;
                        playCancelSound();
                    }
                } else {
                    // 各種パーツの設置処理
                    const def = getPartDefByTool(activeTool);
                    if (!def) return;
                    
                    const count = gameState.creator_inventory ? (gameState.creator_inventory[activeTool] || 0) : 0;
                    if (count <= 0) return;
                    
                    // 50個制限
                    if (countPlacedParts(gameState) >= 50) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    }
                    
                    // 敵・砲台の最大数制限
                    if (def.tile === TILE.SKELETON && countTileInMaze(gameState, TILE.SKELETON) >= GAME_CONFIG.skeletonMax) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    }
                    if (def.tile === TILE.STATUE && countTileInMaze(gameState, TILE.STATUE) >= GAME_CONFIG.statueMax) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    }
                    if (def.tile === TILE.CANNON && countTileInMaze(gameState, TILE.CANNON) >= GAME_CONFIG.cannonMax) {
                        if (typeof playLoseSound === \'function\') playLoseSound();
                        return;
                    }';

$content = str_replace($target, $replacement, $content);
file_put_contents('index.html', str_replace("\n", "\r\n", $content));
echo "Successfully fixed index.html syntax!\n";
