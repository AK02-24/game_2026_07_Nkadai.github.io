/**
 * game-mechanics.js
 * 
 * 拡張ゲームロジック（迷路生成、クリア可能性検証アルゴリズム、敵キャラクターやギミックの動き、
 * ビームの発射判定、顔グラフィックアニメーション、Canvasへのタイル描画支援など）をまとめています。
 */

// --- プレイヤーの現在の状態管理用グローバル変数 ---
let controlsInverted = false;   // 混乱状態で操作が反転しているかどうかのフラグ
let lastConveyorPush = 0;       // ベルトコンベヤーによる押し出しが最後に発生した時間（ミリ秒）
let activeToolbarFilter = 'all'; // ツールバーのパーツ絞り込みフィルター状態（'all', 'wall', 'floor', 'enemy'）
let imageCache = {};            // 画像のプリロード（事前読み込み）データを格納するオブジェクトキャッシュ

/** 
 * マップ上のスタート地点 {x, y} を取得します。
 * (gameStateオブジェクトに保存されたデータ、またはマップ配列内の値 2 を検索して特定します)
 */
function getStartPos(state) {
    if (state && state.start_pos) return state.start_pos;
    if (state && state.maze) {
        for (let r = 0; r < state.maze.length; r++) {
            for (let c = 0; c < state.maze[r].length; c++) {
                if (state.maze[r][c] === 2) return { x: c, y: r };
            }
        }
    }
    return { x: 0, y: 0 };
}

/** 
 * マップ上のゴール地点 {x, y} を取得します。
 * (gameStateオブジェクトに保存されたデータ、またはマップ配列内の値 3 を検索して特定します)
 */
function getGoalPos(state) {
    if (state && state.goal_pos) return state.goal_pos;
    if (state && state.maze) {
        for (let r = 0; r < state.maze.length; r++) {
            for (let c = 0; c < state.maze[r].length; c++) {
                if (state.maze[r][c] === 3) return { x: c, y: r };
            }
        }
    }
    const size = (state && state.maze) ? state.maze.length : 20;
    return { x: size - 1, y: size - 1 };
}

/** 
 * ゲーム中に使用する全てのキャラクター（歩行方向別）や、パーツの画像アセットを
 * 事前にブラウザへ読み込んでキャッシュします。これにより描画時のチラつきを防ぎます。
 */
function preloadImages() {
    const paths = new Set();
    
    // 各キャラクターの前後左右の画像パスを追加
    CHARACTERS.forEach(c => {
        paths.add(c.img);
        const base = c.img.replace('.svg', '');
        paths.add(`${base}_front.svg`);
        paths.add(`${base}_back.svg`);
        paths.add(`${base}_left.svg`);
        paths.add(`${base}_right.svg`);
    });
    
    // パーツ、壁テンプレート、床パーツの画像パスを追加
    PARTS_REGISTRY.forEach(p => paths.add(p.img));
    WALL_TEMPLATES.forEach(w => { if (w.img) paths.add(w.img); });
    FLOOR_PARTS.forEach(f => paths.add(f.img));
    
    // 画像オブジェクトを作成しプリロードを実行
    paths.forEach(src => {
        const img = new Image();
        img.src = src;
        imageCache[src] = img;
    });
}

/** 
 * ターン開始時に迷路データを初期化し、ランダムなスタートとゴールの配置、
 * メタデータや敵・ビームリストのクリアを行います。
 */
function initMazeExtended(size) {
    ensureTileMeta(gameState);
    
    // 空（TILE.EMPTY = 0）の二次元配列を生成
    const maze = Array.from({ length: size }, () => Array(size).fill(TILE.EMPTY));
    
    // スタートとゴールのランダムな位置を取得し設定
    const { start, goal } = pickRandomStartGoal(size);
    maze[start.y][start.x] = TILE.START;
    maze[goal.y][goal.x] = TILE.GOAL;
    
    gameState.start_pos = start;
    gameState.goal_pos = goal;
    gameState.tile_meta = {};
    gameState.enemies = [];
    gameState.beams = [];
    gameState.placed_items = [];
    
    return maze;
}

/** 
 * 指定座標 (x, y) が迷路（グリッド）の範囲内に収まっているかを判定します。
 */
function isInsideMaze(x, y, size) {
    return x >= 0 && x < size && y >= 0 && y < size;
}

/** 
 * 指定座標がスタートまたはゴールのマスであるかを判定します。
 */
function isStartOrGoal(x, y, state) {
    const start = getStartPos(state);
    const goal = getGoalPos(state);
    return (x === start.x && y === start.y) ||
        (x === goal.x && y === goal.y);
}

/** 
 * キャラクターが指定されたタイルを「歩いて通り抜けられるか」を判定します。
 * (通路、スタート、ゴール、すり抜け可能な隠し壁、各種特殊床、敵キャラクターなどを通り抜け可能と定義しています)
 */
function canWalkThrough(tile, attackerChar) {
    if (tile === TILE.EMPTY || tile === TILE.START || tile === TILE.GOAL) return true;
    if (tile === TILE.HIDDEN) return true;
    if (FLOOR_TILES.includes(tile) || tile === TILE.CONFUSION) return true;
    if (tile === TILE.SKELETON || tile === TILE.STATUE) return true;
    return false;
}

/** 
 * 【重要】BFS（幅優先探索アルゴリズム）を用いて、現在の迷路に「スタートからゴールまでのクリア可能ルート」が
 * 物理的に存在するかどうかを検証します。
 * 迷路作成の確定時にこの関数が実行され、ルートがない場合は確定させない仕組みになっています。
 */
function hasPathExtended(maze, state) {
    const start = getStartPos(state);
    const goal = getGoalPos(state);
    
    // 探索用のキュー（探索候補のマスを格納）
    const queue = [{ x: start.x, y: start.y }];
    
    // 訪問済みフラグを保持するマップ
    const visited = Array.from({ length: maze.length }, () => Array(maze.length).fill(false));
    visited[start.y][start.x] = true;
    
    // 上下左右の4方向ベクトル
    const dirs = [{ x: 0, y: -1 }, { x: 0, y: 1 }, { x: -1, y: 0 }, { x: 1, y: 0 }];

    while (queue.length > 0) {
        const curr = queue.shift();
        
        // ゴールに到達できた場合はクリア可能として true を返す
        if (curr.x === goal.x && curr.y === goal.y) return true;
        
        // 隣接する4方向を探索
        for (const d of dirs) {
            const nx = curr.x + d.x;
            const ny = curr.y + d.y;
            
            // 範囲外チェック
            if (!isInsideMaze(nx, ny, maze.length)) continue;
            
            // 既に訪問済みならスキップ
            if (visited[ny][nx]) continue;
            
            const tile = maze[ny][nx];
            // 通行できない壁マスであればスキップ
            if (isBlockingWall(tile)) continue;
            // 敵キャラクターマスも移動の都合上、探索ルートとしては除外する（安全第一）
            if (tile === TILE.SKELETON || tile === TILE.STATUE) continue;
            
            // 訪問済みに設定して探索キューに追加
            visited[ny][nx] = true;
            queue.push({ x: nx, y: ny });
        }
    }
    // スタートから探索しきってもゴールに到達できなかった場合は false
    return false;
}

/** 
 * ツールIDからパーツ（テンプレート壁または特殊効果床）の定義データを取得します。
 */
function getPartDefByTool(tool) {
    return WALL_TEMPLATES.find(t => t.id === tool) || FLOOR_PARTS.find(f => f.id === tool);
}

/** 
 * 指定座標のマスの「向き情報（dir）」を取得します。（コンベヤーや砲台などで使用）
 * 初期値は 'right' です。
 */
function getTileDirection(state, x, y) {
    const meta = state.tile_meta[getTileMetaKey(x, y)];
    return meta && meta.dir ? meta.dir : 'right';
}

/** 
 * マップ上の指定位置に、1マス用のギミック（敵、コンベヤー、砲台など）を配置します。
 * 敵などの場合は、自動で動きを管理するための配列 (enemies) にも追加を行います。
 */
function placeSingleTile(state, x, y, tile, dir, partId) {
    ensureTileMeta(state);
    state.maze[y][x] = tile;
    
    // 向きやパーツIDなどのメタデータを記録
    if (dir) {
        state.tile_meta[getTileMetaKey(x, y)] = { dir, partId };
    }
    
    // スケルトンの場合、敵管理リストに追加
    if (tile === TILE.SKELETON) {
        state.enemies.push({ type: 'skeleton', x, y, dir: dir || 'right', underTile: TILE.EMPTY });
    } 
    // 石像の場合、敵管理リストに追加
    else if (tile === TILE.STATUE) {
        state.enemies.push({ type: 'statue', x, y, underTile: TILE.EMPTY });
    } 
    // 砲台壁の場合、発射インターバルのメタデータを初期設定
    else if (tile === TILE.CANNON) {
        state.tile_meta[getTileMetaKey(x, y)] = { dir: dir || 'right', partId: 'cannon', lastFire: 0 };
    }
    
    state.placed_items = state.placed_items || [];
    state.placed_items.push({ x, y, tile, dir, partId });
}

/** 
 * 指定された座標 (x, y) の設置済み単一パーツを撤去（回収）します。
 * 回収したパーツは、作成者の手持ちインベントリに戻されます。
 */
function removePlacedAt(state, x, y) {
    ensureTileMeta(state);
    // 複数マスのテンプレート壁の一部である場合は、ここでは処理できません
    if (findPlacedTemplateAt(state, x, y) !== -1) return false;

    const tile = state.maze[y][x];
    if (tile === TILE.EMPTY || tile === TILE.START || tile === TILE.GOAL) return false;

    // マップからタイルを消去
    state.maze[y][x] = TILE.EMPTY;
    delete state.tile_meta[getTileMetaKey(x, y)];
    
    // 敵キャラリストから削除
    state.enemies = state.enemies.filter(e => !(e.x === x && e.y === y));

    const idx = (state.placed_items || []).findIndex(p => p.x === x && p.y === y);
    if (idx !== -1) {
        const item = state.placed_items[idx];
        state.placed_items.splice(idx, 1);
        
        // インベントリの所持数を1増やす
        if (item.partId && state.creator_inventory) {
            state.creator_inventory[item.partId] = (state.creator_inventory[item.partId] || 0) + 1;
        }
        return item;
    }
    return { tile };
}

/** 
 * 指定座標 (x, y) が、配置済みのどの複数マス壁テンプレートの一部であるかを検索します。
 * 見つかった場合はそのテンプレートのインデックス番号を返し、なければ -1 を返します。
 */
function findPlacedTemplateAt(state, x, y) {
    if (!state.placed_templates) return -1;
    return state.placed_templates.findIndex(pt => {
        const r = y - pt.y;
        const c = x - pt.x;
        // テンプレートマトリクスの範囲内かつ、そこが壁マス(1)であるかチェック
        return r >= 0 && r < pt.matrix.length && c >= 0 && c < pt.matrix[0].length && pt.matrix[r][c] === 1;
    });
}

/** 
 * 配置済みの複数マス壁テンプレートを丸ごと回収（撤去）し、手持ちインベントリに戻します。
 */
function removePlacedTemplate(state, index) {
    const pt = state.placed_templates[index];
    
    // マトリクス全体の構成壁マスをすべて空地に戻す
    for (let r = 0; r < pt.matrix.length; r++) {
        for (let c = 0; c < pt.matrix[r].length; c++) {
            if (pt.matrix[r][c] === 1) {
                state.maze[pt.y + r][pt.x + c] = TILE.EMPTY;
                delete state.tile_meta[getTileMetaKey(pt.x + c, pt.y + r)];
            }
        }
    }
    
    // インベントリのパーツ所持数を1増やす
    if (state.creator_inventory) {
        state.creator_inventory[pt.id] = (state.creator_inventory[pt.id] || 0) + 1;
    }
    state.placed_templates.splice(index, 1);
}

/** 
 * パーツが最大上限数（50個）に達しておらず、さらに配置可能かを判定します。
 */
function canPlaceMore(state) {
    return countPlacedParts(state) < GAME_CONFIG.maxTotalParts;
}

/** 
 * 脱出フェーズ開始に伴い、プレイヤーの位置（スタートマス）、方向、特殊能力のクールダウン、
 * 敵の初期向きなどを初期設定します。
 */
function initEscapePhase(state) {
    ensureTileMeta(state);
    state.player_pos = { x: state.start_pos.x, y: state.start_pos.y };
    state.player_facing = 'right';
    state.special_used = false;
    state.defender_barrier_active = false;
    controlsInverted = false;
    lastConveyorPush = 0;
    state.beams = [];
    state.enemies.forEach(e => {
        if (e.type === 'skeleton') e.dir = e.dir || 'right';
        e.underTile = TILE.EMPTY; // 脱出開始時に初期化
    });
}

/** 
 * 混乱状態（キー入力の上下左右の反転）をトグル（切り替え）します。
 */
function applyConfusion() {
    controlsInverted = !controlsInverted;
}

/** 
 * トランポリン床を踏んだ時の飛び先座標を計算します。
 * 乗った時点でのトランポリンの向き方向へ2マスジャンプします。途中に壁がある場合はその手前に着地します。
 */
function resolveTrampoline(state, x, y, facing) {
    const dir = getTileDirection(state, x, y);
    const vec = DIR_VECTORS[dir];
    const midX = x + vec.dx;
    const midY = y + vec.dy;
    const landX = x + vec.dx * 2;
    const landY = y + vec.dy * 2;

    // 1マス先が迷路の外、または壁ならジャンプせずその場に留まる
    if (!isInsideMaze(midX, midY, state.maze.length)) return { x, y };
    if (isBlockingWall(state.maze[midY][midX])) return { x, y };

    // 2マス先が迷路の外、または壁なら1マス先に着地する
    if (!isInsideMaze(landX, landY, state.maze.length)) return { x: midX, y: midY };
    if (isBlockingWall(state.maze[landY][landX])) return { x: midX, y: midY };
    
    // 障害物がなければ無事2マス先に着地
    return { x: landX, y: landY };
}

/** 
 * ベルトコンベヤーによるプレイヤーの自動移動処理を行います。
 * 一定間隔（conveyorMoveIntervalMs）ごとに、乗っているプレイヤーをコンベヤーの向きへ1マス押し出します。
 */
function pushConveyor(state, now) {
    if (now - lastConveyorPush < GAME_CONFIG.conveyorMoveIntervalMs) return;
    lastConveyorPush = now;

    const px = state.player_pos.x;
    const py = state.player_pos.y;
    const tile = state.maze[py][px];
    if (tile !== TILE.CONVEYOR) return;

    const dir = getTileDirection(state, px, py);
    const vec = DIR_VECTORS[dir];
    const nx = px + vec.dx;
    const ny = py + vec.dy;

    // 押し出し先が迷路の外、壁、または敵キャラクターであれば動かさない
    if (!isInsideMaze(nx, ny, state.maze.length)) return;
    if (isBlockingWall(state.maze[ny][nx])) return;
    if (state.maze[ny][nx] === TILE.SKELETON || state.maze[ny][nx] === TILE.STATUE) return;

    state.player_pos.x = nx;
    state.player_pos.y = ny;
    if (typeof targetPlayerPos !== 'undefined') {
        targetPlayerPos.x = nx;
        targetPlayerPos.y = ny;
    }
    
    // 移動した先のマスのギミック効果を連鎖的に実行する（コンベヤーやトゲ床などの連鎖処理）
    processTileAfterMove(state, nx, ny);
}

/** 
 * 敵（スケルトン）を自動で移動させます。
 * スケルトンは設定された向きに直進し、壁に突き当たると逆方向に向きを変えて移動を続けます。
 */
function moveSkeletons(state) {
    state.enemies.forEach(enemy => {
        if (enemy.type === 'skeleton') {
            let dir = enemy.dir || 'right';
            let vec = DIR_VECTORS[dir];
            let nx = enemy.x + vec.dx;
            let ny = enemy.y + vec.dy;
            
            // 進行方向が迷路外、または壁、または他のスケルトンの場合は反転（180度向きを変える）
            if (!isInsideMaze(nx, ny, state.maze.length) || isBlockingWall(state.maze[ny][nx]) || state.maze[ny][nx] === TILE.SKELETON) {
                dir = rotateDir(rotateDir(dir)); // 90度回転を2回で180度反転
                enemy.dir = dir;
                vec = DIR_VECTORS[dir];
                nx = enemy.x + vec.dx;
                ny = enemy.y + vec.dy;
            }
            
            // 移動が可能な場合は、前のマスを元に戻して移動先を敵タイルに書き換える
            if (isInsideMaze(nx, ny, state.maze.length) && !isBlockingWall(state.maze[ny][nx]) && state.maze[ny][nx] !== TILE.SKELETON) {
                // 移動前のマスを元の床に復元する
                state.maze[enemy.y][enemy.x] = (enemy.underTile !== undefined) ? enemy.underTile : TILE.EMPTY;
                
                // 新しい移動先の元の床を退避する
                enemy.underTile = state.maze[ny][nx];
                
                enemy.x = nx;
                enemy.y = ny;
                state.maze[ny][nx] = TILE.SKELETON;
            }
        }
    });
}

/** 
 * 敵（石像）を移動させます。
 * 石像は自分からは動きませんが、プレイヤーが移動した方向（playerDx, playerDy）と同じ方向へ
 * まるで影のように同期して移動し、行く手を塞ぎます。
 */
function updateStatues(state, playerDx, playerDy) {
    if (playerDx === 0 && playerDy === 0) return;
    state.enemies.forEach(enemy => {
        if (enemy.type === 'statue') {
            let nx = enemy.x + playerDx;
            let ny = enemy.y + playerDy;
            if (isInsideMaze(nx, ny, state.maze.length) && !isBlockingWall(state.maze[ny][nx]) && state.maze[ny][nx] !== TILE.STATUE) {
                // 移動前のマスを元の床に復元する
                state.maze[enemy.y][enemy.x] = (enemy.underTile !== undefined) ? enemy.underTile : TILE.EMPTY;
                
                // 新しい移動先の元の床を退避する
                enemy.underTile = state.maze[ny][nx];
                
                enemy.x = nx;
                enemy.y = ny;
                state.maze[ny][nx] = TILE.STATUE;
            }
        }
    });
}

/** 
 * 砲台壁の自動更新処理。
 * 一定時間（cannonFireIntervalSeconds）が経過するごとに、向き方向へビームを発射させます。
 */
function updateCannons(state, now) {
    const interval = GAME_CONFIG.cannonFireIntervalSeconds * 1000;
    for (let y = 0; y < state.maze.length; y++) {
        for (let x = 0; x < state.maze[y].length; x++) {
            if (state.maze[y][x] !== TILE.CANNON) continue;
            const key = getTileMetaKey(x, y);
            const meta = state.tile_meta[key] || { dir: 'right', lastFire: 0 };
            
            // クールダウン経過チェック
            if (now - (meta.lastFire || 0) < interval) continue;
            meta.lastFire = now;
            state.tile_meta[key] = meta;
            
            // ビーム発射処理を実行
            fireBeam(state, x, y, meta.dir);
        }
    }
}

/** 
 * 砲台から射出されたビームの直線当たり判定とダメージ処理を行います。
 * ビームは直進し、壁にぶつかるとそこで遮られます。
 * ビームに脱出者が接触するとライフ減少（即失敗）となります。
 * ディフェンダーがバリアスキルを展開している場合は、それを身代わりにして耐え、3秒間の無敵状態に移行します。
 */
function fireBeam(state, cx, cy, dir) {
    const vec = DIR_VECTORS[dir];
    let x = cx + vec.dx;
    let y = cy + vec.dy;
    
    // マップ外または壁にぶつかるまでビームを伸ばす
    while (isInsideMaze(x, y, state.maze.length)) {
        if (isBlockingWall(state.maze[y][x]) && state.maze[y][x] !== TILE.CANNON) break;
        
        // ビームエフェクトを登録（描画用）
        state.beams.push({ x, y, life: 500, color: '#ff6600' });
        
        // プレイヤーとの衝突検知
        if (x === state.player_pos.x && y === state.player_pos.y) {
            // ダメージ判定は、通信同期による二重処理や競合を防ぐためアタッカー（脱出プレイヤー）側でのみ計算する
            const isMeAttacker = (typeof isLocalDebugMode !== 'undefined' && isLocalDebugMode) 
                ? (typeof localTurnOwner !== 'undefined' && localTurnOwner === state.attacker)
                : (typeof myRole !== 'undefined' && myRole === state.attacker);

            if (isMeAttacker) {
                const isInvincible = state.player_invincible_until && (Date.now() < state.player_invincible_until);
                if (isInvincible) {
                    // 無敵時間（ダメージ後の猶予）はダメージを受けない
                } else {
                    const attackerChar = (state.attacker === 'host') ? state.host_char : state.client_char;
                    
                    // ディフェンダーがバリア展開中の場合、バリアを消費してダメージを防ぐ
                    if (attackerChar === 'defender' && state.defender_barrier_active) {
                        state.defender_barrier_active = false;
                        state.special_used = true;
                        state.player_invincible_until = Date.now() + 3000; // 3秒間無敵状態へ
                        if (typeof playConfirmSound === 'function') playConfirmSound();
                        if (typeof spawnExplosion === 'function') spawnExplosion(x, y, '#00f2fe');
                        if (typeof updateAbilityStatusHUD === 'function') updateAbilityStatusHUD();
                    } else {
                        // バリアが無い場合は被弾ダメージ、脱出失敗フェーズへ
                        if (typeof playDamageSound === 'function') playDamageSound();
                        if (typeof handleEscapeEnd === 'function') handleEscapeEnd('failed_damage');
                        return;
                    }
                }
            }
        }
        x += vec.dx;
        y += vec.dy;
    }
    if (typeof playBeamSound === 'function') playBeamSound();
}

/** 
 * プレイヤーが現在、敵キャラクター（スケルトン、石像）と重なり接触しているかを検証します。
 */
function checkEnemyContact(state) {
    const px = state.player_pos.x;
    const py = state.player_pos.y;
    for (const enemy of state.enemies) {
        if (enemy.x === px && enemy.y === py) return true;
    }
    if (state.maze[py][px] === TILE.SKELETON || state.maze[py][px] === TILE.STATUE) return true;
    return false;
}

/** 
 * プレイヤーが歩いて移動した直後に、その移動先のタイルの床効果や敵との衝突判定を実行します。
 * (ゴール到達、混乱床、トランポリン床のジャンプ、暗闇床、敵接触時の被ダメージなど)
 */
function processTileAfterMove(state, tx, ty) {
    const tile = state.maze[ty][tx];
    const attackerChar = (state.attacker === 'host') ? state.host_char : state.client_char;

    // ゴール判定
    const goal = getGoalPos(state);
    if (tile === TILE.GOAL || (tx === goal.x && ty === goal.y)) {
        if (typeof handleEscapeEnd === 'function') handleEscapeEnd('success');
        return;
    }

    // 混乱床を踏んだ場合
    if (tile === TILE.CONFUSION) {
        applyConfusion();
        state.maze[ty][tx] = TILE.EMPTY; // 一度踏むと消滅する仕様
        if (typeof playConfirmSound === 'function') playConfirmSound();
    }

    // トランポリン床を踏んだ場合
    if (tile === TILE.TRAMPOLINE) {
        state.maze[ty][tx] = TILE.EMPTY; // 跳ぶ前にトランポリン床を消す
        delete state.tile_meta[getTileMetaKey(tx, ty)];
        
        // 飛び先を計算して座標を強制ワープ
        const land = resolveTrampoline(state, tx, ty);
        state.player_pos.x = land.x;
        state.player_pos.y = land.y;
        if (typeof targetPlayerPos !== 'undefined') {
            targetPlayerPos.x = land.x;
            targetPlayerPos.y = land.y;
        }
        if (typeof playBounceSound === 'function') playBounceSound();
        
        // ジャンプ着地先のマスの効果をさらに再帰処理
        processTileAfterMove(state, land.x, land.y);
        return;
    }

    // 暗闇床を踏んだ場合
    if (tile === TILE.DARK) {
        state.darkness_until = Date.now() + 20000; // 20秒間暗闇状態（視界が極端に狭まる）に移行
        state.maze[ty][tx] = TILE.EMPTY; // 踏んだら消える
        if (typeof playCancelSound === 'function') playCancelSound();
        return;
    }

    // 敵（スケルトン・石像）との接触判定
    if (checkEnemyContact(state)) {
        const isInvincible = state.player_invincible_until && (Date.now() < state.player_invincible_until);
        if (isInvincible) {
            // 無敵状態中なら無傷で通り抜ける
        } else {
            // ディフェンダーの無敵バリアがあればそれを消費、無ければ脱出失敗
            if (attackerChar === 'defender' && state.defender_barrier_active) {
                state.defender_barrier_active = false;
                state.special_used = true;
                state.player_invincible_until = Date.now() + 3000; // 3秒無敵
                if (typeof playConfirmSound === 'function') playConfirmSound();
                if (typeof spawnExplosion === 'function') spawnExplosion(tx, ty, '#00f2fe');
                if (typeof updateAbilityStatusHUD === 'function') updateAbilityStatusHUD();
            } else {
                if (typeof playDamageSound === 'function') playDamageSound();
                if (typeof handleEscapeEnd === 'function') handleEscapeEnd('failed_damage');
                return;
            }
        }
    }
}

/** 
 * 混乱状態（操作反転フラグが立っている）の場合、キー入力のベクトルを反転させます。
 * 例: 右キー入力(dx:1) -> 左(dx:-1)へ反転
 */
function applyMovementInversion(dx, dy) {
    if (!controlsInverted) return { dx, dy };
    return { dx: -dx, dy: -dy };
}

/** 
 * 脱出者の残りタイムの割合から、HUD表示用の「表情フェイスステータス」を返します。
 * (時間があるときは『normal』、残り30-60%で『worried/心配』、残り30%未満で『panic/パニック』)
 */
function getExpressionFace(timeLeft, maxTime) {
    const ratio = timeLeft / maxTime;
    if (ratio > 0.6) return 'normal';
    if (ratio > 0.3) return 'worried';
    return 'panic';
}

/** 
 * ゲーム画面描画時に、標準の壁(1)以外の「特殊なギミックタイル（コンベヤー、敵、砲台など）」を
 * 座標に応じて2D Canvas上に描画します。
 */
function drawTileExtended(ctx, tile, x, y, px, py, tileSize, state, isMeCreator) {
    // ベルトコンベヤー床の描画
    if (tile === TILE.CONVEYOR) {
        const dir = getTileDirection(state, x, y);
        ctx.fillStyle = 'rgba(0, 204, 255, 0.25)';
        ctx.fillRect(px, py, tileSize, tileSize);
        ctx.strokeStyle = '#00ccff';
        ctx.strokeRect(px + 2, py + 2, tileSize - 4, tileSize - 4);
        drawDirectionArrow(ctx, px + tileSize / 2, py + tileSize / 2, dir, '#00ccff');
    } 
    // トランポリン床の描画
    else if (tile === TILE.TRAMPOLINE) {
        ctx.fillStyle = 'rgba(255, 102, 204, 0.25)';
        ctx.fillRect(px, py, tileSize, tileSize);
        ctx.strokeStyle = '#ff66cc';
        ctx.strokeRect(px + 2, py + 2, tileSize - 4, tileSize - 4);
        drawDirectionArrow(ctx, px + tileSize / 2, py + tileSize / 2, getTileDirection(state, x, y), '#ff66cc');
    } 
    // 混乱床の描画
    else if (tile === TILE.CONFUSION) {
        ctx.fillStyle = 'rgba(204, 102, 255, 0.25)';
        ctx.fillRect(px, py, tileSize, tileSize);
        ctx.fillStyle = '#cc66ff';
        ctx.font = '10px Outfit';
        ctx.textAlign = 'center';
        ctx.fillText('?', px + tileSize / 2, py + tileSize / 2 + 3);
    } 
    // 砲台壁の描画（チャージ中・発射寸前で赤熱化するエフェクト）
    else if (tile === TILE.CANNON) {
        const now = Date.now();
        const meta = state.tile_meta[getTileMetaKey(x, y)] || { lastFire: 0 };
        const phase = (now % (GAME_CONFIG.cannonFireIntervalSeconds * 1000)) / (GAME_CONFIG.cannonFireIntervalSeconds * 1000);
        // チャージ完了に近づくほど色が赤くなります
        const heat = phase > 0.75 ? '#ff0000' : (phase > 0.5 ? '#ff9900' : '#666666');
        ctx.fillStyle = heat;
        ctx.fillRect(px + 4, py + 4, tileSize - 8, tileSize - 8);
        drawDirectionArrow(ctx, px + tileSize / 2, py + tileSize / 2, getTileDirection(state, x, y), '#fff');
    } 
    // 敵（スケルトン）の描画。進行方向に応じた赤目の位置表現付き。
    else if (tile === TILE.SKELETON) {
        const enemy = (state.enemies || []).find(e => e.x === x && e.y === y);
        let dir = enemy ? enemy.dir : 'right';
        if (!enemy) {
            const key = x + ',' + y;
            if (state.tile_meta && state.tile_meta[key] && state.tile_meta[key].dir) {
                dir = state.tile_meta[key].dir;
            }
        }

        ctx.fillStyle = 'rgba(200,200,200,0.35)';
        ctx.beginPath();
        ctx.arc(px + tileSize / 2, py + tileSize / 2, tileSize / 3, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#fff';
        // 向きに応じてスケルトンの「目」の描画位置を変更
        if (dir === 'right') {
            ctx.fillRect(px + tileSize / 2 + 2, py + tileSize / 2 - 5, 3, 3);
            ctx.fillRect(px + tileSize / 2 + 2, py + tileSize / 2 + 2, 3, 3);
        } else if (dir === 'left') {
            ctx.fillRect(px + tileSize / 2 - 5, py + tileSize / 2 - 5, 3, 3);
            ctx.fillRect(px + tileSize / 2 - 5, py + tileSize / 2 + 2, 3, 3);
        } else if (dir === 'down') {
            ctx.fillRect(px + tileSize / 2 - 5, py + tileSize / 2 + 2, 3, 3);
            ctx.fillRect(px + tileSize / 2 + 2, py + tileSize / 2 + 2, 3, 3);
        } else {
            // up
            ctx.fillRect(px + tileSize / 2 - 5, py + tileSize / 2 - 5, 3, 3);
            ctx.fillRect(px + tileSize / 2 + 2, py + tileSize / 2 - 5, 3, 3);
        }
    } 
    // 敵（石像）の描画
    else if (tile === TILE.STATUE) {
        ctx.fillStyle = 'rgba(120,120,120,0.5)';
        ctx.fillRect(px + 6, py + 6, tileSize - 12, tileSize - 12);
        ctx.strokeStyle = '#aaa';
        ctx.strokeRect(px + 6, py + 6, tileSize - 12, tileSize - 12);
    }
}

/** 
 * パーツ（コンベヤー、砲台など）に方向がある場合、その「矢印（方向インジケーター）」を描画します。
 */
function drawDirectionArrow(ctx, cx, cy, dir, color) {
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(DIR_VECTORS[dir].angle);
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(8, 0);
    ctx.lineTo(-4, -5);
    ctx.lineTo(-4, 5);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
}

/** 
 * 砲台から発射され、飛んでいる「ビームエフェクト」をマップ上に一時的に描画します。
 * (ライフが残っている間だけフェードしながら描画します)
 */
function drawBeams(ctx, state, tileSize) {
    const now = Date.now();
    state.beams = (state.beams || []).filter(b => now - (b.spawn || now) < b.life);
    state.beams.forEach(b => {
        if (!b.spawn) b.spawn = now;
        ctx.fillStyle = b.color;
        ctx.fillRect(b.x * tileSize + 8, b.y * tileSize + 8, tileSize - 16, tileSize - 16);
    });
}

/** 
 * 対戦中の画面中央や相手用HUDに描画される「脱出者の顔感情グラフィック（表情フレーム）」を
 * ベクターグラフィックス（Canvas描画）でリアルタイムにレンダリングします。
 * 制限時間やキャラクター種別、汗しぶきアニメーションなどを含みます。
 */
function drawExpressionFrame(timeLeft, maxTime) {
    const canvas = document.getElementById('expression-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // 現在の脱出者（アタッカー）のキャラクターIDを判定
    const charId = (gameState.attacker === 'host') ? gameState.host_char : gameState.client_char;
    if (!charId) return;
    
    const face = getExpressionFace(timeLeft, maxTime);
    
    // 外枠とバックグラウンド描画
    ctx.fillStyle = 'rgba(10,12,28,0.85)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = 'rgba(0,242,254,0.5)';
    ctx.strokeRect(1, 1, canvas.width - 2, canvas.height - 2);

    // キャラクターの特徴色で頭部（ベース円）を描画
    ctx.save();
    let charColor = '#00f2fe';
    if (charId === 'crusher') charColor = '#ff3b30';
    if (charId === 'ghost') charColor = '#af52de';
    if (charId === 'defender') charColor = '#007aff';
    
    ctx.shadowColor = charColor;
    ctx.shadowBlur = 6;
    
    ctx.fillStyle = charColor;
    ctx.beginPath();
    ctx.arc(40, 42, 22, 0, Math.PI * 2);
    ctx.fill();
    
    // キャラクター毎の固有形状（角やバイザーなど）の追加描画
    if (charId === 'crusher') {
        // クラッシャーの角（左右）
        ctx.fillStyle = '#ffcc00';
        ctx.beginPath();
        ctx.moveTo(25, 26);
        ctx.lineTo(15, 12);
        ctx.lineTo(30, 22);
        ctx.closePath();
        ctx.fill();
        ctx.beginPath();
        ctx.moveTo(55, 26);
        ctx.lineTo(65, 12);
        ctx.lineTo(50, 22);
        ctx.closePath();
        ctx.fill();
    } else if (charId === 'ghost') {
        // ゴーストのフワフワした下半身
        ctx.fillStyle = charColor;
        ctx.beginPath();
        ctx.moveTo(20, 50);
        ctx.quadraticCurveTo(40, 68, 60, 50);
        ctx.closePath();
        ctx.fill();
    } else if (charId === 'defender') {
        // ディフェンダーの兜プレート（額パーツ）
        ctx.fillStyle = '#0f488f';
        ctx.fillRect(26, 28, 28, 6);
    }
    
    ctx.restore();
    
    // 残り時間に応じた「表情（顔パーツ）」をオーバーレイ描画
    ctx.fillStyle = '#ffffff';
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 1.5;
    
    if (face === 'normal') {
        // 通常時：余裕の笑顔、またはキリッとしたバイザー
        if (charId === 'ghost' || charId === 'crusher') {
            ctx.fillStyle = '#fff';
            ctx.beginPath();
            ctx.arc(32, 38, 2.5, 0, Math.PI*2);
            ctx.arc(48, 38, 2.5, 0, Math.PI*2);
            ctx.fill();
            ctx.beginPath();
            ctx.arc(40, 47, 5, 0, Math.PI);
            ctx.stroke();
        } else {
            // ディフェンダーのクールな水色バイザー
            ctx.strokeStyle = '#00f2fe';
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            ctx.moveTo(28, 40);
            ctx.lineTo(52, 40);
            ctx.stroke();
        }
    } else if (face === 'worried') {
        // 時間減少（焦り）：困り眉、波打つ口
        if (charId === 'ghost' || charId === 'crusher') {
            ctx.strokeStyle = '#fff';
            // 八の字の眉・目
            ctx.beginPath();
            ctx.moveTo(28, 36); ctx.lineTo(34, 40);
            ctx.moveTo(52, 36); ctx.lineTo(46, 40);
            ctx.stroke();
            // 困り口
            ctx.beginPath();
            ctx.arc(40, 50, 3, Math.PI, 0);
            ctx.stroke();
        } else {
            // ディフェンダー：警告色のオレンジバイザーに変化
            ctx.strokeStyle = '#ffcc00';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(30, 40);
            ctx.lineTo(50, 40);
            ctx.stroke();
        }
        // 冷や汗しずくエフェクト（1個）
        ctx.fillStyle = '#00ccff';
        ctx.beginPath();
        ctx.arc(58, 28, 2.5, 0, Math.PI*2);
        ctx.fill();
        ctx.beginPath();
        ctx.moveTo(58, 25.5);
        ctx.lineTo(56, 29);
        ctx.lineTo(60, 29);
        ctx.closePath();
        ctx.fill();
    } else {
        // 時間切れ寸前（大パニック）：白目の見開いた目、叫び口
        if (charId === 'ghost' || charId === 'crusher') {
            ctx.fillStyle = '#fff';
            ctx.beginPath();
            ctx.arc(30, 36, 4, 0, Math.PI*2);
            ctx.arc(50, 36, 4, 0, Math.PI*2);
            ctx.fill();
            ctx.fillStyle = '#000';
            ctx.beginPath();
            ctx.arc(30, 36, 1.5, 0, Math.PI*2);
            ctx.arc(50, 36, 1.5, 0, Math.PI*2);
            ctx.fill();
            // 叫ぶ丸い大きな口
            ctx.strokeStyle = '#fff';
            ctx.beginPath();
            ctx.arc(40, 50, 6, 0, Math.PI*2);
            ctx.stroke();
        } else {
            // ディフェンダー：赤色のアラームバイザー（危険状態）に変化
            ctx.strokeStyle = '#ff3b30';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(28, 40);
            ctx.lineTo(52, 40);
            ctx.stroke();
        }
        
        // 滝のように流れる左右の冷や汗滴（動的に流れ落ちるアニメーション）
        ctx.fillStyle = '#00ccff';
        const t = Date.now();
        const drip = (t / 150) % 15;
        
        ctx.beginPath();
        ctx.arc(22, 28 + drip, 2, 0, Math.PI*2);
        ctx.arc(60, 25 + drip, 2, 0, Math.PI*2);
        ctx.fill();
    }
}

/** 
 * 作成フェーズでインベントリ（持ち物）の個数を判定し、
 * 所持数が1個以上あるパーツの一覧を配列にして返します。
 * (作成者がパーツを選ぶツールバーの生成のベースになります)
 */
function getToolbarTools(state, isLocalDebug) {
    const tools = [];
    
    // ローカルデバッグモード専用の「通常壁（1マス）」ツールを先頭に追加
    if (isLocalDebug) tools.push({ id: 'wall', type: 'wall' });

    // インベントリのデータが無い場合は、キャラクタースキルの「special」のみを返します
    if (!state || !state.creator_inventory) {
        tools.push({ id: 'special', type: 'special' });
        return tools;
    }

    // 各壁テンプレートで、所持数が1個以上のものを追加
    WALL_TEMPLATES.forEach(t => {
        if (t.matrix && (state.creator_inventory[t.id] || 0) > 0) tools.push({ id: t.id, type: 'template' });
        if (t.tile === TILE.CANNON && (state.creator_inventory[t.id] || 0) > 0) tools.push({ id: t.id, type: 'single' });
        if (t.tile === TILE.SKELETON && (state.creator_inventory[t.id] || 0) > 0) tools.push({ id: t.id, type: 'single' });
        if (t.tile === TILE.STATUE && (state.creator_inventory[t.id] || 0) > 0) tools.push({ id: t.id, type: 'single' });
    });

    // 特殊効果床パーツで、所持数が1個以上のものを追加
    FLOOR_PARTS.forEach(f => {
        if ((state.creator_inventory[f.id] || 0) > 0) tools.push({ id: f.id, type: 'floor' });
    });

    // キャラクター固有スキル「special」ボタンは個数によらず最後尾に必ず追加
    tools.push({ id: 'special', type: 'special' });
    return tools;
}

/** 
 * ツールバーのカテゴリフィルター（すべて、壁、床、敵）に基づいて、
 * 表示させるべきツールの一覧を絞り込んで抽出します。
 */
function filterToolsByCategory(tools) {
    if (activeToolbarFilter === 'all') return tools;
    return tools.filter(t => {
        // キャラ固有スキル「special」ボタンのカテゴリ割り当て
        if (t.id === 'special') {
            const creatorChar = (typeof gameState !== 'undefined') 
                ? ((gameState.creator === 'host') ? gameState.host_char : gameState.client_char) 
                : 'crusher';
            // ゴーストの隠し壁は『壁』、それ以外の床系は『床』に振り分けます
            if (creatorChar === 'ghost') {
                return activeToolbarFilter === 'wall';
            } else {
                return activeToolbarFilter === 'floor';
            }
        }
        
        // 通常壁のカテゴリ振り分け
        if (t.id === 'wall') return activeToolbarFilter === 'wall';
        
        const def = getPartDefByTool(t.id);
        if (!def) return false;
        
        // 各種パーツのカテゴリマッチング
        if (activeToolbarFilter === 'enemy') return def.category === 'enemy';
        if (activeToolbarFilter === 'floor') return def.category === 'floor';
        if (activeToolbarFilter === 'wall') return def.category === 'wall' || (def.matrix && def.category !== 'enemy');
        return true;
    });
}

/** 
 * ツールボタンにマウスを載せた時に表示される説明文（ツールチップ）のテキストを返します。
 */
function getPartTooltip(tool) {
    if (tool === 'wall') return '通常壁：デバッグモード専用の1マス壁。';
    if (tool === 'special') return 'キャラクター固有の特殊パーツ（設置上限あり）。';
    const def = getPartDefByTool(tool) || FLOOR_PARTS.find(f => f.id === tool);
    return def ? def.desc : '';
}

/** 
 * フェーズ切り替え時に、タイマーの設定時間を初期値（設定値）にリセットします。
 */
function resetPhaseTimers() {
    gameState.create_time_left = GAME_CONFIG.createTimeSeconds;
    gameState.escape_time_left = GAME_CONFIG.escapeTimeSeconds;
}

/** 
 * ローカルファイル（file://）形式でブラウザ起動された場合に、
 * PHPサーバーとの通信が機能しない旨を開発者ツールに警告します。
 */
function checkProtocolWarning() {
    if (location.protocol === 'file:') {
        console.warn('file:// では通信できません。start_server.bat から http://localhost:50000 を開いてください。');
    }
}
