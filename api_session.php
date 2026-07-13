<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// データベース設定
$host = '127.0.0.1';
$username = 'root';
$password = '';
$dbname = 'maze_game';
$dbConnected = false;
$pdo = null;

// MySQLサーバーが起動しているか事前チェック (ポート 3306, タイムアウト 0.1秒)
$mysqlCheck = @fsockopen($host, 3306, $errno, $errstr, 0.1);
if ($mysqlCheck) {
    fclose($mysqlCheck);
    try {
        // 1. MySQL サーバーに初期接続（データベース未作成の場合に備える）
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_TIMEOUT => 1,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // データベース自動作成
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `$dbname`");
        
        // テーブル自動作成: game_sessions
        $createSessionsTable = "
        CREATE TABLE IF NOT EXISTS game_sessions (
            session_id VARCHAR(50) NOT NULL PRIMARY KEY,
            host_status VARCHAR(20) DEFAULT 'waiting',
            client_status VARCHAR(20) DEFAULT 'none',
            current_turn INT DEFAULT 1,
            game_state LONGTEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createSessionsTable);
        $dbConnected = true;
    } catch (PDOException $e) {
        // DB接続エラー時はファイルベースに切り替え（エラー出力はせずフォールバックする）
        $dbConnected = false;
    }
} else {
    $dbConnected = false;
}

// データ保存用フォルダの作成
$dataDir = __DIR__ . '/data';
$sessionsDir = $dataDir . '/sessions';
if (!is_dir($sessionsDir)) {
    mkdir($sessionsDir, 0777, true);
}

// リクエストパラメータの取得
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// ファイルベースのセッション管理用関数
function getFileSessionPath($sessionId) {
    global $sessionsDir;
    return $sessionsDir . '/' . preg_replace('/[^0-9a-zA-Z]/', '', $sessionId) . '.json';
}

function loadFileSession($sessionId) {
    $path = getFileSessionPath($sessionId);
    if (file_exists($path)) {
        $content = file_get_contents($path);
        return json_decode($content, true);
    }
    return null;
}

function saveFileSession($sessionId, $data) {
    $path = getFileSessionPath($sessionId);
    $data['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function deleteFileSession($sessionId) {
    $path = getFileSessionPath($sessionId);
    if (file_exists($path)) {
        unlink($path);
    }
}

switch ($action) {
    case 'host':
        // ホストセッション作成
        $sessionId = sprintf("%06d", mt_rand(100000, 999999));
        
        if ($dbConnected && $pdo) {
            try {
                // 重複チェック
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_sessions WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                while ($stmt->fetchColumn() > 0) {
                    $sessionId = sprintf("%06d", mt_rand(100000, 999999));
                    $stmt->execute([$sessionId]);
                }

                // 新規セッション登録
                $stmt = $pdo->prepare("INSERT INTO game_sessions (session_id, host_status, client_status, current_turn, game_state) VALUES (?, 'waiting', 'none', 1, NULL)");
                $stmt->execute([$sessionId]);

                echo json_encode(["status" => "success", "session_id" => $sessionId, "role" => "host"]);
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            // ファイルベースでセッションを作成
            $sessionFile = getFileSessionPath($sessionId);
            while (file_exists($sessionFile)) {
                $sessionId = sprintf("%06d", mt_rand(100000, 999999));
                $sessionFile = getFileSessionPath($sessionId);
            }

            $sessionData = [
                "session_id" => $sessionId,
                "host_status" => "waiting",
                "client_status" => "none",
                "current_turn" => 1,
                "game_state" => null
            ];
            saveFileSession($sessionId, $sessionData);
            echo json_encode(["status" => "success", "session_id" => $sessionId, "role" => "host"]);
        }
        break;

    case 'join':
        // クライアント参加
        $sessionId = isset($input['session_id']) ? trim($input['session_id']) : '';
        
        if ($dbConnected && $pdo) {
            try {
                if (empty($sessionId)) {
                    // セッションID指定なし：空いている（クライアント未接続の）待機セッションを探して自動マッチング
                    $stmt = $pdo->query("SELECT session_id FROM game_sessions WHERE host_status = 'waiting' AND client_status = 'none' ORDER BY updated_at DESC LIMIT 1");
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $sessionId = $row['session_id'];
                    } else {
                        echo json_encode(["status" => "error", "message" => "No waiting host found."]);
                        exit;
                    }
                }

                // セッションが存在し、空いているか確認
                $stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$session) {
                    echo json_encode(["status" => "error", "message" => "Session not found."]);
                    exit;
                }

                if ($session['client_status'] !== 'none') {
                    echo json_encode(["status" => "error", "message" => "Session is already full."]);
                    exit;
                }

                // クライアントのステータスをwaitingに更新してマッチング成立とする
                $stmt = $pdo->prepare("UPDATE game_sessions SET client_status = 'waiting' WHERE session_id = ?");
                $stmt->execute([$sessionId]);

                echo json_encode(["status" => "success", "session_id" => $sessionId, "role" => "client"]);
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            // ファイルベースで参加
            if (empty($sessionId)) {
                // 空いているセッションを検索します
                $files = glob($sessionsDir . '/*.json');
                $foundId = null;
                $latestTime = 0;
                foreach ($files as $file) {
                    $data = json_decode(file_get_contents($file), true);
                    if ($data && $data['host_status'] === 'waiting' && $data['client_status'] === 'none') {
                        $mtime = filemtime($file);
                        if ($mtime > $latestTime) {
                            $latestTime = $mtime;
                            $foundId = $data['session_id'];
                        }
                    }
                }
                
                if ($foundId) {
                    $sessionId = $foundId;
                } else {
                    echo json_encode(["status" => "error", "message" => "No waiting host found."]);
                    exit;
                }
            }

            $session = loadFileSession($sessionId);
            if (!$session) {
                echo json_encode(["status" => "error", "message" => "Session not found."]);
                exit;
            }

            if ($session['client_status'] !== 'none') {
                echo json_encode(["status" => "error", "message" => "Session is already full."]);
                exit;
            }

            $session['client_status'] = 'waiting';
            saveFileSession($sessionId, $session);

            echo json_encode(["status" => "success", "session_id" => $sessionId, "role" => "client"]);
        }
        break;

    case 'poll':
        // ポーリング（状態取得）
        $sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : (isset($input['session_id']) ? trim($input['session_id']) : '');
        if (empty($sessionId)) {
            echo json_encode(["status" => "error", "message" => "Missing session_id."]);
            exit;
        }

        if ($dbConnected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$session) {
                    echo json_encode(["status" => "error", "message" => "Session not found."]);
                } else {
                    echo json_encode([
                        "status" => "success",
                        "data" => [
                            "session_id" => $session['session_id'],
                            "host_status" => $session['host_status'],
                            "client_status" => $session['client_status'],
                            "current_turn" => (int)$session['current_turn'],
                            "game_state" => $session['game_state'] ? json_decode($session['game_state'], true) : null,
                            "updated_at" => $session['updated_at']
                        ]
                    ]);
                }
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            // ファイルベースで取得
            $session = loadFileSession($sessionId);
            if (!$session) {
                echo json_encode(["status" => "error", "message" => "Session not found."]);
            } else {
                echo json_encode([
                    "status" => "success",
                    "data" => [
                        "session_id" => $session['session_id'],
                        "host_status" => $session['host_status'],
                        "client_status" => $session['client_status'],
                        "current_turn" => (int)$session['current_turn'],
                        "game_state" => $session['game_state'],
                        "updated_at" => isset($session['updated_at']) ? $session['updated_at'] : date('Y-m-d H:i:s')
                    ]
                ]);
            }
        }
        break;

    case 'update':
        // 状態更新
        $sessionId = isset($input['session_id']) ? trim($input['session_id']) : '';
        if (empty($sessionId)) {
            echo json_encode(["status" => "error", "message" => "Missing session_id."]);
            exit;
        }

        $hostStatus = isset($input['host_status']) ? $input['host_status'] : null;
        $clientStatus = isset($input['client_status']) ? $input['client_status'] : null;
        $currentTurn = isset($input['current_turn']) ? $input['current_turn'] : null;
        $gameState = isset($input['game_state']) ? $input['game_state'] : null;

        if ($dbConnected && $pdo) {
            try {
                // 現在のセッションデータを取得
                $stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$session) {
                    echo json_encode(["status" => "error", "message" => "Session not found."]);
                    exit;
                }

                // 動的SQLの構築
                $fields = [];
                $params = [];

                if ($hostStatus !== null) {
                    $fields[] = "host_status = ?";
                    $params[] = $hostStatus;
                }
                if ($clientStatus !== null) {
                    $fields[] = "client_status = ?";
                    $params[] = $clientStatus;
                }
                if ($currentTurn !== null) {
                    $fields[] = "current_turn = ?";
                    $params[] = (int)$currentTurn;
                }
                if ($gameState !== null) {
                    $fields[] = "game_state = ?";
                    $params[] = json_encode($gameState);
                }

                if (count($fields) > 0) {
                    $params[] = $sessionId;
                    $sql = "UPDATE game_sessions SET " . implode(", ", $fields) . " WHERE session_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    echo json_encode(["status" => "success", "message" => "Session updated."]);
                } else {
                    echo json_encode(["status" => "success", "message" => "No changes made."]);
                }
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            // ファイルベースで更新
            $session = loadFileSession($sessionId);
            if (!$session) {
                echo json_encode(["status" => "error", "message" => "Session not found."]);
                exit;
            }

            if ($hostStatus !== null) {
                $session['host_status'] = $hostStatus;
            }
            if ($clientStatus !== null) {
                $session['client_status'] = $clientStatus;
            }
            if ($currentTurn !== null) {
                $session['current_turn'] = (int)$currentTurn;
            }
            if ($gameState !== null) {
                $session['game_state'] = $gameState;
            }

            saveFileSession($sessionId, $session);
            echo json_encode(["status" => "success", "message" => "Session updated in file."]);
        }
        break;

    case 'exit':
        // セッションから退出または削除
        $sessionId = isset($input['session_id']) ? trim($input['session_id']) : '';
        $role = isset($input['role']) ? trim($input['role']) : '';

        if (empty($sessionId)) {
            echo json_encode(["status" => "error", "message" => "Missing session_id."]);
            exit;
        }

        if ($dbConnected && $pdo) {
            try {
                if ($role === 'host') {
                    // ホストが抜けたらセッション自体を削除
                    $stmt = $pdo->prepare("DELETE FROM game_sessions WHERE session_id = ?");
                    $stmt->execute([$sessionId]);
                    echo json_encode(["status" => "success", "message" => "Session deleted because host exited."]);
                } else {
                    // クライアントが抜けたらclient_statusをnoneに戻す
                    $stmt = $pdo->prepare("UPDATE game_sessions SET client_status = 'none' WHERE session_id = ?");
                    $stmt->execute([$sessionId]);
                    echo json_encode(["status" => "success", "message" => "Client exited, slot is now empty."]);
                }
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            // ファイルベースで退出
            if ($role === 'host') {
                deleteFileSession($sessionId);
                echo json_encode(["status" => "success", "message" => "Session deleted from file because host exited."]);
            } else {
                $session = loadFileSession($sessionId);
                if ($session) {
                    $session['client_status'] = 'none';
                    saveFileSession($sessionId, $session);
                }
                echo json_encode(["status" => "success", "message" => "Client exited, slot is now empty in file."]);
            }
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        break;
}
?>
