<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';

if (
    empty($_SESSION['isloggedin']) ||
    $_SESSION['isloggedin'] !== true ||
    empty($_SESSION['login_token'])
) {
    session_unset();
    session_destroy();
    header('Location: ../Auth/sign_in.php');
    exit;
}

// ── AJAX: mark single notification as read ────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'mark_read' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $s = $conn->prepare("UPDATE `notifications` SET `msg_read` = 'yes' WHERE `notification_id` = ? AND `user_id` = ?");
    $s->bind_param("ii", $id, $_SESSION['user_id']);
    $s->execute();
    $s->close();
    echo json_encode(['success' => true]);
    exit;
}

// ── AJAX: mark all notifications as read ──────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $s = $conn->prepare("UPDATE `notifications` SET `msg_read` = 'yes' WHERE `user_id` = ?");
    $s->bind_param("i", $_SESSION['user_id']);
    $s->execute();
    $s->close();
    echo json_encode(['success' => true]);
    exit;
}

// ── AJAX: Delete notification ──────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'dismiss' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $s = $conn->prepare("DELETE FROM `notifications` WHERE `notification_id` = ? AND `user_id` = ?");
    $s->bind_param("ii", $id, $_SESSION['user_id']);
    $s->execute();
    $s->close();
    echo json_encode(['success' => true]);
    exit;
}

$stmtSelect = $conn->prepare("SELECT `notification_id` FROM `notifications`
                                            WHERE `user_id` = ?");
$stmtSelect->bind_param("i", $_SESSION['user_id']);
$stmtSelect->execute();
$resSelect = $stmtSelect->get_result()->fetch_assoc();



// ── Fetch logged-in user ──────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM `user_master` WHERE `user_id` = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user_detials = $stmt->get_result()->fetch_assoc();
$name = explode(" ", $user_detials['full_name']);
$stmt->close();

// ── Fetch this user's notifications (newest first) ────────────────────────────
$stmt = $conn->prepare("SELECT * FROM `notifications` WHERE `user_id` = ? ORDER BY `time` DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Helpers ───────────────────────────────────────────────────────────────────
function detect_type(string $msg): string
{
    if (preg_match('/cancel|fail|error|block|suspend|unauthori|unknown|danger|urgent/i', $msg)) return 'danger';
    if (preg_match('/approv|complet|success|paid|confirm|verified|resolv|accept/i', $msg))      return 'success';
    if (preg_match('/pending|renew|expir|warn|remind|due|overdue|review/i', $msg))              return 'warn';
    if (preg_match('/session|book|meeting|request|schedul|new/i', $msg))                        return 'info';
    return 'system';
}

function type_icon(string $type): string
{
    return match ($type) {
        'danger'  => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'success' => '<polyline points="20 6 9 17 4 12"/>',
        'warn'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'info'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 3.56 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        default   => '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
    };
}

function type_label(string $type): string
{
    return match ($type) {
        'danger'  => 'Alert',
        'success' => 'Success',
        'warn'    => 'Reminder',
        'info'    => 'Session',
        default   => 'System',
    };
}

function human_time(string $ts_str): string
{
    $ts   = strtotime($ts_str);
    $diff = time() - $ts;
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
    if ($diff < 172800) return 'Yesterday, ' . date('h:i A', $ts);
    return date('d M Y, h:i A', $ts);
}

// ── Group by date ─────────────────────────────────────────────────────────────
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$groups    = ['Today' => [], 'Yesterday' => [], 'Older' => []];
foreach ($notifications as $n) {
    $d = date('Y-m-d', strtotime($n['time']));
    if ($d === $today)         $groups['Today'][]     = $n;
    elseif ($d === $yesterday) $groups['Yesterday'][] = $n;
    else                       $groups['Older'][]     = $n;
}

$unread_count = count(array_filter($notifications, fn($n) => $n['msg_read'] === 'no'));
?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../includes/scripts/common.php'; ?>
    <link rel="stylesheet" href="../assets/styles/user/common.css">
    <script src="../assets/js/tailwind.js.php" defer></script>
    <link rel="stylesheet" href="../assets/styles/user/notifications.css">

</head>

<body class="font-sans bg-page-light text-ink-light overflow-x-hidden dark:bg-page-dark dark:text-ink-dark transition-colors duration-300 relative">

    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>
    <?php include_once __DIR__ . '/../animated-bg.php'; ?>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="lg:ml-64 min-h-screen relative z-10">

        <!-- Top Bar -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-card-dark/80 backdrop-blur-xl border-b border-royal-basic/10 dark:border-royal-violet/20 transition-colors duration-300">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center gap-3">
                    <button onclick="openMobileSidebar()" class="lg:hidden p-2 rounded-xl bg-royal-basic/5 dark:bg-royal-soft/10 hover:bg-royal-basic/10 dark:hover:bg-royal-soft/15 text-royal-basic dark:text-royal-soft transition-all duration-200">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                    </button>
                    <div>
                        <h1 class="font-display font-bold text-xl sm:text-2xl text-royal-basic dark:text-royal-soft">Notifications</h1>
                        <p class="text-xs sm:text-sm text-muted-light dark:text-muted-dark hidden sm:block">Check the updates you missed!</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Dark Mode Toggle -->
                    <div id="toggleWrap" class="relative cursor-pointer rounded-full bg-slate-300 dark:bg-royal-DEFAULT transition-colors duration-400" style="width:52px;height:28px;" onclick="toggleTheme()" role="button" aria-label="Toggle dark mode" tabindex="0">
                        <div id="toggleKnob" class="absolute top-0.5 left-0.5 rounded-full bg-white shadow-knob flex items-center justify-center transition-transform duration-300" style="width:22px;height:22px;">
                            <svg id="iconSun" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5" />
                                <line x1="12" y1="1" x2="12" y2="3" />
                                <line x1="12" y1="21" x2="12" y2="23" />
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                                <line x1="1" y1="12" x2="3" y2="12" />
                                <line x1="21" y1="12" x2="23" y2="12" />
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                            </svg>
                            <svg id="iconMoon" class="w-3.5 h-3.5 hidden" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                            </svg>
                        </div>
                    </div>
                    <!-- Profile -->
                    <div class="relative hidden sm:block">
                        <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2 sm:gap-3 p-2 pr-3 rounded-xl hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10 transition-all duration-200">
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-white font-semibold text-sm" style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
                                <?= htmlspecialchars($name[0][0] . ($name[1][0] ?? '')) ?>
                            </div>
                            <svg class="w-4 h-4 text-muted-light dark:text-muted-dark hidden sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white dark:bg-card-dark rounded-2xl shadow-dropdown border border-royal-basic/10 dark:border-royal-violet/20 overflow-hidden animate-scale-in opacity-0">
                            <div class="p-4 border-b border-royal-basic/10 dark:border-royal-violet/20">
                                <p class="font-semibold text-sm"><?= htmlspecialchars($user_detials['full_name']) ?></p>
                                <p class="text-xs text-muted-light dark:text-muted-dark">@<?= htmlspecialchars($user_detials['user_name']) ?></p>
                            </div>
                            <div class="p-2">
                                <a href="profile.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10 transition-colors">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                    <span class="text-sm">View Profile</span>
                                </a>
                            </div>
                            <div class="p-2 border-t border-royal-basic/10 dark:border-royal-violet/20">
                                <button id="logoutButton" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 transition-colors">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                        <polyline points="16 17 21 12 16 7" />
                                        <line x1="21" y1="12" x2="9" y2="12" />
                                    </svg>
                                    <span class="text-sm font-semibold">Logout</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ════════════ NOTIFICATIONS FEED ════════════ -->
        <div class="notif-feed">

            <div class="notif-header-row">
                <div class="flex items-center gap-3">
                    <span style="font-size:1rem;font-weight:800;">All Notifications</span>
                    <span class="unread-count-badge" id="unreadBadge" style="<?= $unread_count === 0 ? 'display:none' : '' ?>"><?= $unread_count ?></span>
                </div>
                <?php if ($unread_count > 0): ?>
                    <button class="btn-mark-all" id="markAllBtn" onclick="markAllRead()">Mark all as read</button>
                <?php endif; ?>
            </div>

            <div class="filter-bar">
                <button class="filter-tab active" onclick="filterNotifs(this,'all')">All</button>
                <button class="filter-tab" onclick="filterNotifs(this,'unread')">Unread</button>
                <button class="filter-tab" onclick="filterNotifs(this,'info')">Sessions</button>
                <button class="filter-tab" onclick="filterNotifs(this,'success')">Success</button>
                <button class="filter-tab" onclick="filterNotifs(this,'warn')">Reminders</button>
                <button class="filter-tab" onclick="filterNotifs(this,'danger')">Alerts</button>
                <button class="filter-tab" onclick="filterNotifs(this,'system')">System</button>
            </div>

            <?php
            $card_index     = 0;
            $total_rendered = 0;
            foreach ($groups as $group_label => $group_notifs):
                if (empty($group_notifs)) continue;
                $total_rendered += count($group_notifs);
            ?>
                <div class="section-label" <?= $card_index > 0 ? 'style="margin-top:1.5rem;"' : '' ?>>
                    <?= htmlspecialchars($group_label) ?>
                </div>

                <?php foreach ($group_notifs as $n):
                    $type    = detect_type($n['msg']);
                    $is_read = $n['msg_read'] === 'yes';
                    $time    = human_time($n['time']);
                    // First sentence becomes the title
                    preg_match('/^[^.!?]+[.!?]?/', $n['msg'], $m);
                    $title = trim($m[0] ?? $n['msg']);
                ?>
                    <div class="notif-card <?= $is_read ? '' : 'unread' ?>"
                        data-type="<?= $type ?>"
                        data-read="<?= $is_read ? 'true' : 'false' ?>"
                        data-id="<?= (int)$n['notification_id'] ?>"
                        style="--i:<?= $card_index ?>">

                        <div class="notif-icon type-<?= $type ?>">
                            <?php if (!$is_read): ?>
                                <div class="unread-dot"></div>
                            <?php endif; ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <?= type_icon($type) ?>
                            </svg>
                        </div>

                        <div class="notif-body" onclick="markRead(this)">
                            <p class="notif-title"><?= htmlspecialchars($title) ?></p>
                            <p class="notif-text"><?= htmlspecialchars($n['msg']) ?></p>
                            <div class="notif-meta">
                                <span class="notif-time"><?= $time ?></span>
                                <span class="notif-tag tag-<?= $type ?>"><?= type_label($type) ?></span>
                            </div>
                        </div>

                        <div class="notif-actions">
                            <button class="btn-dismiss" onclick="dismissNotif(this)" title="Dismiss">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php $card_index++;
                endforeach; ?>
            <?php endforeach; ?>

            <div class="empty-state <?= $total_rendered === 0 ? 'visible' : '' ?>" id="emptyState">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                </div>
                <h3>All caught up!</h3>
                <p>You have no notifications right now.</p>
            </div>

        </div>
    </main>

    <script src="../assets/js/dashboard.js.php" defer></script>
    <script src="../assets/js/darkmodeToggle.js.php" defer></script>

    <script>
        const PAGE_URL = window.location.pathname;

        /* ── Filter ── */
        function filterNotifs(btn, type) {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');
            let visible = 0;
            document.querySelectorAll('.notif-card').forEach(card => {
                const matchType = type === 'all' || card.dataset.type === type;
                const matchRead = type !== 'unread' || card.dataset.read === 'false';
                const show = matchType && matchRead;
                card.style.display = show ? 'flex' : 'none';
                if (show) visible++;
            });
            document.getElementById('emptyState').classList.toggle('visible', visible === 0);
        }

        /* ── Mark single as read → updates DB ── */
        function markRead(bodyEl) {
            const card = bodyEl.closest('.notif-card');
            if (card.dataset.read === 'true') return;

            // Optimistic UI update
            card.dataset.read = 'true';
            card.classList.remove('unread');
            card.querySelector('.unread-dot')?.remove();
            updateBadge();

            // Persist to DB
            fetch(PAGE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=mark_read&id=${card.dataset.id}`
            });
        }

        /* ── Mark all as read → updates DB ── */
        function markAllRead() {
            document.querySelectorAll('.notif-card.unread').forEach(card => {
                card.dataset.read = 'true';
                card.classList.remove('unread');
                card.querySelector('.unread-dot')?.remove();
            });
            updateBadge();

            // Hide the button itself
            const btn = document.getElementById('markAllBtn');
            if (btn) btn.style.display = 'none';

            fetch(PAGE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=mark_all_read'
            });
        }

        /* ── Dismiss: mark read in DB + animate out ── */
        function dismissNotif(btn) {
            const card = btn.closest('.notif-card');
            const wasUnread = card.dataset.read === 'false';

            if (wasUnread) {
                fetch(PAGE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=mark_read&id=${card.dataset.id}`
                });
            }

            card.classList.add('removing');
            setTimeout(() => {
                card.remove();
                if (wasUnread) updateBadge();
                checkEmpty();
            }, 320);

            fetch(PAGE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=dismiss&id=${card.dataset.id}`
            });
        }

        /* ── Update unread badge count ── */
        function updateBadge() {
            const count = document.querySelectorAll('.notif-card[data-read="false"]').length;
            const badge = document.getElementById('unreadBadge');
            badge.textContent = count;
            badge.style.display = count === 0 ? 'none' : 'inline-flex';
        }

        /* ── Show empty state if no visible cards ── */
        function checkEmpty() {
            const visible = [...document.querySelectorAll('.notif-card')]
                .filter(c => c.style.display !== 'none').length;
            document.getElementById('emptyState').classList.toggle('visible', visible === 0);
        }
    </script>
</body>

</html>