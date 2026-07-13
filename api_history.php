<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// データベース設定（バックアップ用）
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
        // データベース接続試行（エラーが起きてもファイルの読み書きは続行する）
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_TIMEOUT => 1,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `$dbname`");
        
        $createHistoryTable = "
        CREATE TABLE IF NOT EXISTS match_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player_name VARCHAR(50) NOT NULL,
            opponent_name VARCHAR(50) NOT NULL,
            result VARCHAR(10) NOT NULL,
            rounds INT NOT NULL,
            played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createHistoryTable);
        $dbConnected = true;
    } catch (PDOException $e) {
        // DB接続エラーは無視してファイルのみで動かす
        $dbConnected = false;
    }
} else {
    $dbConnected = false;
}

// データ保存用フォルダの作成
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$historyFile = $dataDir . '/result.txt';

// ファイルが存在しない場合は初期化
if (!file_exists($historyFile)) {
    file_put_contents($historyFile, "[result]\n");
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // 戦歴の保存
    $input = json_decode(file_get_contents('php://input'), true);
    $playerName = isset($input['player_name']) ? trim($input['player_name']) : '';
    $opponentName = isset($input['opponent_name']) ? trim($input['opponent_name']) : '';
    $result = isset($input['result']) ? trim($input['result']) : ''; // win, lose, draw
    $rounds = isset($input['rounds']) ? (int)$input['rounds'] : 0;
    $playedAt = date('Y-m-d H:i:s');

    if (empty($playerName) || empty($opponentName) || empty($result)) {
        echo json_encode(["status" => "error", "message" => "Missing required fields."]);
        exit;
    }

    // 1. result.txt ファイルへ保存
    $newLine = sprintf("%s,%s,%s,%d,%s\n", 
        escapeCsvField($playerName), 
        escapeCsvField($opponentName), 
        escapeCsvField($result), 
        $rounds, 
        $playedAt
    );
    file_put_contents($historyFile, $newLine, FILE_APPEND);

    // 2. MySQL データベースへバックアップ保存（接続されている場合のみ）
    if ($dbConnected && $pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO match_history (player_name, opponent_name, result, rounds, played_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$playerName, $opponentName, $result, $rounds, $playedAt]);
        } catch (PDOException $ex) {
            // DBへの保存失敗は無視
        }
    }

    echo json_encode(["status" => "success", "message" => "History saved to file."]);

} else if ($method === 'GET') {
    // 戦歴の取得
    $historyData = [];

    // 1. result.txt から戦歴を読み込み
    if (file_exists($historyFile)) {
        $content = file_get_contents($historyFile);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === '[result]') {
                continue;
            }
            // CSV風にパースします
            $fields = str_getcsv($line);
            if (count($fields) >= 4) {
                $historyData[] = [
                    "player_name" => $fields[0],
                    "opponent_name" => $fields[1],
                    "result" => $fields[2],
                    "rounds" => (int)$fields[3],
                    "played_at" => isset($fields[4]) ? $fields[4] : date('Y-m-d H:i:s')
                ];
            }
        }
    }
    
    // 最新のものが上に来るように逆順にします
    $historyData = array_reverse($historyData);

    echo json_encode(["status" => "success", "data" => $historyData]);
} else {
    echo json_encode(["status" => "error", "message" => "Unsupported request method."]);
}

// CSVのフィールドエスケープ用ヘルパー
function escapeCsvField($field) {
    // カンマや改行、ダブルクォーテーションが含まれる場合はエスケープします
    if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false || strpos($field, "\r") !== false) {
        $field = '"' . str_replace('"', '""', $field) . '"';
    }
    return $field;
}
?>
