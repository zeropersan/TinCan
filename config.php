<?php
// ============================================
// config.php - 全局配置与工具函数
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_DIR', __DIR__);
define('ROOM_DIR', BASE_DIR . '/rooms/');
define('DATA_DIR', BASE_DIR . '/data/');
define('PASS_FILE', DATA_DIR . 'auth_pass.php');
define('MAX_MESSAGES', 300);
define('USERNAME_COOKIE', 'chat_username');
define('USERNAME_COOKIE_DAYS', 365);

// 自动创建目录
if (!is_dir(ROOM_DIR)) { @mkdir(ROOM_DIR, 0755, true); }
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0755, true); }

// 密码文件不存在 → 引导到初始化页面
if (!file_exists(PASS_FILE)) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script !== 'setup.php') {
        header('Location: setup.php');
        exit;
    }
} else {
    require_once PASS_FILE;
}

// ---------- 登录状态 ----------
function isLoggedIn() {
    return !empty($_SESSION['logged_in']);
}

// ---------- 房间名过滤 ----------
function sanitizeRoom($name) {
    $name = trim((string)$name);
    if ($name === '') return 'default';
    $name = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]/u', '', $name);
    if ($name === '') return 'default';
    if (mb_strlen($name) > 30) $name = mb_substr($name, 0, 30);
    return $name;
}

function roomFile($room) {
    return ROOM_DIR . $room . '.json';
}

// ---------- 用户名过滤 ----------
function sanitizeUsername($name) {
    $name = trim((string)$name);
    if ($name === '') return '';
    $name = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9_\-]/u', '', $name);
    if ($name === '') return '';
    if (mb_strlen($name) > 20) $name = mb_substr($name, 0, 20);
    return $name;
}

function randomUsername() {
    return '游客' . rand(1000, 9999);
}

// 读取浏览器保存的用户名（Cookie），没有则生成随机并写回
function cookieUsername() {
    $u = sanitizeUsername($_COOKIE[USERNAME_COOKIE] ?? '');
    if ($u !== '') return $u;
    $u = randomUsername();
    saveUsernameCookie($u);
    return $u;
}

// 写入用户名 Cookie（默认 365 天）
function saveUsernameCookie($name) {
    $expires = time() + 86400 * USERNAME_COOKIE_DAYS;
    if (PHP_VERSION_ID >= 70300) {
        setcookie(USERNAME_COOKIE, $name, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie(USERNAME_COOKIE, $name, $expires, '/', '', !empty($_SERVER['HTTPS']), true);
    }
    // 同步更新本次请求内可用的 $_COOKIE 数组
    $_COOKIE[USERNAME_COOKIE] = $name;
}

// ---------- 读取消息 ----------
function loadMessages($room) {
    $file = roomFile($room);
    if (!file_exists($file)) return [];

    $fp = @fopen($file, 'r');
    if (!$fp) return [];

    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// ---------- 保存消息 ----------
function saveMessage($room, $user, $text) {
    $file = roomFile($room);
    $messages = loadMessages($room);

    $messages[] = [
        'user' => $user,
        'text' => $text,
        'time' => date('H:i'),
    ];

    if (count($messages) > MAX_MESSAGES) {
        $messages = array_slice($messages, -MAX_MESSAGES);
    }

    $json = json_encode($messages, JSON_UNESCAPED_UNICODE);

    $fp = @fopen($file, 'c+');
    if (!$fp) return false;

    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

// ---------- 房间列表 ----------
function listRooms() {
    $rooms = [];
    $files = glob(ROOM_DIR . '*.json');
    if ($files) {
        foreach ($files as $f) {
            $rooms[] = basename($f, '.json');
        }
    }
    sort($rooms);
    return $rooms;
}