<?php
// ============================================
// api.php - 聊天接口（仅正常前端使用）
// 返回 JSON：get / post / rooms
// ============================================
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

// 未登录一律拒绝
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$room   = sanitizeRoom($_REQUEST['room'] ?? 'default');

// ---------- 拉取消息（支持增量） ----------
if ($action === 'get') {
    $messages = loadMessages($room);
    $total    = count($messages);
    $since    = isset($_GET['since']) ? max(0, intval($_GET['since'])) : 0;

    // since > 0 时只返回增量，首次加载返回最近 50 条全量
    $new = ($since > 0 && $since <= $total) ? array_slice($messages, $since) : [];

    echo json_encode([
        'ok'       => true,
        'total'    => $total,
        'messages' => array_slice($messages, -50),
        'new'      => $new,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- 发送消息 ----------
if ($action === 'post') {
    $msg = trim($_POST['msg'] ?? '');
    if ($msg === '') {
        echo json_encode(['ok' => false, 'msg' => '空消息'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $msg = mb_substr($msg, 0, 500);
    saveMessage($room, $_SESSION['username'], $msg);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- 房间列表 ----------
if ($action === 'rooms') {
    echo json_encode(['ok' => true, 'rooms' => listRooms()], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作'], JSON_UNESCAPED_UNICODE);