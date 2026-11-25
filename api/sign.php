<?php
/**
 * 手机手写签名页（扫码进入）：sign.php?t=<签名令牌>
 * 独立于 JSON 接口入口，签名提交后图片保存到 uploads/sign/ 并与目标绑定：
 *   - 委托单（commission.sign_token）→ 写 commission.sign_img（只允许签一次）
 *   - 系统账号（sys_user.sign_token）→ 写 sys_user.sign（可重复签名覆盖）
 */
require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';

$token = isset($_GET['t']) ? preg_replace('/[^a-f0-9]/i', '', (string) $_GET['t']) : '';

/** 按令牌查找签名目标：先委托单，后系统账号 */
function sign_find_target($token)
{
    if ($token === '') return null;
    $c = q_one(
        'SELECT id, code, cust_name, status, sign_img, sign_time FROM commission WHERE sign_token = ? AND is_del = 0 LIMIT 1',
        [$token]
    );
    if ($c) return ['type' => 'commission', 'row' => $c];
    $u = q_one(
        'SELECT id, account, name, unit_name, sign, sign_time, sign_pending FROM sys_user WHERE sign_token = ? AND is_del = 0 LIMIT 1',
        [$token]
    );
    if ($u) return ['type' => 'user', 'row' => $u];
    return null;
}

/* ---------------- 数据接口：info 查询 / save 保存（返回 JSON） ---------------- */
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $target = sign_find_target($token);
    if (!$target) {
        echo json_encode(['code' => 404, 'message' => '签名链接无效或已过期，请回到电脑端重新获取二维码'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $row = $target['row'];
    $isUser = $target['type'] === 'user';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'code' => $isUser ? $row['name'] : $row['code'],
                'custName' => $isUser ? (string) $row['unit_name'] : (string) $row['cust_name'],
                'signed' => $isUser
                    ? (!empty($row['sign']) && (int) $row['sign_pending'] === 0)
                    : !empty($row['sign_img']),
                'hasSign' => $isUser ? !empty($row['sign']) : !empty($row['sign_img']),
                'signTime' => (string) ($isUser ? $row['sign_time'] : $row['sign_time']),
                'target' => $target['type'],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 保存签名（委托单只允许签一次；系统账号允许重签覆盖）
    if (!$isUser && !empty($row['sign_img'])) {
        echo json_encode(['code' => 1, 'message' => '该委托单已完成签名，请勿重复提交'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $body = json_decode(file_get_contents('php://input'), true);
    $img = isset($body['image']) ? (string) $body['image'] : '';
    $prefix = 'data:image/png;base64,';
    if (strpos($img, $prefix) !== 0) {
        echo json_encode(['code' => 1, 'message' => '签名数据格式错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $data = base64_decode(substr($img, strlen($prefix)));
    if ($data === false || strlen($data) < 500) {
        echo json_encode(['code' => 1, 'message' => '请先在白板上手写签名'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $dir = __DIR__ . '/uploads/sign';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $file = ($isUser ? 'sign_user_' . $row['id'] : 'sign_' . $row['id']) . '_' . date('Ymd_His') . '.png';
    if (file_put_contents($dir . '/' . $file, $data) === false) {
        echo json_encode(['code' => 1, 'message' => '签名保存失败，请重试'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($isUser) {
        q_update('sys_user', [
            'sign'         => 'uploads/sign/' . $file,
            'sign_time'    => date('Y-m-d H:i:s'),
            'sign_pending' => 0,
        ], 'id = ?', [$row['id']]);
        // 重签覆盖：删除该账号上一张签名图，避免文件堆积
        $old = (string) $row['sign'];
        if ($old !== '' && strpos($old, 'uploads/sign/') === 0) {
            $oldFile = __DIR__ . '/' . $old;
            if (is_file($oldFile)) @unlink($oldFile);
        }
    } else {
        q_update('commission', [
            'sign_img'  => 'uploads/sign/' . $file,
            'sign_time' => date('Y-m-d H:i:s'),
            // 签名完成后：等待签名 → 等待预检
            'status'    => $row['status'] === 'sign' ? 'pending' : $row['status'],
        ], 'id = ?', [$row['id']]);
    }
    echo json_encode(['code' => 0, 'message' => 'ok', 'data' => ['saved' => true]], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<?php
/* 按令牌预判签名目标，标题/提示语区分委托单与系统账号 */
$htmlTarget = sign_find_target($token);
$isUserPage = $htmlTarget && $htmlTarget['type'] === 'user';
$pageTitle  = $isUserPage ? '系统账号手写签名确认' : '委托单手写签名确认';
$pageTip    = $isUserPage ? '请在下方白板区域内手写签名，签名将与该系统账号绑定' : '请在下方白板区域内手写签名，签名将与该委托单绑定';
?>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title><?php echo $pageTitle; ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  body { font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f5f6f8; color: #333; }
  .head { background: #1a6fe8; color: #fff; padding: 14px 16px; }
  .head h1 { font-size: 17px; font-weight: 600; }
  .head p { font-size: 13px; opacity: .85; margin-top: 4px; }
  .wrap { padding: 12px; }
  .tip { font-size: 13px; color: #888; margin-bottom: 8px; }
  .board { position: relative; background: #fff; border: 1px solid #dcdfe6; border-radius: 8px; overflow: hidden; }
  canvas { display: block; width: 100%; touch-action: none; }
  .btns { display: flex; gap: 10px; margin-top: 14px; }
  .btn { flex: 1; height: 44px; border: 0; border-radius: 8px; font-size: 16px; }
  .btn-clear { background: #fff; color: #666; border: 1px solid #dcdfe6; }
  .btn-ok { background: #1a6fe8; color: #fff; }
  .btn:disabled { opacity: .5; }
  .mask { position: fixed; inset: 0; background: rgba(255,255,255,.96); display: none; align-items: center; justify-content: center; flex-direction: column; padding: 24px; text-align: center; z-index: 9; }
  .mask .icon { font-size: 52px; margin-bottom: 14px; }
  .mask .msg { font-size: 16px; line-height: 1.7; color: #333; white-space: pre-line; }
  .mask.err .icon { color: #e6a23c; }
  .mask.ok .icon { color: #18a058; }
</style>
</head>
<body>
  <div class="head">
    <h1><?php echo $pageTitle; ?></h1>
    <p id="info">加载中…</p>
  </div>
  <div class="wrap">
    <div class="tip" id="tip"><?php echo $pageTip; ?></div>
    <div class="board"><canvas id="pad"></canvas></div>
    <div class="btns">
      <button class="btn btn-clear" id="clearBtn" type="button">清除重签</button>
      <button class="btn btn-ok" id="okBtn" type="button">确认提交</button>
    </div>
  </div>
  <div class="mask" id="mask"><div class="icon" id="mIcon">✅</div><div class="msg" id="mMsg"></div></div>

<script>
var TOKEN = new URLSearchParams(location.search).get('t') || '';
var canvas = document.getElementById('pad');
var ctx = canvas.getContext('2d');
var drawing = false, hasInk = false, submitting = false;

function show(icon, msg, cls) {
  var m = document.getElementById('mask');
  document.getElementById('mIcon').textContent = icon;
  document.getElementById('mMsg').textContent = msg;
  m.className = 'mask show ' + (cls || '');
  m.style.display = 'flex';
}

function fit() {
  var w = canvas.parentNode.clientWidth;
  var h = Math.max(260, Math.round(window.innerHeight * 0.45));
  var dpr = window.devicePixelRatio || 1;
  // 保留已有笔迹
  var old = hasInk ? canvas.toDataURL() : null;
  canvas.width = w * dpr;
  canvas.height = h * dpr;
  canvas.style.height = h + 'px';
  ctx.scale(dpr, dpr);
  ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#1a1a1a';
  if (old) {
    var img = new Image();
    img.onload = function () { ctx.drawImage(img, 0, 0, w, h); };
    img.src = old;
  }
}

function pos(e) {
  var r = canvas.getBoundingClientRect();
  return { x: e.clientX - r.left, y: e.clientY - r.top };
}

canvas.addEventListener('pointerdown', function (e) {
  drawing = true; canvas.setPointerCapture(e.pointerId);
  var p = pos(e);
  ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p.x + 0.1, p.y + 0.1); ctx.stroke();
});
canvas.addEventListener('pointermove', function (e) {
  if (!drawing) return;
  hasInk = true;
  var p = pos(e);
  ctx.lineTo(p.x, p.y); ctx.stroke();
});
['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) {
  canvas.addEventListener(ev, function () { drawing = false; });
});

document.getElementById('clearBtn').addEventListener('click', function () {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  hasInk = false;
});

document.getElementById('okBtn').addEventListener('click', function () {
  if (submitting) return;
  if (!hasInk) { alert('请先手写签名再提交'); return; }
  submitting = true;
  this.disabled = true; this.textContent = '提交中…';
  fetch(location.pathname + '?t=' + TOKEN + '&action=save', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ image: canvas.toDataURL('image/png') })
  }).then(function (r) { return r.json(); }).then(function (res) {
    if (res.code === 0) {
      show('✅', '签名提交成功！\n请返回电脑端继续后续操作。', 'ok');
    } else {
      show('⚠️', res.message || '提交失败，请重试', 'err');
      document.getElementById('okBtn').disabled = false;
      document.getElementById('okBtn').textContent = '确认提交';
    }
  }).catch(function () {
    show('⚠️', '网络异常，请检查网络后重试', 'err');
    document.getElementById('okBtn').disabled = false;
    document.getElementById('okBtn').textContent = '确认提交';
  }).finally(function () { submitting = false; });
});

fetch(location.pathname + '?t=' + TOKEN + '&action=info')
  .then(function (r) { return r.json(); })
  .then(function (res) {
    if (res.code !== 0) { show('⚠️', res.message || '签名链接无效', 'err'); return; }
    var d = res.data;
    var isUser = d.target === 'user';
    document.getElementById('info').textContent = (isUser ? '账号：' : '委托单号：') + d.code + (d.custName ? '　' + d.custName : '');
    if (d.hasSign) {
      if (isUser) {
        // 系统账号支持重签覆盖：只做文字提示，不遮挡画板
        document.getElementById('tip').textContent =
          '该账号已于 ' + d.signTime + ' 签过名，重新手写提交后会覆盖原签名';
        document.getElementById('okBtn').textContent = '重新提交（覆盖原签名）';
      } else {
        show('✅', '该委托单已完成签名（' + d.signTime + '）\n无需重复签名。', 'ok');
      }
    }
  })
  .catch(function () { show('⚠️', '网络异常，签名链接可能无效', 'err'); });

fit();
window.addEventListener('resize', fit);
</script>
</body>
</html>
