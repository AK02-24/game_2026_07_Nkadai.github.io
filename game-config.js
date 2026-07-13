/**
 * game-config.js
 * 
 * ゲーム全体の基本設定（制限時間、配置できるパーツ数、ギミックの動作間隔、音源のパスなど）と、
 * UI・画面描画に関するレイアウト設定を管理しています。
 * ここにある数値を変更するだけで、プログラムの挙動やバランスを簡単に調整できます。
 */

/**
 * ゲームのパラメータ設定オブジェクト
 */
const GAME_CONFIG = {
    // --- 制限時間の設定（秒） ---
    createTimeSeconds: 95,  // 迷路を作成するフェーズの持ち時間（95秒）
    escapeTimeSeconds: 45,  // 作成された迷路を脱出するフェーズの持ち時間（45秒）

    // --- パーツ配置のルール設定 ---
    minPartCount: 2,        // ランダム配布時に、各パーツが最低何個配られるか（2個）
    maxPartCount: 6,        // ランダム配布時に、各パーツが最大何個配られるか（6個）
    maxTotalParts: 50,      // マップ上に設置できるパーツ全体の最大上限数

    // --- スタートとゴールの配置ルール ---
    minStartGoalDistance: 5, // スタートからゴールまで最低限離す必要のあるマスの数（マンハッタン距離）

    // --- ギミック・敵の自動動作間隔（ミリ秒 / 秒） ---
    conveyorMoveIntervalMs: 900,   // ベルトコンベヤー床が乗っているプレイヤーを動かす周期（900ミリ秒 = 0.9秒ごと）
    skeletonMoveIntervalMs: 800,   // 敵（スケルトン）が歩いて進む周期（800ミリ秒 = 0.8秒ごと）
    cannonFireIntervalSeconds: 3,  // 砲台壁がビームを発射する周期（3秒ごと）

    // --- 敵や特殊ギミックの最大設置制限（これ以上はマップ上に配置できません） ---
    skeletonMax: 3,         // スケルトンの最大数（3体）
    statueMax: 2,           // 石像の最大数（2体）
    cannonMax: 3,           // 砲台壁の最大数（3台）

    // --- バックエンド（PHP）のAPI接続先設定 ---
    // ※ ローカルファイルの直接実行 (file://) では通信できません。サーバー起動が必要です。
    apiSessionUrl: './api_session.php',  // セッション管理用APIのパス
    apiHistoryUrl: './api_history.php',  // 対戦戦績保存用APIのパス
    apiSettingUrl: './api_setting.php',  // 個人設定保存用APIのパス

    // --- BGM音源のファイルパス設定 ---
    // 音源ファイルが見つからない場合は、8bit風の電子音がプログラムによって自動生成されて鳴る仕様になっています。
    bgm: {
        title: 'assets/BGM/title.ogg',    // タイトル画面用のBGM
        home: 'assets/BGM/home.ogg',      // メインメニュー用のBGM
        create: 'assets/BGM/create.ogg',  // 迷路作成画面用のBGM
        escape: 'assets/BGM/escape.ogg',  // 迷路脱出画面用のBGM
        result: 'assets/BGM/result.ogg'   // 結果確認画面用のBGM
    },

    // --- 効果音（SE）音源のファイルパス設定 ---
    se: {
        confirm: 'assets/SE/confirm.wav',  // 決定・選択時の効果音
        cancel: 'assets/SE/cancel.wav',    // キャンセル・パーツ回収時の効果音
        move: 'assets/SE/move.wav',        // プレイヤー移動時の効果音
        break: 'assets/SE/break.wav',      // クラッシャーが壁を壊した時の効果音
        win: 'assets/SE/win.wav',          // ゲーム勝利時のファンファーレ
        lose: 'assets/SE/lose.wav',        // ゲーム敗北時の効果音
        damage: 'assets/SE/damage.wav',    // 敵やビームでダメージを受けた時の効果音
        beam: 'assets/SE/beam.wav',        // 砲台からビームが発射された時の効果音
        bounce: 'assets/SE/bounce.wav'     // トランポリン床で跳ねた時の効果音
    },

    // --- 音量の初期設定（0.0 〜 1.0） ---
    bgmVolumeRatio: 0.35  // BGMの音量比率（35%）。ゲームの設定画面で変更した値と連動します。
};

/**
 * 画面レイアウトおよびCanvas描画時のパラメータ設定オブジェクト
 */
const UI_LAYOUT_CONFIG = {
    canvasSize: 400,           // メインのゲーム画面（Canvas）の解像度（400x400ピクセル）
    oppWidth: 400,             // 相手画面のデフォルメ表示（横幅）
    oppHeight: 96,             // 相手画面のデフォルメ表示（縦幅）
    backOfHeadOpacity: 0.4,    // 相手が作成中の時に手前に描画される「後頭部キャラクター」の透明度 (40%)
    backOfHeadYOffset: 15,     // 後頭部キャラクターを描画する縦方向の調整値
    backOfHeadSize: 85,        // 後頭部キャラクターの描画サイズ
    expressionSize: 80,        // クイックチャット（表情感情アピール）の表示サイズ
    toolbarMaxHeight: 160,     // 画面右側の配置パーツ選択ツールバーの最大高さ（スクロール可能）
    handCircleRadius: 8,       // 感情表現アニメーションなどで手がぐるぐる回る際の半径 (px)
    handCircleSpeed: 0.012,    // 手がぐるぐる回る際のアニメーション回転速度
    backgrounds: [             // ゲーム画面の背景に使われる画像パスのリスト（ランダムで選択されます）
        'assets/bg/cyber_neon.png'
    ]
};
