/**
 * game-data.js
 * 
 * ゲーム内のデータ定義（タイル、キャラクター、壁テンプレート、床パーツなど）および
 * それらに関連する共通の計算関数・UI生成関数をまとめています。
 */

/**
 * タイル種別コード（マップ上の各マスの状態を表す数値）
 * 0: 空白, 1: 壁, 2: スタート, 3: ゴール
 * 4以降は特殊な床や敵、ギミックを表します。
 */
const TILE = {
    EMPTY: 0,       // 空き地（通路）
    WALL: 1,        // 通常の壁
    START: 2,       // スタート位置
    GOAL: 3,        // ゴール位置
    CRUSH: 4,       // クラッシュ床（クラッシャーの能力：一度通ると壁になる）
    HIDDEN: 5,      // 隠し壁（ゴーストの能力：見た目は壁だが通り抜け可能）
    DARK: 6,        // 暗闇床（ディフェンダーの能力：踏むと視界が狭まる）※DAMAGEと同じ値で定義されています
    DAMAGE: 6,      // ダメージ床
    CONVEYOR: 7,    // ベルトコンベヤー床（乗ると自動で流される）
    TRAMPOLINE: 8,  // トランポリン床（乗ると2マス先へ跳ぶ）
    CONFUSION: 9,   // 混乱床（乗ると操作方法が上下左右反転する）
    CANNON: 10,     // 砲台壁（定期的にレーザービームを射出する壁）
    SKELETON: 11,   // スケルトン（敵：往復移動する）
    STATUE: 12      // 石像（敵：プレイヤーの移動を真似て動く）
};

/** 
 * プレイヤーが上を歩いて通過できる床タイルのリスト
 */
const FLOOR_TILES = [TILE.CRUSH, TILE.DAMAGE, TILE.CONVEYOR, TILE.TRAMPOLINE, TILE.CONFUSION];

/** 
 * プレイヤーが通常通り抜けることのできない「進入不可の壁」かどうかを判定します。
 * (隠し壁である TILE.HIDDEN(5) は通り抜けられるため、ここには含まれません)
 */
function isBlockingWall(tile) {
    return tile === TILE.WALL || tile === TILE.CANNON;
}

/** 
 * 通常の通路やスタート・ゴール以外の「床・敵・特殊オブジェクト」のタイルであるかを判定します。
 */
function isSpecialTile(tile) {
    return tile >= TILE.CRUSH;
}

/**
 * プレイアブルキャラクターの定義リスト
 * 新しいキャラクターを追加したい場合は、この配列オブジェクトにデータを追加するだけで
 * 自動的にキャラクター選択画面などのUIに反映されます。
 */
const CHARACTERS = [
    {
        id: 'crusher',
        name: 'CRUSHER',
        japanese: 'クラッシャー',
        color: '#ff3b30', // イメージカラー（赤）
        img: 'assets/characters/crusher.svg',
        desc: '作成時: 進入不可になるクラッシュ床を2枚配置可能。<br>脱出時: 能力キー/隣接クリックで壁を1回だけ破壊して進める。'
    },
    {
        id: 'ghost',
        name: 'GHOST',
        japanese: 'ゴースト',
        color: '#af52de', // イメージカラー（紫）
        img: 'assets/characters/ghost.svg',
        desc: '作成時: 通り抜け可能な隠し壁を2枚配置可能。<br>脱出時: 能力キー/隣接クリックで壁を1回すり抜けて移動できる。'
    },
    {
        id: 'defender',
        name: 'DEFENDER',
        japanese: 'ディフェンダー',
        color: '#007aff', // イメージカラー（青）
        img: 'assets/characters/defender.svg',
        desc: '作成時: 踏むと20秒間周囲5×5マス内しか見えなくなる「暗闇床」を1枚配置可能。<br>脱出時: 能力キーで迷路内ダメージを1度だけ無効化できる。'
    }
];

/**
 * 迷路作成時に配置可能な「壁パーツ」の定義リスト
 * 複数マスの組み合わせである「テンプレートパーツ」と、
 * 単一マスのギミック（スケルトン、石像、砲台壁）が登録されています。
 */
const WALL_TEMPLATES = [
    { id: 'i_shape', name: 'I型ブロック', color: '#00f2fe', category: 'wall', desc: '5マスの直線壁。', img: 'assets/parts/wall_i.svg', matrix: [[1, 1, 1, 1, 1]] },
    { id: 'cross_shape', name: '十字型ブロック', color: '#00ffcc', category: 'wall', desc: '中央から十字に広がる5マスの壁。通路を4方向に分断するのに最適。', img: 'assets/parts/wall_cross.svg', matrix: [[0, 1, 0], [1, 1, 1], [0, 1, 0]] },
    { id: 'l_shape', name: 'L型ブロック', color: '#f355da', category: 'wall', desc: 'L字の壁パーツ。', img: 'assets/parts/wall_l.svg', matrix: [[1, 0, 0], [1, 0, 0], [1, 1, 1]] },
    { id: 'l_shape_2', name: 'L型ブロック2', color: '#f355da', category: 'wall', desc: '角を曲がる壁と、その反対側にぽつんと浮かぶ1マスの壁からなる特殊なL字。', img: 'assets/parts/wall_l2.svg', matrix: [[1, 0, 1], [1, 0, 0], [1, 1, 1]] },
    { id: 't_shape', name: 'T型ブロック', color: '#fff200', category: 'wall', desc: 'T字の壁パーツ。', img: 'assets/parts/wall_t.svg', matrix: [[1, 1, 1], [0, 1, 0], [0, 1, 0]] },
    { id: 'circular_shape', name: '円型ブロック', color: '#ffcc00', category: 'wall', desc: '5×5マスの強固な外周と中央にコアを持つ超大型ブロック。巨大な砦を作れる。', img: 'assets/parts/wall_circular.svg', matrix: [[1, 1, 0, 1, 1], [1, 0, 0, 0, 1], [1, 0, 1, 0, 1], [1, 0, 0, 0, 1], [1, 1, 0, 1, 1]] },
    { id: 'square_shape', name: '四角ブロック', color: '#39ff14', category: 'wall', desc: '2×2の四角壁。', img: 'assets/parts/wall_square.svg', matrix: [[1, 1], [1, 1]] },
    { id: 'h_shape', name: 'H型ブロック', color: '#39ff14', category: 'wall', desc: 'H字の壁パーツ。', img: 'assets/parts/wall_h.svg', matrix: [[1, 0, 1], [1, 1, 1], [1, 0, 1]] },
    { id: 'left_diagonal_shape', name: '左斜めブロック', color: '#39ff14', category: 'wall', desc: '左下がり斜めの壁。', img: 'assets/parts/wall_ldiag.svg', matrix: [[0, 0, 1], [0, 1, 0], [1, 0, 0]] },
    { id: 'right_diagonal_shape', name: '右斜めブロック', color: '#39ff14', category: 'wall', desc: '右下がり斜めの壁。', img: 'assets/parts/wall_rdiag.svg', matrix: [[1, 0, 0], [0, 1, 0], [0, 0, 1]] },
    { id: 'bridge_shape', name: '橋型ブロック', color: '#39ff14', category: 'wall', desc: '上下2本の長い壁。', img: 'assets/parts/wall_bridge.svg', matrix: [[1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [1, 1, 1, 1, 1]] },
    
    // 単一マスの敵や砲台
    { id: 'skeleton', name: 'スケルトン', color: '#cccccc', category: 'enemy', desc: '直進し壁で反転する敵。接触でダメージ。1〜3体。', img: 'assets/parts/enemy_skeleton.svg', maxCount: 3, tile: TILE.SKELETON },
    { id: 'statue', name: '石像', color: '#888888', category: 'enemy', desc: '脱出者の移動方向と同じ方向に動く敵。0〜2体。', img: 'assets/parts/enemy_statue.svg', maxCount: 2, tile: TILE.STATUE },
    { id: 'cannon', name: '砲台壁', color: '#ff6600', category: 'wall', desc: '一定間隔でビームを発射。1〜3個。', img: 'assets/parts/wall_cannon.svg', maxCount: 3, tile: TILE.CANNON, needsDir: true }
];

/**
 * 迷路作成時に配置可能な「特殊効果床パーツ（1マス）」の定義リスト
 */
const FLOOR_PARTS = [
    { id: 'conveyor', name: 'ベルトコンベヤー', color: '#00ccff', category: 'floor', desc: '向き方向へ1マスずつ押し出す床。', img: 'assets/parts/floor_conveyor.svg', tile: TILE.CONVEYOR, needsDir: true },
    { id: 'trampoline', name: 'トランポリン床', color: '#ff66cc', category: 'floor', desc: '向き方向へ2マス跳ぶ。着地が壁なら手前で止まる。', img: 'assets/parts/floor_trampoline.svg', tile: TILE.TRAMPOLINE, needsDir: true },
    { id: 'confusion', name: '混乱床', color: '#cc66ff', category: 'floor', desc: '踏むと操作が左右反転になる。', img: 'assets/parts/floor_confusion.svg', tile: TILE.CONFUSION }
];

/** 
 * 全パーツの統合レジストリ（説明書モーダルや図鑑UIなどの動的表示生成用）
 */
const PARTS_REGISTRY = [
    { id: 'normal_wall', name: '通常壁', category: 'wall', desc: '通行不可の基本壁。デバッグモード以外では1マス壁は使えません。', img: 'assets/parts/wall_normal.svg', color: '#00f2fe' },
    ...WALL_TEMPLATES,
    ...FLOOR_PARTS,
    { id: 'crush_floor', name: 'クラッシュ床', category: 'floor', desc: '通過後に壁化する床（クラッシャー専用）。', img: 'assets/parts/floor_crush.svg', color: '#ff9500' },
    { id: 'hidden_wall', name: '隠し壁', category: 'wall', desc: '見た目は壁だが通れる（ゴースト専用）。', img: 'assets/parts/wall_hidden.svg', color: '#af52de' },
    { id: 'dark_floor', name: '暗闇床', category: 'floor', desc: '踏むと20秒間周囲5×5マス内しか見えなくなる床（ディフェンダー専用 / 1個まで）。', img: 'assets/parts/floor_dark.svg', color: '#2a085c' }
];

/** 
 * 4つの方向（上・下・左・右）に対応する移動ベクトルと、Canvas描画時の回転角度（ラジアン）の定義
 */
const DIR_VECTORS = {
    right: { dx: 1, dy: 0, angle: 0 },
    down: { dx: 0, dy: 1, angle: Math.PI / 2 },
    left: { dx: -1, dy: 0, angle: Math.PI },
    up: { dx: 0, dy: -1, angle: -Math.PI / 2 }
};

/** 向き変更時の時計回り方向の順序定義 */
const DIR_ORDER = ['right', 'down', 'left', 'up'];

/** 
 * 現在の向きを受け取り、時計回りに90度回転させた向きを返します。
 * 例: 'right' -> 'down' -> 'left' -> 'up' -> 'right'
 */
function rotateDir(dir) {
    const idx = DIR_ORDER.indexOf(dir);
    return DIR_ORDER[(idx + 1) % 4];
}

/** 
 * 特定のマスの座標 (x, y) から、メタデータ（向き情報など）を保持するマップキーを生成します。
 */
function getTileMetaKey(x, y) {
    return `${x},${y}`;
}

/** 
 * オンライン同期時に、ゲーム状態オブジェクト (state) 内の必要なデータ構造が定義されているか安全チェックし、
 * 定義されていない場合は空のオブジェクトや配列として初期化します。
 */
function ensureTileMeta(state) {
    if (!state.tile_meta) state.tile_meta = {};
    if (!state.enemies) state.enemies = [];
    if (!state.beams) state.beams = [];
    if (!state.start_pos) state.start_pos = { x: 0, y: 0 };
    if (!state.goal_pos) state.goal_pos = { x: 0, y: 0 };
}

/** 
 * マップ上に現在配置されている壁や罠などの「パーツの総数」をカウントします。
 * (最大配置可能数 50 個の上限制限チェックなどに使われます)
 */
function countPlacedParts(state) {
    let templatesCount = (state.placed_templates || []).length;
    let itemsCount = (state.placed_items || []).length;

    let wallTiles = 0;
    let specialTiles = 0;

    // マップ全体を捜査して通常壁とキャラ固有特殊マスの総数をカウント
    for (let y = 0; y < state.maze.length; y++) {
        for (let x = 0; x < state.maze[y].length; x++) {
            const t = state.maze[y][x];
            if (t === TILE.WALL) wallTiles++;
            if (t >= 4 && t <= 6) specialTiles++;
        }
    }

    // テンプレート（複数マスブロック）によって配置された壁のマス数を計算
    let templateWallTiles = 0;
    (state.placed_templates || []).forEach(pt => {
        const matrix = pt.matrix;
        for (let r = 0; r < matrix.length; r++) {
            for (let c = 0; c < matrix[r].length; c++) {
                if (matrix[r][c] === 1) {
                    templateWallTiles++;
                }
            }
        }
    });

    // テンプレートによらない単独の通常壁（デバッグモード用）の数を計算
    let singleWallsCount = Math.max(0, wallTiles - templateWallTiles);

    // すべての合計（テンプレートパーツ数 + 置かれたアイテム数 + 単体壁数 + 特殊床・壁数）
    return templatesCount + itemsCount + singleWallsCount + specialTiles;
}

/** 
 * マップ内に存在する特定の種別コード (tileCode) のマス数を数えます。
 * (敵キャラや砲台壁の上限配置数制限のために使用します)
 */
function countTileInMaze(state, tileCode) {
    let n = 0;
    for (let y = 0; y < state.maze.length; y++) {
        for (let x = 0; x < state.maze[y].length; x++) {
            if (state.maze[y][x] === tileCode) n++;
        }
    }
    return n;
}

/** 
 * マップ内に存在するキャラクター固有の特殊タイル（クラッシュ床、隠し壁、暗闇床）の総数をカウントします。
 */
function countCharSpecials(state) {
    let count = 0;
    for (let y = 0; y < state.maze.length; y++) {
        for (let x = 0; x < state.maze[y].length; x++) {
            const t = state.maze[y][x];
            if (t === TILE.CRUSH || t === TILE.HIDDEN || t === TILE.DAMAGE) count++;
        }
    }
    return count;
}

/** 
 * 2つの座標点 a(x, y) と b(x, y) の間のマンハッタン距離（斜めを含まない格子状 of 最短距離）を計算します。
 */
function manhattan(a, b) {
    return Math.abs(a.x - b.x) + Math.abs(a.y - b.y);
}

/** 
 * マップのサイズに基づいて、スタート地点とゴール地点をランダムに決定します。
 * (スタートとゴールが近すぎないように、一定値(minStartGoalDistance)以上のマンハッタン距離を確保するまで再抽選します)
 */
function pickRandomStartGoal(size) {
    const minDist = GAME_CONFIG.minStartGoalDistance;
    for (let attempt = 0; attempt < 200; attempt++) {
        const start = { x: Math.floor(Math.random() * size), y: Math.floor(Math.random() * size) };
        const goal = { x: Math.floor(Math.random() * size), y: Math.floor(Math.random() * size) };
        if (manhattan(start, goal) >= minDist) {
            return { start, goal };
        }
    }
    return { start: { x: 0, y: 0 }, goal: { x: size - 1, y: size - 1 } };
}

/** 
 * ターン開始時に、迷路作成者が使用できる壁やギミックパーツの所持数を
 * ランダムに選定してインベントリ（持ち物リスト）オブジェクトを作成します。
 */
function generateRandomInventory() {
    const inventory = {};
    
    // 各壁テンプレートの個数をランダム決定
    WALL_TEMPLATES.forEach(temp => {
        if (temp.matrix) {
            inventory[temp.id] = Math.floor(Math.random() * (GAME_CONFIG.maxPartCount - GAME_CONFIG.minPartCount + 1)) + GAME_CONFIG.minPartCount;
        }
    });
    
    // 特殊床パーツの個数をランダム決定
    FLOOR_PARTS.forEach(fp => {
        inventory[fp.id] = Math.floor(Math.random() * (GAME_CONFIG.maxPartCount - GAME_CONFIG.minPartCount + 1)) + GAME_CONFIG.minPartCount;
    });
    
    // 敵キャラと砲台の最大配置可能個数をランダムに設定
    inventory.skeleton = Math.floor(Math.random() * GAME_CONFIG.skeletonMax) + 1;
    inventory.statue = Math.floor(Math.random() * GAME_CONFIG.statueMax) + 1;
    inventory.cannon = Math.floor(Math.random() * GAME_CONFIG.cannonMax) + 1;
    
    return inventory;
}

/** 
 * キャラクター図鑑モーダル（ポップアップ窓）用のキャラクターリストUIを動的に生成します。
 */
function buildCharactersModal() {
    const container = document.getElementById('characters-modal-list');
    if (!container) return;
    container.innerHTML = CHARACTERS.map(ch => `
        <div class="list-item-card char-modal-item" data-char="${ch.id}" style="border-left: 4px solid ${ch.color}; cursor:pointer;">
            <div style="display:flex; gap:1rem; align-items:flex-start;">
                <img src="${ch.img}" alt="${ch.name}" style="width:72px; height:72px; border-radius:12px; background:rgba(0,0,0,0.3);">
                <div style="flex:1; text-align:left;">
                    <h4 style="color:${ch.color}; margin:0 0 0.4rem 0;">${ch.name} (${ch.japanese})</h4>
                    <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.5; margin:0;">${ch.desc.replace(/<br>/g, '<br>')}</p>
                </div>
            </div>
            <div class="char-detail-preview" id="char-detail-${ch.id}" style="display:none; margin-top:0.8rem; padding:0.8rem; border-radius:8px; background:rgba(255,255,255,0.03);">
                <img src="${ch.img}" alt="" style="width:120px; height:120px; margin:0 auto; display:block;">
                <p style="font-size:0.8rem; color:var(--text-muted); margin-top:0.5rem; text-align:center;">${ch.desc.replace(/<br>/g, ' / ')}</p>
            </div>
        </div>
    `).join('');

    // クリックしたキャラクターの詳細表示をトグル展開するイベントリスナー
    container.querySelectorAll('.char-modal-item').forEach(el => {
        el.addEventListener('click', () => {
            const id = el.dataset.char;
            container.querySelectorAll('.char-detail-preview').forEach(p => p.style.display = 'none');
            const preview = document.getElementById(`char-detail-${id}`);
            if (preview) preview.style.display = 'block';
        });
    });
}

/** 
 * パーツ図鑑モーダル用のアイテムリストUIを動的に生成します。
 */
function buildPartsModal() {
    const container = document.getElementById('parts-modal-list');
    if (!container) return;
    container.innerHTML = PARTS_REGISTRY.map(p => `
        <div class="list-item-card" style="border-left: 4px solid ${p.color || '#00f2fe'};">
            <div style="display:flex; gap:0.8rem; align-items:center;">
                <img src="${p.img}" alt="${p.name}" style="width:48px; height:48px; object-fit:contain;" onerror="this.style.display='none'">
                <div style="text-align:left;">
                    <h4 style="margin:0;">${p.name}</h4>
                    <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.4; margin:0.3rem 0 0 0;">${p.desc}</p>
                </div>
            </div>
        </div>
    `).join('');
}

/** 
 * キャラクター選択カードに登録されている画像パスを最新のものに同期して表示させます。
 */
function syncCharacterImages() {
    CHARACTERS.forEach(ch => {
        const cardImg = document.querySelector(`#card-${ch.id} .avatar-img`);
        if (cardImg) cardImg.src = ch.img;
    });
    if (typeof previewCharIndex === 'number') {
        const char = CHARACTERS[previewCharIndex];
        const previewImg = document.getElementById('preview-char-img');
        if (previewImg && char) previewImg.src = char.img;
    }
}

/** 
 * 起動時に一度だけ実行され、すべての図鑑モーダルのUI表示の初期作成および画像の同期を行います。
 */
function initRegistryUI() {
    buildCharactersModal();
    buildPartsModal();
    syncCharacterImages();
}
