<?php
require_once __DIR__ . '/config.php';
requireLogin();
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];
$pageTitle = ($isRtl ? 'الرسائل — ' : 'Messages — ') . APP_NAME;

// ── JOIN community rooms if not already in them ──────────────
$roleMap = [
    'teacher' => ['all_teachers','all_users'],
    'parent'  => ['all_parents','all_users'],
    'admin'   => ['all_teachers','all_parents','all_users','admin_broadcast'],
];
$allowed = $roleMap[$role] ?? ['all_users'];
$placeholders = implode(',', array_fill(0, count($allowed), '?'));
$communityConvs = $pdo->prepare(
    "SELECT id FROM conversations WHERE community_type IN ($placeholders) AND type='community' AND is_active=1"
);
$communityConvs->execute($allowed);
foreach ($communityConvs->fetchAll() as $conv) {
    $pdo->prepare("INSERT IGNORE INTO conversation_participants (conversation_id, user_id) VALUES (?,?)")
        ->execute([$conv['id'], $userId]);
}

// ── Handle POST: send message ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'send') {
        $convId = (int)$_POST['conv_id'];
        $body = mb_substr(strip_tags(trim($_POST['body'] ?? '')), 0, 2000);
        if ($body && $convId) {
        $rk = 'msg_rate_'.$userId;
        if (!isset($_SESSION[$rk])) $_SESSION[$rk] = ['n'=>0,'t'=>time()+60];
        if (time() > $_SESSION[$rk]['t']) $_SESSION[$rk] = ['n'=>0,'t'=>time()+60];
        if ($_SESSION[$rk]['n'] >= 10) { echo json_encode(['ok'=>false,'error'=>'Rate limit exceeded']); exit; }
        $_SESSION[$rk]['n']++;

            // Verify participant
            $ok = $pdo->prepare("SELECT id FROM conversation_participants WHERE conversation_id=? AND user_id=?");
            $ok->execute([$convId, $userId]);
            if ($ok->fetch()) {
                $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)")
                    ->execute([$convId, $userId, $body]);
                $msgId = $pdo->lastInsertId();
                $pdo->prepare("UPDATE conversations SET last_message_at=NOW() WHERE id=?")
                    ->execute([$convId]);

                // Notify offline participants via email queue
                $participants = $pdo->prepare(
                    "SELECT u.id, u.email, u.full_name FROM conversation_participants cp
                     JOIN users u ON u.id=cp.user_id
                     WHERE cp.conversation_id=? AND cp.user_id!=?"
                );
                $participants->execute([$convId, $userId]);
                $convName = $pdo->prepare("SELECT name_en, name_ar FROM conversations WHERE id=?");
                $convName->execute([$convId]);
                $conv = $convName->fetch();
                $cName = $isRtl ? ($conv['name_ar'] ?? $conv['name_en']) : $conv['name_en'];
                $senderName = $_SESSION['user']['full_name'];
                $preview = mb_substr($body, 0, 100) . (mb_strlen($body) > 100 ? '...' : '');

                foreach ($participants->fetchAll() as $p) {
                    $html = buildMessageEmail($p['full_name'], $senderName, $cName, $preview, $convId);
                    $pdo->prepare("INSERT INTO email_queue (to_email, to_name, subject, body_html) VALUES (?,?,?,?)")
                        ->execute([
                            $p['email'], $p['full_name'],
                            "💬 رسالة جديدة من $senderName في $cName",
                            $html
                        ]);
                }
                // Log
                $pdo->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id) VALUES (?,?,?,?)")
                    ->execute([$userId, 'send_message', 'messages', $msgId]);

                echo json_encode(['ok' => true, 'msg_id' => $msgId]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'not a participant']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'empty body']);
        }
        exit;
    }

    if ($action === 'get_messages') {
        $convId    = (int)$_POST['conv_id'];
        $afterId   = (int)($_POST['after_id'] ?? 0);
        $msgs = $pdo->prepare(
            "SELECT m.id, m.body, m.created_at,
                    u.full_name as sender_name, u.id as sender_id, u.role as sender_role
             FROM messages m
             LEFT JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id=? AND m.is_deleted=0
               AND m.id > ?
             ORDER BY m.id ASC
             LIMIT 50"
        );
        $msgs->execute([$convId, $afterId]);
        // Mark read
        $pdo->prepare("UPDATE conversation_participants SET last_read_at=NOW() WHERE conversation_id=? AND user_id=?")
            ->execute([$convId, $userId]);
        echo json_encode(['ok' => true, 'messages' => $msgs->fetchAll()]);
        exit;
    }

    if ($action === 'get_history') {
        $convId = (int)$_POST['conv_id'];
        $ok = $pdo->prepare("SELECT id FROM conversation_participants WHERE conversation_id=? AND user_id=?");
        $ok->execute([$convId, $userId]);
        if (!$ok->fetch()) { echo json_encode(['ok'=>false]); exit; }
        $msgs = $pdo->prepare(
            "SELECT m.id, m.body, m.created_at,
                    u.full_name as sender_name, u.id as sender_id, u.role as sender_role
             FROM messages m
             LEFT JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id=? AND m.is_deleted=0
             ORDER BY m.id DESC LIMIT 50"
        );
        $msgs->execute([$convId]);
        $all = array_reverse($msgs->fetchAll());
        $pdo->prepare("UPDATE conversation_participants SET last_read_at=NOW() WHERE conversation_id=? AND user_id=?")
            ->execute([$convId, $userId]);
        echo json_encode(['ok' => true, 'messages' => $all]);
        exit;
    }

    if ($action === 'create_direct') {
        $otherId = (int)$_POST['other_id'];
        // Check existing
        $existing = $pdo->prepare(
            "SELECT c.id FROM conversations c
             JOIN conversation_participants cp1 ON cp1.conversation_id=c.id AND cp1.user_id=?
             JOIN conversation_participants cp2 ON cp2.conversation_id=c.id AND cp2.user_id=?
             WHERE c.type='direct'
             LIMIT 1"
        );
        $existing->execute([$userId, $otherId]);
        $row = $existing->fetch();
        if ($row) {
            echo json_encode(['ok'=>true,'conv_id'=>$row['id'],'existing'=>true]); exit;
        }
        // Create new
        $other = $pdo->prepare("SELECT full_name FROM users WHERE id=?");
        $other->execute([$otherId]);
        $otherName = $other->fetchColumn();
        $pdo->prepare("INSERT INTO conversations (type, name_en, created_by) VALUES ('direct',?,?)")
            ->execute(["Direct: " . $_SESSION['user']['full_name'] . " & $otherName", $userId]);
        $convId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id, role) VALUES (?,?,'admin')")
            ->execute([$convId, $userId]);
        $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?,?)")
            ->execute([$convId, $otherId]);
        echo json_encode(['ok'=>true,'conv_id'=>$convId,'existing'=>false]);
        exit;
    }
}

// ── Load conversations for sidebar ──────────────────────────
$convs = $pdo->prepare(
    "SELECT c.id, c.type, c.name_en, c.name_ar, c.community_type,
            c.last_message_at,
            cp.last_read_at,
            (SELECT COUNT(*) FROM messages m
             WHERE m.conversation_id=c.id AND m.is_deleted=0
               AND m.created_at > COALESCE(cp.last_read_at,'2000-01-01')
               AND m.sender_id != ?) as unread,
            (SELECT m2.body FROM messages m2
             WHERE m2.conversation_id=c.id AND m2.is_deleted=0
             ORDER BY m2.id DESC LIMIT 1) as last_msg
     FROM conversations c
     JOIN conversation_participants cp ON cp.conversation_id=c.id AND cp.user_id=?
     WHERE c.is_active=1
     ORDER BY COALESCE(c.last_message_at,'2000-01-01') DESC"
);
$convs->execute([$userId, $userId]);
$conversations = $convs->fetchAll();

// ── Users list for direct messages ──────────────────────────
$users = $pdo->prepare(
    "SELECT id, full_name, role FROM users WHERE id!=? AND is_active=1 ORDER BY role, full_name"
);
$users->execute([$userId]);
$allUsers = $users->fetchAll();

// Build email helper
function buildMessageEmail($toName, $senderName, $convName, $preview, $convId): string {
    $url = BASE_URL . "/messages.php?conv=$convId";
    return "<!DOCTYPE html><html dir='rtl' lang='ar'><head><meta charset='UTF-8'>
<style>body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;direction:rtl;margin:0}
.wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.1)}
.hdr{background:linear-gradient(135deg,#1B4332,#2D6A4F);padding:28px 32px;color:#fff}
.hdr h1{margin:0;font-size:20px;color:#F4D03F}.hdr p{margin:6px 0 0;opacity:.8;font-size:13px}
.body{padding:28px 32px}.msg-box{background:#f0f9f4;border-right:4px solid #2D6A4F;border-radius:8px;padding:16px 20px;margin:16px 0}
.sender{font-weight:700;color:#1B4332;font-size:14px;margin-bottom:6px}.preview{color:#374151;font-size:14px;line-height:1.6}
.btn{display:inline-block;background:#2D6A4F;color:#fff!important;text-decoration:none;padding:12px 32px;border-radius:8px;font-size:14px;font-weight:600;margin-top:20px}
.footer{padding:14px 32px;background:#f8f9fa;text-align:center;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb}
</style></head><body><div class='wrap'>
<div class='hdr'><h1>🕌 رسالة جديدة — Digital Quran Hub</h1><p>في محادثة: $convName</p></div>
<div class='body'><p style='color:#374151'>مرحباً $toName،</p>
<div class='msg-box'><div class='sender'>$senderName</div><div class='preview'>$preview</div></div>
<a href='$url' class='btn'>عرض الرسالة</a></div>
<div class='footer'>🕌 Digital Quran Center Hub — عُمان</div>
</div></body></html>";
}

include '/var/www/html/includes/header.php';
?>
<div class="layout">
<?php include '/var/www/html/includes/sidebar.php'; ?>
<main class="main-content" style="padding:0;display:flex;height:calc(100vh - 64px)">

  <!-- ── Left: Conversation List ── -->
  <div style="width:300px;border-<?= $isRtl?'left':'right' ?>:1px solid var(--gray-100);display:flex;flex-direction:column;background:#fff;flex-shrink:0">
    <div style="padding:14px 16px;border-bottom:1px solid var(--gray-100)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <h2 style="margin:0;font-size:16px;font-weight:700;color:var(--green-dark)">
          💬 <?= $isRtl?'الرسائل':'Messages' ?>
        </h2>
        <button onclick="openNewDM()" style="background:var(--green-pale);border:none;border-radius:8px;padding:5px 10px;cursor:pointer;font-size:12px;color:var(--green-dark);font-weight:600">
          + <?= $isRtl?'جديد':'New' ?>
        </button>
      </div>
      <!-- Tabs -->
      <div style="display:flex;background:var(--gray-50);border-radius:10px;padding:3px">
        <button onclick="filterConvs('all')" id="tab-all" class="conv-tab active" style="flex:1;padding:5px;border:none;border-radius:8px;background:#fff;font-size:12px;cursor:pointer;font-weight:600;color:var(--green-dark)">
          <?= $isRtl?'الكل':'All' ?>
        </button>
        <button onclick="filterConvs('community')" id="tab-community" class="conv-tab" style="flex:1;padding:5px;border:none;border-radius:8px;background:transparent;font-size:12px;cursor:pointer;color:var(--gray-500)">
          <?= $isRtl?'مجتمع':'Community' ?>
        </button>
        <button onclick="filterConvs('direct')" id="tab-direct" class="conv-tab" style="flex:1;padding:5px;border:none;border-radius:8px;background:transparent;font-size:12px;cursor:pointer;color:var(--gray-500)">
          <?= $isRtl?'خاص':'Direct' ?>
        </button>
      </div>
    </div>

    <!-- Conversation list -->
    <div id="convList" style="flex:1;overflow-y:auto">
      <?php
      $icons = ['all_teachers'=>'👨‍🏫','all_parents'=>'👨‍👩‍👧','all_users'=>'🌐','admin_broadcast'=>'📢','class_group'=>'📚','direct'=>'💬','group'=>'👥'];
      foreach ($conversations as $c):
        $icon = $c['community_type'] ? ($icons[$c['community_type']] ?? '💬') : ($icons[$c['type']] ?? '💬');
        $name = $isRtl ? ($c['name_ar'] ?? $c['name_en']) : $c['name_en'];
        $lastMsg = $c['last_msg'] ? mb_substr($c['last_msg'],0,38).'...' : ($isRtl?'لا توجد رسائل بعد':'No messages yet');
        $unread = (int)$c['unread'];
      ?>
      <div class="conv-item" data-id="<?= $c['id'] ?>" data-type="<?= h($c['type']) ?>"
           onclick="openConv(<?= $c['id'] ?>, '<?= addslashes($name) ?>')"
           style="padding:12px 16px;cursor:pointer;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;gap:10px;transition:background .15s">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
          <?= $icon ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:600;font-size:13px;color:var(--green-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px"><?= h($name) ?></span>
            <?php if ($unread > 0): ?>
            <span style="background:var(--green-main);color:#fff;border-radius:99px;font-size:10px;font-weight:700;padding:1px 6px;min-width:18px;text-align:center"><?= $unread ?></span>
            <?php endif; ?>
          </div>
          <div style="font-size:11px;color:var(--gray-500);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($lastMsg) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Right: Chat Area ── -->
  <div style="flex:1;display:flex;flex-direction:column;background:var(--gray-50)">
    <!-- Empty state -->
    <div id="emptyState" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--gray-300)">
      <div style="font-size:64px;margin-bottom:16px">🕌</div>
      <div style="font-size:18px;font-weight:600;color:var(--gray-500)"><?= $isRtl?'اختر محادثة للبدء':'Select a conversation' ?></div>
      <div style="font-size:13px;margin-top:6px;color:var(--gray-300)"><?= $isRtl?'تواصل مع المعلمين وأولياء الأمور':'Connect with teachers and parents' ?></div>
    </div>

    <!-- Chat view (hidden initially) -->
    <div id="chatArea" style="display:none;flex:1;flex-direction:column;height:100%">
      <!-- Chat header -->
      <div style="padding:14px 20px;background:#fff;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;gap:12px">
        <div id="chatIcon" style="width:38px;height:38px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:20px">💬</div>
        <div>
          <div id="chatTitle" style="font-weight:700;font-size:15px;color:var(--green-dark)"></div>
          <div id="chatMeta" style="font-size:11px;color:var(--gray-500)"></div>
        </div>
      </div>

      <!-- Messages -->
      <div id="msgList" style="flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:8px">
      </div>

      <!-- Typing indicator -->
      <div id="typingIndicator" style="display:none;padding:6px 20px;font-size:12px;color:var(--gray-500);font-style:italic"></div>

      <!-- Input -->
      <div style="padding:12px 16px;background:#fff;border-top:1px solid var(--gray-100);display:flex;gap:10px;align-items:flex-end">
        <textarea id="msgInput" placeholder="<?= $isRtl?'اكتب رسالتك...':'Type a message...' ?>"
          rows="1"
          style="flex:1;border:1px solid var(--gray-100);border-radius:12px;padding:10px 14px;font-size:14px;resize:none;outline:none;font-family:inherit;max-height:120px;line-height:1.5;direction:<?= $isRtl?'rtl':'ltr' ?>;background:var(--gray-50)"
          onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
        <button onclick="sendMsg()" id="sendBtn"
          style="width:42px;height:42px;border-radius:50%;background:var(--green-main);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;flex-shrink:0;transition:background .2s">
          <?= $isRtl ? '←' : '→' ?>
        </button>
      </div>
    </div>
  </div>
</main>

<!-- New DM Modal -->
<div id="dmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;display:none;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:380px;max-height:80vh;overflow-y:auto;box-shadow:var(--shadow-lg)">
    <h3 style="margin:0 0 16px;color:var(--green-dark)"><?= $isRtl?'رسالة مباشرة':'Direct Message' ?></h3>
    <input id="userSearch" placeholder="<?= $isRtl?'ابحث عن مستخدم...':'Search user...' ?>" oninput="filterUsers(this.value)"
      style="width:100%;padding:8px 12px;border:1px solid var(--gray-100);border-radius:8px;margin-bottom:12px;font-family:inherit;font-size:14px">
    <div id="userList">
      <?php foreach ($allUsers as $u):
        $roleLabel = ['teacher'=>'👨‍🏫 Teacher','parent'=>'👨‍👩‍👧 Parent','admin'=>'⚙️ Admin'][$u['role']] ?? $u['role'];
      ?>
      <div class="user-item" data-name="<?= strtolower(h($u['full_name'])) ?>"
           onclick="startDM(<?= $u['id'] ?>, '<?= addslashes($u['full_name']) ?>')"
           style="padding:10px 12px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .15s"
           onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:16px"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
        <div>
          <div style="font-weight:600;font-size:13px;color:var(--dark)"><?= h($u['full_name']) ?></div>
          <div style="font-size:11px;color:var(--gray-500)"><?= $roleLabel ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <button onclick="closeDM()" style="margin-top:12px;width:100%;padding:8px;background:var(--gray-50);border:none;border-radius:8px;cursor:pointer;font-family:inherit;font-size:14px;color:var(--gray-700)">
      <?= $isRtl?'إلغاء':'Cancel' ?>
    </button>
  </div>
</div>

<script>
let currentConvId = null;
let lastMsgId = 0;
let pollTimer = null;
const isRtl = <?= $isRtl ? 'true' : 'false' ?>;

const ROLE_ICONS = {'all_teachers':'👨‍🏫','all_parents':'👨‍👩‍👧','all_users':'🌐','admin_broadcast':'📢','direct':'💬','group':'👥'};

function openConv(id, name) {
    currentConvId = id;
    lastMsgId = 0;
    document.getElementById('emptyState').style.display = 'none';
    const ca = document.getElementById('chatArea');
    ca.style.display = 'flex';
    ca.style.flexDirection = 'column';
    ca.style.height = '100%';
    document.getElementById('chatTitle').textContent = name;
    document.getElementById('msgList').innerHTML = '';

    // Highlight active
    document.querySelectorAll('.conv-item').forEach(el => {
        el.style.background = el.dataset.id == id ? 'var(--green-pale)' : '';
    });

    loadHistory();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(pollNew, 2000);
    document.getElementById('msgInput').focus();
}

async function loadHistory() {
    const fd = new FormData();
    fd.append('action','get_history');
    fd.append('conv_id', currentConvId);
    const r = await fetch(window.location.href, {method:'POST',body:fd});
    const d = await r.json();
    if (d.ok && d.messages.length) {
        d.messages.forEach(m => appendMsg(m));
        lastMsgId = d.messages[d.messages.length-1].id;
        scrollBottom();
    }
}

async function pollNew() {
    if (!currentConvId) return;
    const fd = new FormData();
    fd.append('action','get_messages');
    fd.append('conv_id', currentConvId);
    fd.append('after_id', lastMsgId);
    const r = await fetch(window.location.href, {method:'POST',body:fd});
    const d = await r.json();
    if (d.ok && d.messages.length) {
        d.messages.forEach(m => appendMsg(m));
        lastMsgId = d.messages[d.messages.length-1].id;
        scrollBottom();
        // Update conversation list last msg
        updateConvPreview(currentConvId, d.messages[d.messages.length-1].body);
    }
}

function appendMsg(m) {
    const isMe = m.sender_id == <?= $userId ?>;
    const list = document.getElementById('msgList');
    const time = new Date(m.created_at).toLocaleTimeString(isRtl?'ar-OM':'en-GB',{hour:'2-digit',minute:'2-digit'});
    const div = document.createElement('div');
    div.style.cssText = `display:flex;flex-direction:${isMe?'row-reverse':'row'};gap:8px;align-items:flex-end;margin-bottom:2px`;
    div.innerHTML = `
      ${!isMe ? `<div style="width:30px;height:30px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;font-weight:700;color:var(--green-dark)">${(m.sender_name||'?')[0].toUpperCase()}</div>` : ''}
      <div style="max-width:68%">
        ${!isMe ? `<div style="font-size:10px;color:var(--gray-500);margin-bottom:2px;${isRtl?'margin-right:4px':'margin-left:4px'}">${m.sender_name||''}</div>` : ''}
        <div style="padding:9px 13px;border-radius:${isMe?'16px 4px 16px 16px':'4px 16px 16px 16px'};background:${isMe?'var(--green-main)':'#fff'};color:${isMe?'#fff':'var(--dark)'};font-size:14px;line-height:1.5;box-shadow:var(--shadow-sm);border:${isMe?'none':'1px solid var(--gray-100)'}">
          ${escHtml(m.body)}
        </div>
        <div style="font-size:10px;color:var(--gray-300);margin-top:2px;text-align:${isMe?'right':'left'}">${time}</div>
      </div>`;
    list.appendChild(div);
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

async function sendMsg() {
    const inp = document.getElementById('msgInput');
    const body = inp.value.trim();
    if (!body || !currentConvId) return;
    inp.value = '';
    inp.style.height = 'auto';
    const fd = new FormData();
    fd.append('action','send');
    fd.append('conv_id', currentConvId);
    fd.append('body', body);
    const r = await fetch(window.location.href, {method:'POST',body:fd});
    await r.json();
    await pollNew();
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
}
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}
function scrollBottom() {
    const ml = document.getElementById('msgList');
    ml.scrollTop = ml.scrollHeight;
}
function updateConvPreview(id, text) {
    const item = document.querySelector(`.conv-item[data-id="${id}"] div div:last-child`);
    if (item) item.textContent = text.substring(0,38)+'...';
}

function filterConvs(type) {
    document.querySelectorAll('.conv-tab').forEach(t => {
        t.style.background = 'transparent'; t.style.color='var(--gray-500)'; t.style.fontWeight='400';
    });
    const active = document.getElementById('tab-'+type);
    active.style.background='#fff'; active.style.color='var(--green-dark)'; active.style.fontWeight='700';
    document.querySelectorAll('.conv-item').forEach(el => {
        el.style.display = (type==='all' || el.dataset.type===type) ? 'flex' : 'none';
    });
}

function openNewDM() {
    document.getElementById('dmModal').style.display = 'flex';
    document.getElementById('userSearch').focus();
}
function closeDM() { document.getElementById('dmModal').style.display = 'none'; }

async function startDM(otherId, name) {
    closeDM();
    const fd = new FormData();
    fd.append('action','create_direct');
    fd.append('other_id', otherId);
    const r = await fetch(window.location.href, {method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) {
        location.reload(); // Reload to show new conversation
    }
}

function filterUsers(q) {
    document.querySelectorAll('.user-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q.toLowerCase()) ? 'flex' : 'none';
    });
}

// Hover effects
document.querySelectorAll('.conv-item').forEach(el => {
    el.addEventListener('mouseenter', function() { if(this.dataset.id != currentConvId) this.style.background='var(--gray-50)'; });
    el.addEventListener('mouseleave', function() { if(this.dataset.id != currentConvId) this.style.background=''; });
});

// Auto-open first conv if ?conv= param
const urlParams = new URLSearchParams(window.location.search);
const convParam = urlParams.get('conv');
if (convParam) {
    const el = document.querySelector(`.conv-item[data-id="${convParam}"]`);
    if (el) el.click();
}
</script>

<?php include '/var/www/html/includes/footer.php'; ?>
