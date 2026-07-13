const fs = require('fs');
const lines = fs.readFileSync('index.html', 'utf8').split(/\r?\n/);

// 2288行目(0-indexedで2287)〜2337行目(0-indexedで2336)を差し替える
const linesBefore = lines.slice(0, 2288);
const linesAfter = lines.slice(2337);

const replacement = [
    "                        return;",
    "                    }",
    "                    if (gameState.maze[y][x] === 1) {",
    "                        gameState.maze[y][x] = 0;",
    "                        playCancelSound();",
    "                    } else if (gameState.maze[y][x] === 0) {",
    "                        gameState.maze[y][x] = 1;",
    "                        playConfirmSound();",
    "                    }",
    "                } else if (activeTool === 'special') {",
    "                    // 特殊ツール: 各自のキャラ能力に応じた罠・壁を配置 (最大2個制限)",
    "                    if (gameState.maze[y][x] === 0) {",
    "                        const currentSpecials = countSpecialsInMaze();",
    "                        if (currentSpecials < 2) {",
    "                            if (countPlacedParts(gameState) >= 50) {",
    "                                if (typeof playLoseSound === 'function') playLoseSound();",
    "                                return;",
    "                            }",
    "                            if (creatorChar === 'crusher') gameState.maze[y][x] = 4; // クラッシュ床",
    "                            if (creatorChar === 'ghost') gameState.maze[y][x] = 5;   // 隠し壁",
    "                            if (creatorChar === 'defender') gameState.maze[y][x] = 6;// ダメージ床",
    "                            playConfirmSound();",
    "                        }",
    "                    } else if (gameState.maze[y][x] >= 4 && gameState.maze[y][x] <= 6) {",
    "                        gameState.maze[y][x] = 0;",
    "                        playCancelSound();",
    "                    }",
    "                } else {",
    "                    // 各種パーツの設置処理",
    "                    const def = getPartDefByTool(activeTool);",
    "                    if (!def) return;",
    "                    ",
    "                    const count = gameState.creator_inventory ? (gameState.creator_inventory[activeTool] || 0) : 0;",
    "                    if (count <= 0) return;",
    "                    ",
    "                    // 50個制限",
    "                    if (countPlacedParts(gameState) >= 50) {",
    "                        if (typeof playLoseSound === 'function') playLoseSound();",
    "                        return;",
    "                    }",
    "                    ",
    "                    // 敵・砲台の最大数制限",
    "                    if (def.tile === TILE.SKELETON && countTileInMaze(gameState, TILE.SKELETON) >= GAME_CONFIG.skeletonMax) {",
    "                        if (typeof playLoseSound === 'function') playLoseSound();",
    "                        return;"
];

const newContent = [...linesBefore, ...replacement, ...linesAfter].join('\n');
fs.writeFileSync('index.html', newContent, 'utf8');
console.log('Successfully repaired!');
