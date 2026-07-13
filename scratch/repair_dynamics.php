<?php
$content = file_get_contents('index.html');
$content = str_replace("\r\n", "\n", $content);

// 1. player_pos = { x: 0, y: 0 }; を dynamic start pos に置換
$content = str_replace(
    'gameState.player_pos = { x: 0, y: 0 };',
    'gameState.player_pos = { x: getStartPos(gameState).x, y: getStartPos(gameState).y };',
    $content
);

// 2. hasPath の置換
$hasPathOld = '        function hasPath(maze) {
            const queue = [{ x: 0, y: 0 }];
            const visited = Array.from({ length: SIZE }, () => Array(SIZE).fill(false));
            visited[0][0] = true;

            const dirs = [
                { x: 0, y: -1 }, { x: 0, y: 1 },
                { x: -1, y: 0 }, { x: 1, y: 0 }
            ];

            while (queue.length > 0) {
                const curr = queue.shift();
                if (curr.x === SIZE - 1 && curr.y === SIZE - 1) {
                    return true;
                }

                for (const d of dirs) {
                    const nx = curr.x + d.x;
                    const ny = curr.y + d.y;

                    if (nx >= 0 && nx < SIZE && ny >= 0 && ny < SIZE) {
                        // 通常壁 (1) 以外は通行可能とみなす (隠し壁5も通路扱い)
                        if (!visited[ny][nx] && maze[ny][nx] !== 1) {
                            visited[ny][nx] = true;
                            queue.push({ x: nx, y: ny });
                        }
                    }
                }
            }
            return false;
        }';

$hasPathNew = '        function hasPath(maze) {
            const start = getStartPos(gameState);
            const goal = getGoalPos(gameState);

            const queue = [{ x: start.x, y: start.y }];
            const visited = Array.from({ length: SIZE }, () => Array(SIZE).fill(false));
            visited[start.y][start.x] = true;

            const dirs = [
                { x: 0, y: -1 }, { x: 0, y: 1 },
                { x: -1, y: 0 }, { x: 1, y: 0 }
            ];

            while (queue.length > 0) {
                const curr = queue.shift();
                if (curr.x === goal.x && curr.y === goal.y) {
                    return true;
                }

                for (const d of dirs) {
                    const nx = curr.x + d.x;
                    const ny = curr.y + d.y;

                    if (nx >= 0 && nx < SIZE && ny >= 0 && ny < SIZE) {
                        // 通常壁 (1) 以外は通行可能とみなす (隠し壁5も通路扱い)
                        if (!visited[ny][nx] && maze[ny][nx] !== 1) {
                            visited[ny][nx] = true;
                            queue.push({ x: nx, y: ny });
                        }
                    }
                }
            }
            return false;
        }';

$content = str_replace($hasPathOld, $hasPathNew, $content);

// 3. handleCanvasClick の配置ルート・経路ふさぎガードの追加
$clickOld = '                if (activeTool === \'wall\') {
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
                    }

                    if (def.matrix) {
                        // 複数マスを持つテンプレートパーツ
                        const matrix = currentTemplateMatrix || def.matrix;
                        const rows = matrix.length;
                        const cols = matrix[0].length;

                        let canPlace = true;
                        for (let r = 0; r < rows; r++) {
                            for (let c = 0; c < cols; c++) {
                                if (matrix[r][c] === 1) {
                                    const tx = x + c;
                                    const ty = y + r;
                                    const isValid = tx >= 0 && tx < SIZE && ty >= 0 && ty < SIZE &&
                                        !((tx === startPos.x && ty === startPos.y) || (tx === goalPos.x && ty === goalPos.y)) &&
                                        (gameState.maze[ty][tx] === 0);
                                    if (!isValid) {
                                        canPlace = false;
                                        break;
                                    }
                                }
                            }
                            if (!canPlace) break;
                        }

                        if (canPlace) {
                            for (let r = 0; r < rows; r++) {
                                for (let c = 0; c < cols; c++) {
                                    if (matrix[r][c] === 1) {
                                        gameState.maze[y + r][x + c] = 1;
                                    }
                                }
                            }
                            if (!gameState.placed_templates) gameState.placed_templates = [];
                            gameState.placed_templates.push({
                                id: activeTool,
                                x: x,
                                y: y,
                                matrix: JSON.parse(JSON.stringify(matrix))
                            });
                            gameState.creator_inventory[activeTool]--;
                            playConfirmSound();
                        }
                    } else {
                        // 1マスの特殊パーツ (コンベヤー、トランポリン、混乱床、スケルトン、石像、砲台)
                        if (gameState.maze[y][x] === 0) {
                            const dir = (def.needsDir || def.tile === TILE.CANNON || def.tile === TILE.SKELETON) ? activePlacementDir : null;
                            placeSingleTile(gameState, x, y, def.tile, dir, activeTool);
                            gameState.creator_inventory[activeTool]--;
                            playConfirmSound();
                        }
                    }';

$clickNew = '                if (activeTool === \'wall\') {
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
                        // ルートふさぎ事前チェック
                        const tempMaze = JSON.parse(JSON.stringify(gameState.maze));
                        tempMaze[y][x] = 1;
                        if (!hasPath(tempMaze)) {
                            if (typeof playLoseSound === \'function\') playLoseSound();
                            return;
                        }
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
                            let targetTile = 0;
                            if (creatorChar === \'crusher\') targetTile = 4; // クラッシュ床
                            if (creatorChar === \'ghost\') targetTile = 5;   // 隠し壁
                            if (creatorChar === \'defender\') targetTile = 6;// ダメージ床
                            
                            // ルートふさぎ事前チェック
                            const tempMaze = JSON.parse(JSON.stringify(gameState.maze));
                            tempMaze[y][x] = targetTile;
                            if (!hasPath(tempMaze)) {
                                if (typeof playLoseSound === \'function\') playLoseSound();
                                return;
                            }
                            
                            gameState.maze[y][x] = targetTile;
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
                    }

                    if (def.matrix) {
                        // 複数マスを持つテンプレートパーツ
                        const matrix = currentTemplateMatrix || def.matrix;
                        const rows = matrix.length;
                        const cols = matrix[0].length;

                        let canPlace = true;
                        for (let r = 0; r < rows; r++) {
                            for (let c = 0; c < cols; c++) {
                                if (matrix[r][c] === 1) {
                                    const tx = x + c;
                                    const ty = y + r;
                                    const isValid = tx >= 0 && tx < SIZE && ty >= 0 && ty < SIZE &&
                                        !((tx === startPos.x && ty === startPos.y) || (tx === goalPos.x && ty === goalPos.y)) &&
                                        (gameState.maze[ty][tx] === 0);
                                    if (!isValid) {
                                        canPlace = false;
                                        break;
                                    }
                                }
                            }
                            if (!canPlace) break;
                        }

                        if (canPlace) {
                            // ルートふさぎ事前チェック
                            const tempMaze = JSON.parse(JSON.stringify(gameState.maze));
                            for (let r = 0; r < rows; r++) {
                                for (let c = 0; c < cols; c++) {
                                    if (matrix[r][c] === 1) {
                                        tempMaze[y + r][x + c] = 1;
                                    }
                                }
                            }
                            if (!hasPath(tempMaze)) {
                                if (typeof playLoseSound === \'function\') playLoseSound();
                                return;
                            }

                            for (let r = 0; r < rows; r++) {
                                for (let c = 0; c < cols; c++) {
                                    if (matrix[r][c] === 1) {
                                        gameState.maze[y + r][x + c] = 1;
                                    }
                                }
                            }
                            if (!gameState.placed_templates) gameState.placed_templates = [];
                            gameState.placed_templates.push({
                                id: activeTool,
                                x: x,
                                y: y,
                                matrix: JSON.parse(JSON.stringify(matrix))
                            });
                            gameState.creator_inventory[activeTool]--;
                            playConfirmSound();
                        }
                    } else {
                        // 1マスの特殊パーツ (コンベヤー、トランポリン、混乱床、スケルトン、石像、砲台)
                        if (gameState.maze[y][x] === 0) {
                            // ルートふさぎ事前チェック
                            const tempMaze = JSON.parse(JSON.stringify(gameState.maze));
                            tempMaze[y][x] = def.tile;
                            if (!hasPath(tempMaze)) {
                                if (typeof playLoseSound === \'function\') playLoseSound();
                                return;
                            }

                            const dir = (def.needsDir || def.tile === TILE.CANNON || def.tile === TILE.SKELETON) ? activePlacementDir : null;
                            placeSingleTile(gameState, x, y, def.tile, dir, activeTool);
                            gameState.creator_inventory[activeTool]--;
                            playConfirmSound();
                        }
                    }';

$content = str_replace($clickOld, $clickNew, $content);

// 4. プレビューとスタート・ゴール描画の置換
$drawOld = '            // 迷路作成中のテンプレートパーツプレビュー描画 (作成者のみ)
            if (gameState.phase === \'maze_create\' && isMeCreator && hoveredTile && activeTool !== \'wall\' && activeTool !== \'special\') {
                const template = WALL_TEMPLATES.find(t => t.id === activeTool);
                if (template) {
                    const inventoryCount = gameState.creator_inventory ? (gameState.creator_inventory[template.id] || 0) : 0;
                    if (inventoryCount > 0) {
                        const matrix = currentTemplateMatrix || template.matrix;
                        const rows = matrix.length;
                        const cols = matrix[0].length;

                        ctx.save();
                        ctx.globalAlpha = 0.4;

                        for (let r = 0; r < rows; r++) {
                            for (let c = 0; c < cols; c++) {
                                if (matrix[r][c] === 1) {
                                    const tx = hoveredTile.x + c;
                                    const ty = hoveredTile.y + r;

                                    // 範囲内かつスタート・ゴール以外かチェック
                                    const isValid = tx >= 0 && tx < SIZE && ty >= 0 && ty < SIZE &&
                                        !((tx === 0 && ty === 0) || (tx === SIZE - 1 && ty === SIZE - 1));

                                    const px = tx * TILE_SIZE;
                                    const py = ty * TILE_SIZE;

                                    if (isValid) {
                                        ctx.fillStyle = template.color;
                                        ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                                        ctx.strokeStyle = "#fff";
                                        ctx.lineWidth = 1.5;
                                        ctx.strokeRect(px + 1, py + 1, TILE_SIZE - 2, TILE_SIZE - 2);
                                    } else {
                                        // 配置不可のマスは半透明の赤で警告表示します
                                        ctx.fillStyle = "rgba(255, 59, 48, 0.6)";
                                        ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                                    }
                                }
                            }
                        }
                        ctx.restore();
                    }
                }
            }

            // スタート & ゴール描画
            ctx.save();
            // スタート
            ctx.strokeStyle = varColor(\'--neon-green\');
            ctx.lineWidth = 2;
            ctx.fillStyle = "rgba(57, 255, 20, 0.15)";
            ctx.beginPath();
            ctx.arc(TILE_SIZE / 2, TILE_SIZE / 2, TILE_SIZE / 2 - 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            // スタートテキスト
            ctx.fillStyle = "#fff";
            ctx.font = "8px \'Orbitron\'";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText("START", TILE_SIZE / 2, TILE_SIZE / 2);

            // ゴール
            const gx = (SIZE - 1) * TILE_SIZE + TILE_SIZE / 2;
            const gy = (SIZE - 1) * TILE_SIZE + TILE_SIZE / 2;
            ctx.strokeStyle = varColor(\'--neon-yellow\');
            ctx.lineWidth = 2;
            ctx.fillStyle = "rgba(255, 242, 0, 0.15)";
            ctx.beginPath();
            ctx.arc(gx, gy, TILE_SIZE / 2 - 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            // ゴールテキスト
            ctx.fillStyle = "#fff";
            ctx.fillText("GOAL", gx, gy);
            ctx.restore();';

$drawNew = '            // 迷路作成中のプレビュー描画 (作成者のみ)
            if (gameState.phase === \'maze_create\' && isMeCreator && hoveredTile) {
                const hx = hoveredTile.x;
                const hy = hoveredTile.y;
                const px = hx * TILE_SIZE;
                const py = hy * TILE_SIZE;
                const startPos = getStartPos(gameState);
                const goalPos = getGoalPos(gameState);

                ctx.save();
                ctx.globalAlpha = 0.5; // 半透明プレビュー

                if (activeTool === \'wall\') {
                    // 通常壁プレビュー
                    const isValid = !((hx === startPos.x && hy === startPos.y) || (hx === goalPos.x && hy === goalPos.y));
                    if (isValid) {
                        ctx.fillStyle = "rgba(0, 242, 254, 0.4)";
                        ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                        ctx.strokeStyle = "#fff";
                        ctx.lineWidth = 1.5;
                        ctx.strokeRect(px + 1, py + 1, TILE_SIZE - 2, TILE_SIZE - 2);
                    } else {
                        ctx.fillStyle = "rgba(255, 59, 48, 0.6)";
                        ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                    }
                } else if (activeTool === \'special\') {
                    // 特殊パーツプレビュー
                    const isValid = !((hx === startPos.x && hy === startPos.y) || (hx === goalPos.x && hy === goalPos.y));
                    if (isValid) {
                        let specialColor = "rgba(255, 255, 255, 0.4)";
                        if (creatorChar === \'crusher\') specialColor = "rgba(255, 149, 0, 0.4)";
                        if (creatorChar === \'ghost\') specialColor = "rgba(175, 82, 222, 0.4)";
                        if (creatorChar === \'defender\') specialColor = "rgba(255, 59, 48, 0.4)";
                        ctx.fillStyle = specialColor;
                        ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                        ctx.strokeStyle = "#fff";
                        ctx.lineWidth = 1.5;
                        ctx.strokeRect(px + 1, py + 1, TILE_SIZE - 2, TILE_SIZE - 2);
                    } else {
                        ctx.fillStyle = "rgba(255, 59, 48, 0.6)";
                        ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                    }
                } else {
                    const def = getPartDefByTool(activeTool);
                    if (def) {
                        const count = gameState.creator_inventory ? (gameState.creator_inventory[activeTool] || 0) : 0;
                        if (count > 0) {
                            if (def.matrix) {
                                // 複数マステンプレート
                                const matrix = currentTemplateMatrix || def.matrix;
                                const rows = matrix.length;
                                const cols = matrix[0].length;
                                for (let r = 0; r < rows; r++) {
                                    for (let c = 0; c < cols; c++) {
                                        if (matrix[r][c] === 1) {
                                            const tx = hx + c;
                                            const ty = hy + r;
                                            const isValid = tx >= 0 && tx < SIZE && ty >= 0 && ty < SIZE &&
                                                !((tx === startPos.x && ty === startPos.y) || (tx === goalPos.x && ty === goalPos.y));
                                            const ppx = tx * TILE_SIZE;
                                            const ppy = ty * TILE_SIZE;
                                            if (isValid) {
                                                ctx.fillStyle = def.color || "rgba(255,255,255,0.4)";
                                                ctx.fillRect(ppx, ppy, TILE_SIZE, TILE_SIZE);
                                                ctx.strokeStyle = "#fff";
                                                ctx.lineWidth = 1.5;
                                                ctx.strokeRect(ppx + 1, ppy + 1, TILE_SIZE - 2, TILE_SIZE - 2);
                                            } else {
                                                ctx.fillStyle = "rgba(255, 59, 48, 0.6)";
                                                ctx.fillRect(ppx, ppy, TILE_SIZE, TILE_SIZE);
                                            }
                                        }
                                    }
                                }
                            } else {
                                // 1マスの特殊パーツ (コンベヤー、トランポリン、混乱床、敵、砲台など)
                                const isValid = !((hx === startPos.x && hy === startPos.y) || (hx === goalPos.x && hy === goalPos.y));
                                if (isValid) {
                                    const tempState = JSON.parse(JSON.stringify(gameState));
                                    const key = hx + \',\' + hy;
                                    const dir = (def.needsDir || def.tile === TILE.CANNON || def.tile === TILE.SKELETON) ? activePlacementDir : null;
                                    if (dir) {
                                        tempState.tile_meta[key] = { dir: dir };
                                    }
                                    drawTileExtended(ctx, def.tile, hx, hy, px, py, TILE_SIZE, tempState, true);
                                } else {
                                    ctx.fillStyle = "rgba(255, 59, 48, 0.6)";
                                    ctx.fillRect(px, py, TILE_SIZE, TILE_SIZE);
                                }
                            }
                        }
                    }
                }
                ctx.restore();
            }

            // スタート & ゴール描画
            ctx.save();
            const start = getStartPos(gameState);
            const goal = getGoalPos(gameState);

            // スタート
            const sx = start.x * TILE_SIZE + TILE_SIZE / 2;
            const sy = start.y * TILE_SIZE + TILE_SIZE / 2;
            ctx.strokeStyle = varColor(\'--neon-green\');
            ctx.lineWidth = 2;
            ctx.fillStyle = "rgba(57, 255, 20, 0.15)";
            ctx.beginPath();
            ctx.arc(sx, sy, TILE_SIZE / 2 - 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            // スタートテキスト
            ctx.fillStyle = "#fff";
            ctx.font = "8px \'Orbitron\'";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText("START", sx, sy);

            // ゴール
            const gx = goal.x * TILE_SIZE + TILE_SIZE / 2;
            const gy = goal.y * TILE_SIZE + TILE_SIZE / 2;
            ctx.strokeStyle = varColor(\'--neon-yellow\');
            ctx.lineWidth = 2;
            ctx.fillStyle = "rgba(255, 242, 0, 0.15)";
            ctx.beginPath();
            ctx.arc(gx, gy, TILE_SIZE / 2 - 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            // ゴールテキスト
            ctx.fillStyle = "#fff";
            ctx.fillText("GOAL", gx, gy);
            ctx.restore();';

$content = str_replace($drawOld, $drawNew, $content);

file_put_contents('index.html', str_replace("\n", "\r\n", $content));
echo "Successfully fixed index.html coordinates, preview, and path blocking guards!\n";
