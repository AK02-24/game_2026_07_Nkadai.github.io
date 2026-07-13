<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$settingFile = $dataDir . '/setting.txt';

// デフォルト設定
$defaultSettings = [
    'volume' => '50',
    'bgm_volume' => '35',
    'control_type' => 'wasd_arrow', // 'wasd_arrow', 'wasd', 'arrow', 'custom'
    'key_up' => 'w',
    'key_down' => 's',
    'key_left' => 'a',
    'key_right' => 'd',
    'key_ability' => 'space'
];

// ファイルがない場合は作成
if (!file_exists($settingFile)) {
    writeSettings($settingFile, $defaultSettings);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $settings = readSettings($settingFile, $defaultSettings);
    echo json_encode(["status" => "success", "data" => $settings]);
} else if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(["status" => "error", "message" => "Invalid JSON input."]);
        exit;
    }
    
    $settings = readSettings($settingFile, $defaultSettings);
    foreach ($defaultSettings as $key => $val) {
        if (isset($input[$key])) {
            $settings[$key] = strval($input[$key]);
        }
    }
    
    writeSettings($settingFile, $settings);
    echo json_encode(["status" => "success", "message" => "Settings saved.", "data" => $settings]);
} else {
    echo json_encode(["status" => "error", "message" => "Unsupported method."]);
}

// 設定ファイルを読み込む関数
function readSettings($file, $defaults) {
    $settings = $defaults;
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '[') {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            if (array_key_exists($key, $defaults)) {
                $settings[$key] = $val;
            }
        }
    }
    return $settings;
}

// 設定ファイルに書き込む関数
function writeSettings($file, $settings) {
    $content = "[setting]\n";
    foreach ($settings as $key => $val) {
        $content .= "$key=$val\n";
    }
    file_put_contents($file, $content);
}
?>
