<?php
// ============================================
// wap.php - 功能机前端（纯 PHP，无 JS）
// 手动刷新 + 自定义昵称 + Cookie 记忆昵称
// 顶部跳转到底部按钮（锚点实现）
// ============================================
require 'config.php';

// 退出登录（保留 Cookie 昵称）
if (isset($_GET['logout'])) {
    unset($_SESSION['logged_in'], $_SESSION['username']);
    $r = sanitizeRoom($_GET['room'] ?? 'default');
    header('Location: wap.php?room=' . urlencode($r));
    exit;
}

// ---------- 登录提交 ----------
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $u = sanitizeUsername($_POST['username'] ?? '');
    if ($u === '') $u = cookieUsername();
    if (defined('PASS_HASH') && password_verify($_POST['password'], PASS_HASH)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $u;
        saveUsernameCookie($u);
        $r = sanitizeRoom($_GET['room'] ?? 'default');
        header('Location: wap.php?room=' . urlencode($r));
        exit;
    }
    $error = '密码错误';
    saveUsernameCookie($u);
}

// ---------- 未登录：WAP 登录页 ----------
if (!isLoggedIn()) {
    $r = sanitizeRoom($_GET['room'] ?? 'default');
    $prefillUser = htmlspecialchars(cookieUsername(), ENT_QUOTES, 'UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>登录</title>
    <meta http-equiv="Cache-Control" content="no-cache" />
</head>
<body>
    <p><b>🔐 私人聊天室</b></p>
    <?php if ($error !== ''): ?><p style="color:red;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
    <form method="post" action="wap.php?room=<?php echo urlencode($r); ?>">
        <p>昵称：<input type="text" name="username" size="10" maxlength="20" value="<?php echo $prefillUser; ?>"/></p>
        <p>密码：<input type="password" name="password" size="12"/></p>
        <p><input type="submit" value="进入"/></p>
    </form>
    <p><small>昵称会保存在本机，下次自动填写</small></p>
</body>
</html>
<?php
    exit;
}

// ---------- 已登录 ----------
$room = sanitizeRoom($_GET['room'] ?? 'default');

// 改名
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_username'])) {
    $u = sanitizeUsername($_POST['new_username']);
    if ($u !== '') {
        $_SESSION['username'] = $u;
        saveUsernameCookie($u);
    }
    header('Location: wap.php?room=' . urlencode($room));
    exit;
}

// 发消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['msg'])) {
    $msg = trim($_POST['msg']);
    if ($msg !== '') {
        $msg = mb_substr($msg, 0, 200);
        saveMessage($room, $_SESSION['username'], $msg);
    }
    header('Location: wap.php?room=' . urlencode($room));
    exit;
}
// “更新消息”按钮（name=refresh）→ 不处理，直接渲染最新数据

$messages = loadMessages($room);
$recent   = array_slice($messages, -30);

$rooms = listRooms();
if (!in_array($room, $rooms)) $rooms[] = $room;
sort($rooms);

$me = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>聊天室 - <?php echo htmlspecialchars($room); ?></title>
    <meta http-equiv="Cache-Control" content="no-cache" />
</head>
<body>

<p>
    <b>房间：<?php echo htmlspecialchars($room); ?></b><br/>
    昵称：<b><?php echo $me; ?></b><br/>
    <a href="#bottom">⬇ 跳到底部</a>
    | <a href="index.php?room=<?php echo urlencode($room); ?>">电脑版</a>
    | <a href="?logout=1&amp;room=<?php echo urlencode($room); ?>">退出</a>
</p>
<hr/>

<?php if (empty($recent)): ?>
    <p>暂无消息，说两句吧。</p>
<?php else: ?>
    <?php foreach ($recent as $m): ?>
        <p>
            <small>[<?php echo htmlspecialchars($m['time']); ?>] <?php echo htmlspecialchars($m['user']); ?>:</small><br/>
            <?php echo nl2br(htmlspecialchars($m['text'], ENT_QUOTES, 'UTF-8')); ?>
        </p>
    <?php endforeach; ?>
<?php endif; ?>

<hr/>

<form method="post" action="wap.php?room=<?php echo urlencode($room); ?>">
    <input type="text" name="msg" maxlength="200" size="16"/>
    <input type="submit" value="发送"/>
</form>

<form method="post" action="wap.php?room=<?php echo urlencode($room); ?>">
    <input type="submit" name="refresh" value="🔄 更新消息"/>
</form>

<hr/>

<p><b>修改昵称：</b></p>
<form method="post" action="wap.php?room=<?php echo urlencode($room); ?>">
    <input type="text" name="new_username" maxlength="20" size="10" value="<?php echo $me; ?>"/>
    <input type="submit" value="保存"/>
</form>

<hr/>

<p><b>切换房间：</b></p>
<?php foreach ($rooms as $r): ?>
    <p>
        <a href="wap.php?room=<?php echo urlencode($r); ?>"><?php echo htmlspecialchars($r); ?><?php echo $r === $room ? '（当前）' : ''; ?></a>
    </p>
<?php endforeach; ?>

<form method="get" action="wap.php">
    <p>新房间：<input type="text" name="room" maxlength="20" size="10"/>
    <input type="submit" value="进入"/></p>
</form>

<!-- 页面底部锚点：顶部“⬇ 跳到底部”会跳到这里 -->
<p><a name="bottom" id="bottom"></a></p>
<p><small>— 已到底部 —</small></p>

</body>
</html>