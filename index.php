<?php
// ============================================
// index.php - 正常前端（电脑 / 智能手机）
// ============================================
require 'config.php';

// 退出登录（保留 Cookie 里的昵称，方便下次预填）
if (isset($_GET['logout'])) {
    unset($_SESSION['logged_in'], $_SESSION['username']);
    $r = sanitizeRoom($_GET['room'] ?? 'default');
    header('Location: index.php?room=' . urlencode($r));
    exit;
}

// ---------- 登录提交 ----------
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $u = sanitizeUsername($_POST['username'] ?? '');
    if ($u === '') $u = cookieUsername();          // 没填就沿用 Cookie
    if (defined('PASS_HASH') && password_verify($_POST['password'], PASS_HASH)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $u;
        saveUsernameCookie($u);
        $r = sanitizeRoom($_GET['room'] ?? 'default');
        header('Location: index.php?room=' . urlencode($r));
        exit;
    }
    $error = '密码错误';
    saveUsernameCookie($u);   // 即使密码错也记住这次填的昵称
}

// ---------- 改名提交（已登录） ----------
if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_username'])) {
    $u = sanitizeUsername($_POST['new_username']);
    if ($u !== '') {
        $_SESSION['username'] = $u;
        saveUsernameCookie($u);
    }
    $r = sanitizeRoom($_GET['room'] ?? 'default');
    header('Location: index.php?room=' . urlencode($r));
    exit;
}

// ---------- 未登录：登录页 ----------
if (!isLoggedIn()) {
    $roomParam   = sanitizeRoom($_GET['room'] ?? 'default');
    $prefillUser = htmlspecialchars(cookieUsername(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 - 私人聊天室</title>
<style>
body{font-family:-apple-system,"Segoe UI",Roboto,sans-serif;background:#f0f2f5;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:16px;box-sizing:border-box}
.login{background:#fff;padding:30px;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.1);width:320px;max-width:100%}
h2{margin:0 0 20px;text-align:center;color:#333;font-size:20px}
label{display:block;font-size:13px;color:#666;margin:8px 0 4px}
input{width:100%;padding:10px;box-sizing:border-box;border:1px solid #ccc;border-radius:6px;font-size:15px;outline:none}
input:focus{border-color:#1677ff}
button{width:100%;padding:10px;background:#1677ff;color:#fff;border:none;border-radius:6px;font-size:16px;cursor:pointer;margin-top:16px}
.err{color:#e33;font-size:14px;margin-bottom:10px;text-align:center}
.hint{font-size:12px;color:#999;margin-top:10px;text-align:center;line-height:1.5}
</style>
</head>
<body>
<div class="login">
    <h2>🔐 私人聊天室</h2>
    <?php if ($error): ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" action="index.php?room=<?php echo urlencode($roomParam); ?>">
        <label>昵称（可自定义，最长 20 字）</label>
        <input type="text" name="username" value="<?php echo $prefillUser; ?>" maxlength="20" placeholder="给自己取个名字">
        <label>访问密码</label>
        <input type="password" name="password" placeholder="请输入访问密码" required autofocus>
        <button type="submit">进入</button>
    </form>
    <div class="hint">昵称会保存在本浏览器，下次自动填写</div>
</div>
</body>
</html>
<?php
    exit;
}

// ---------- 已登录：聊天界面 ----------
$room  = sanitizeRoom($_GET['room'] ?? 'default');
$rooms = listRooms();
if (!in_array($room, $rooms)) $rooms[] = $room;
sort($rooms);
$me = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>聊天室 - <?php echo htmlspecialchars($room); ?></title>
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,"Segoe UI",Roboto,sans-serif;max-width:720px;margin:0 auto;padding:10px;background:#f0f2f5}
.top{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:8px}
.rooms{flex:1;min-width:200px}
.rooms a{display:inline-block;padding:4px 10px;margin:2px;background:#e6f0ff;color:#1677ff;border-radius:12px;text-decoration:none;font-size:14px}
.rooms a.active{background:#1677ff;color:#fff}
.rooms form{display:inline-block;margin-left:4px}
.rooms input[type=text]{padding:4px 8px;border:1px solid #ccc;border-radius:6px;width:110px;font-size:14px}
.rooms button{padding:4px 10px;border:none;background:#1677ff;color:#fff;border-radius:6px;cursor:pointer}
.user-area{font-size:14px;color:#555;display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.user-area b{color:#1677ff}
.user-area a{color:#1677ff;text-decoration:none;cursor:pointer}
.user-area .logout{color:#999}
.user-area .jump-btn{background:#e6f0ff;padding:3px 10px;border-radius:12px}
#rename-form{display:none;gap:6px;margin-bottom:8px;align-items:center}
#rename-form input[type=text]{flex:1;padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:14px}
#rename-form button{padding:6px 14px;background:#1677ff;color:#fff;border:none;border-radius:6px;cursor:pointer}
#msg-box{background:#fff;border-radius:10px;height:60vh;overflow-y:auto;padding:12px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.msg{margin-bottom:10px;line-height:1.45;word-break:break-word}
.meta{font-size:12px;color:#999;margin-right:6px}
.meta.mine{color:#1677ff}
.input-area{display:flex;gap:8px;margin-top:10px}
#msg-input{flex:1;padding:10px;border:1px solid #ccc;border-radius:8px;font-size:15px;outline:none}
#msg-input:focus{border-color:#1677ff}
#send-btn{padding:0 20px;border:none;background:#1677ff;color:#fff;border-radius:8px;font-size:15px;cursor:pointer}
#send-btn:disabled{background:#a0c4ff;cursor:not-allowed}
</style>
</head>
<body>

<div class="top">
    <div class="rooms">
        <?php foreach ($rooms as $r): ?>
            <a href="?room=<?php echo urlencode($r); ?>" class="<?php echo $r === $room ? 'active' : ''; ?>"><?php echo htmlspecialchars($r); ?></a>
        <?php endforeach; ?>
        <form method="get" action="index.php">
            <input type="text" name="room" placeholder="新房间名" maxlength="20">
            <button type="submit">进入</button>
        </form>
    </div>
</div>

<div class="user-area">
    <span>👤 我是 <b><?php echo $me; ?></b></span>
    <a href="#" onclick="toggleRename();return false;">✏️ 改名</a>
    <a href="#" class="jump-btn" onclick="jumpToBottom();return false;">⬇ 到底部</a>
    <a class="logout" href="?logout=1&room=<?php echo urlencode($room); ?>">退出</a>
</div>

<form id="rename-form" method="post" action="index.php?room=<?php echo urlencode($room); ?>">
    <input type="text" name="new_username" value="<?php echo $me; ?>" maxlength="20" required>
    <button type="submit">保存昵称</button>
</form>

<div id="msg-box"></div>

<div class="input-area" id="input-area">
    <input type="text" id="msg-input" placeholder="输入消息，回车发送…" maxlength="500">
    <button id="send-btn" onclick="sendMsg()">发送</button>
</div>

<script>
var room        = <?php echo json_encode($room, JSON_UNESCAPED_UNICODE); ?>;
var myName      = <?php echo json_encode($_SESSION['username'], JSON_UNESCAPED_UNICODE); ?>;
var lastTotal   = 0;
var initialized = false;
var polling     = false;

function toggleRename() {
    var f = document.getElementById('rename-form');
    f.style.display = (f.style.display === 'flex') ? 'none' : 'flex';
    if (f.style.display === 'flex') f.querySelector('input').focus();
}

// 跳到底部：消息框滚到最底 + 页面滚动到输入区 + 聚焦输入框
function jumpToBottom() {
    var box = document.getElementById('msg-box');
    box.scrollTop = box.scrollHeight;

    var area = document.getElementById('input-area');
    if (area.scrollIntoView) area.scrollIntoView();

    var input = document.getElementById('msg-input');
    input.focus();
}

function appendMsg(m) {
    var box = document.getElementById('msg-box');
    var div = document.createElement('div');
    div.className = 'msg';
    var meta = document.createElement('span');
    meta.className = 'meta' + (m.user === myName ? ' mine' : '');
    meta.textContent = '[' + m.time + '] ' + m.user + ':';
    div.appendChild(meta);
    div.appendChild(document.createTextNode(m.text));
    box.appendChild(div);
}

function poll() {
    if (polling) return;
    polling = true;
    var since = initialized ? lastTotal : 0;

    fetch('api.php?action=get&room=' + encodeURIComponent(room) + '&since=' + since)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.ok) { polling = false; return; }
            var box = document.getElementById('msg-box');
            var needReset = !initialized || data.total < lastTotal;
            if (needReset) {
                box.innerHTML = '';
                (data.messages || []).forEach(appendMsg);
                initialized = true;
            } else {
                (data.new || []).forEach(appendMsg);
            }
            lastTotal = data.total;
            box.scrollTop = box.scrollHeight;
            polling = false;
        })
        .catch(function(){ polling = false; });
}

function sendMsg() {
    var input = document.getElementById('msg-input');
    var msg = input.value.trim();
    if (!msg) return;
    var btn = document.getElementById('send-btn');
    btn.disabled = true;

    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=post&room=' + encodeURIComponent(room) + '&msg=' + encodeURIComponent(msg)
    })
    .then(function(r){ return r.json(); })
    .then(function(){
        input.value = '';
        btn.disabled = false;
        poll();
    })
    .catch(function(){ btn.disabled = false; });
}

document.getElementById('msg-input').addEventListener('keypress', function(e){
    if (e.key === 'Enter') sendMsg();
});

setInterval(poll, 3000);
poll();
</script>
</body>
</html>