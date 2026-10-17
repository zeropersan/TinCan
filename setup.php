<?php
// ============================================
// setup.php - 首次初始化密码（设置完成后请删除）
// ============================================
require 'config.php';

$msg  = '';
$done = false;

if (file_exists(PASS_FILE)) {
    $msg = '密码已设置。如需重置，请先删除 data/auth_pass.php，再刷新本页。';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = $_POST['p1'] ?? '';
    $p2 = $_POST['p2'] ?? '';

    if (strlen($p1) < 4) {
        $msg = '密码至少 4 位';
    } elseif ($p1 !== $p2) {
        $msg = '两次输入的密码不一致';
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $content = "<?php\nif (!defined('PASS_HASH')) {\n    define('PASS_HASH', '" . $hash . "');\n}\n";
        if (@file_put_contents(PASS_FILE, $content) !== false) {
            $done = true;
            $msg  = '设置成功！请立即删除 setup.php，然后访问 index.php 或 wap.php。';
        } else {
            $msg = '写入失败，请检查 data/ 目录权限（755 或 777）。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>初始化密码</title>
<style>
body{font-family:sans-serif;background:#f0f2f5;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
.box{background:#fff;padding:30px;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.1);width:300px}
h2{margin:0 0 16px;text-align:center;font-size:20px;color:#333}
input{width:100%;padding:10px;box-sizing:border-box;border:1px solid #ccc;border-radius:6px;margin-bottom:10px;font-size:15px}
button{width:100%;padding:10px;background:#1677ff;color:#fff;border:none;border-radius:6px;font-size:16px;cursor:pointer}
.msg{font-size:14px;margin-bottom:14px;text-align:center;color:#c33;line-height:1.5}
.msg.ok{color:#2a2}
a{color:#1677ff}
</style>
</head>
<body>
<div class="box">
    <h2>初始化访问密码</h2>
    <div class="msg <?php echo $done ? 'ok' : ''; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php if (!$done && !file_exists(PASS_FILE)): ?>
    <form method="post">
        <input type="password" name="p1" placeholder="设置密码（至少 4 位）" required>
        <input type="password" name="p2" placeholder="再次输入密码" required>
        <button type="submit">保存密码</button>
    </form>
    <?php else: ?>
    <p style="text-align:center"><a href="index.php">前往聊天室 →</a></p>
    <?php endif; ?>
</div>
</body>
</html>