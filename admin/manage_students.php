<?php
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';
require_once __DIR__ . '/validations.php';

$current_page = 'manage_students.php';

// ── Delete user ──
if (isset($_GET['remove_user']) || isset($_POST['remove_user'])) {
    $uid = isset($_POST['remove_user']) ? intval($_POST['remove_user']) : intval($_GET['remove_user']);
    
    // Use prepared statements for security
    $stmt = mysqli_prepare($conn, "DELETE FROM mcq_assignments WHERE student_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($conn, "DELETE FROM mcq_results WHERE student_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($conn, "DELETE FROM user_roles WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM meetings WHERE student_id = ? OR teacher_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $uid, $uid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM notifications WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM assignment WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM chat_history WHERE sender_id = ? OR receiver_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $uid, $uid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM user_history WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM user_master WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    
    header("Location: manage_students.php?status=removed");
    exit;
}

// ── Search & sort ──
$search = isset($_GET['q'])    ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';
$sort   = in_array($_GET['sort'] ?? '', ['full_name','credit','join_date']) ? $_GET['sort'] : 'user_id';
$dir    = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$where  = $search ? "WHERE full_name LIKE '%$search%' OR email LIKE '%$search%' OR user_name LIKE '%$search%'" : '';
$users  = mysqli_query($conn, "SELECT * FROM user_master $where ORDER BY $sort $dir");
$total  = mysqli_num_rows($users);

// ── Summary stats ──
$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(credit),0) AS credits,
            SUM(join_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS new_week
     FROM user_master"));
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Operatives · SkillSwap Admin</title>
   <?php
    include_once __DIR__ . '/../includes/scripts/common.php';
    ?>
    <script src="../assets/js/tailwind.js.php" defer></script>
  <link rel="stylesheet" href="../assets/styles/admin/common.css">
  <link rel="stylesheet" href="../assets/styles/admin/admin_tailwind_css.css">
</head>
<body class="bg-page-light text-ink-light overflow-x-hidden dark:bg-page-dark dark:text-ink-dark transition-colors duration-300 relative">

    <!-- ════════════ MOBILE OVERLAY ════════════ -->
    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>

    <?php
    include_once __DIR__ . '/../animated-bg.php';
    ?>

    <!-- ════════════ SIDEBAR ════════════ -->
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <!-- ══════════ MAIN CONTENT ════════════ -->
    <main class="min-h-screen relative z-10">

  <!-- Topbar -->
  <header class="topbar">
    <div class="flex items-center justify-between px-7 py-3.5 gap-3 flex-wrap">
      <div class="flex items-center gap-3.5">
        <button onclick="openMobileSidebar()" class="sidebar-trigger lg:hidden">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <div>
          <h1 class="font-bold leading-none tracking-tight text-royal-primary"
              style="font-family:'Playfair Display',Georgia,serif;font-size:20px">Operative Oversight</h1>
          <p class="section-label mt-1" style="letter-spacing:.1em">Manage and monitor platform participants</p>
        </div>
      </div>
      <div class="flex items-center gap-2.5">
        <?php if (isset($_GET['status'])): ?>
        <span class="badge badge-red">&#10003; Operative removed</span>
        <?php endif; ?>
        <button id="themeToggle" class="theme-toggle" onclick="toggleTheme()" title="Toggle light / dark">
          <svg id="iconSun" class="w-4 h-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="5"/>
            <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
            <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
          </svg>
          <svg id="iconMoon" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <div class="p-7 max-w-screen-xl flex flex-col gap-5">

    <!-- 3 Mini-stats -->
    <div class="enter" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
      <div class="stat-mini">
        <p class="section-label mb-2">Total Operatives</p>
        <div class="stat-num text-royal-primary"><?= number_format($stats['total']) ?></div>
      </div>
      <div class="stat-mini d1">
        <p class="section-label mb-2">Platform Credits</p>
        <div class="stat-num text-gold"><?= number_format($stats['credits']) ?></div>
      </div>
      <div class="stat-mini d2">
        <p class="section-label mb-2">New This Week</p>
        <div class="stat-num text-green">+<?= (int)$stats['new_week'] ?></div>
      </div>
    </div>

    <!-- Search bar -->
    <div class="enter d1 flex flex-wrap items-center gap-2.5">
      <form method="GET" class="flex gap-2 flex-1 min-w-[240px] items-center">
        <div class="relative flex-1 max-w-[440px]">
          <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none icon-muted"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input class="search-input" type="text" name="q"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="Search by name, email, username…">
        </div>
        <button type="submit" class="btn btn-royal">Search</button>
        <?php if ($search): ?>
        <a href="manage_students.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
      </form>
      <p class="section-label whitespace-nowrap">
        <strong class="ink-text" style="font-size:13px"><?= $total ?></strong>
        result<?= $total != 1 ? 's' : '' ?>
        <?= $search ? ' for <em class="text-blue" style="font-style:normal;font-weight:600">"' . htmlspecialchars($search) . '"</em>' : '' ?>
      </p>
    </div>

    <!-- Table -->
    <div class="ss-card enter d2 overflow-hidden">
      <div class="overflow-x-auto">
        <table>
          <thead>
            <tr>
              <th>
                <a href="?q=<?= urlencode($search) ?>&sort=full_name&dir=<?= ($sort=='full_name'&&$dir=='ASC')?'desc':'asc' ?>"
                   class="sort-link <?= $sort=='full_name'?'active':'' ?>">
                  Operative <?= $sort=='full_name' ? ($dir=='ASC'?'↑':'↓') : '' ?>
                </a>
              </th>
              <th style="display:none" class="md-col">Username</th>
              <th>
                <a href="?q=<?= urlencode($search) ?>&sort=credit&dir=<?= ($sort=='credit'&&$dir=='DESC')?'asc':'desc' ?>"
                   class="sort-link <?= $sort=='credit'?'active':'' ?>">
                  Credits <?= $sort=='credit' ? ($dir=='DESC'?'↓':'↑') : '' ?>
                </a>
              </th>
              <th style="display:none" class="lg-col">
                <a href="?q=<?= urlencode($search) ?>&sort=join_date&dir=<?= ($sort=='join_date'&&$dir=='DESC')?'asc':'desc' ?>"
                   class="sort-link <?= $sort=='join_date'?'active':'' ?>">
                  Joined <?= $sort=='join_date' ? ($dir=='DESC'?'↓':'↑') : '' ?>
                </a>
              </th>
              <th style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($total === 0): ?>
            <tr>
              <td colspan="5" class="text-center text-sm text-muted" style="padding:52px 20px">
                <?= $search
                    ? 'No operatives match <strong class="ink-text">"' . htmlspecialchars($search) . '"</strong>'
                    : 'No operatives found.' ?>
              </td>
            </tr>
            <?php else:
              $av_pool = ['#1e3a8a','#1d4ed8','#059669','#d97706','#7c3aed','#db2777'];
              while ($row = mysqli_fetch_assoc($users)):
                $initials = strtoupper(substr($row['full_name'], 0, 2));
                $av = $av_pool[abs(crc32($row['full_name'])) % count($av_pool)];
            ?>
            <tr>
              <td>
                <div class="flex items-center gap-3">
                  <div class="avatar" style="background:<?= $av ?>14;border:1px solid <?= $av ?>30;color:<?= $av ?>">
                    <?= $initials ?>
                  </div>
                  <div class="min-w-0">
                    <form method="POST" action="view_user.php" class="inline">
                      <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                      <button type="submit" 
                              class="font-bold text-sm no-underline block truncate max-w-[190px] transition-colors ink-text bg-transparent border-0 cursor-pointer text-left"
                              onmouseover="this.style.color='#1d4ed8'"
                              onmouseout="this.style.color=''">
                        <?= htmlspecialchars($row['full_name']) ?>
                      </button>
                    </form>
                    <span class="text-xs block truncate max-w-[190px] text-muted">
                      <?= htmlspecialchars($row['email']) ?>
                    </span>
                  </div>
                </div>
              </td>
              <td style="display:none" class="md-col">
                <code>@<?= htmlspecialchars($row['user_name']) ?></code>
              </td>
              <td>
                <div class="flex items-center gap-1.5">
                  <div class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:#f59e0b"></div>
                  <span class="font-bold text-sm text-gold"><?= number_format($row['credit']) ?></span>
                </div>
              </td>
              <td class="lg-col text-muted" style="display:none;font-size:11px">
                <?= $row['join_date'] ? date('d M Y', strtotime($row['join_date'])) : '&mdash;' ?>
              </td>
              <td class="text-right">
                <div class="flex items-center justify-end gap-2">
                  <form method="POST" action="view_user" class="inline">
                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                    <button type="submit" class="btn btn-outline" style="padding:5px 12px">
                      <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                      </svg>
                      View
                    </button>
                  </form>
                  <a href="?remove_user=<?= (int)$row['user_id'] ?>"
                     onclick="return confirm('Permanently remove <?= htmlspecialchars(addslashes($row['full_name'])) ?>?\n\nAll their data, meetings and history will be deleted. This cannot be undone.')"
                     class="btn btn-danger" style="padding:5px 12px">
                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"/>
                      <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                      <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                    </svg>
                    Remove
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Table footer -->
      <div class="flex items-center justify-between px-5 py-3" style="border-top:1px solid rgba(30,58,138,.07)">
        <p class="section-label">
          Showing <strong class="ink-text" style="font-size:12px;font-weight:700"><?= $total ?></strong>
          operative<?= $total != 1 ? 's' : '' ?>
        </p>
        <a href="index.php"
           class="text-xs font-semibold no-underline px-2.5 py-1 rounded-lg transition-colors text-blue"
           style="background:rgba(29,78,216,.07);border:1px solid rgba(29,78,216,.15)"
           onmouseover="this.style.background='rgba(29,78,216,.13)'"
           onmouseout="this.style.background='rgba(29,78,216,.07)'">
          &larr; Dashboard
        </a>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var html = document.documentElement;
  var sun  = document.getElementById('iconSun');
  var moon = document.getElementById('iconMoon');
  function applyTheme(dark) {
    html.classList.toggle('dark', dark);
    if (sun)  sun.style.display  = dark ? 'block' : 'none';
    if (moon) moon.style.display = dark ? 'none'  : 'block';
  }
  applyTheme(localStorage.getItem('ss_theme') === 'dark');
  window.toggleTheme = function () {
    var isDark = html.classList.toggle('dark');
    localStorage.setItem('ss_theme', isDark ? 'dark' : 'light');
    if (sun)  sun.style.display  = isDark ? 'block' : 'none';
    if (moon) moon.style.display = isDark ? 'none'  : 'block';
  };
})();
</script>
</body>
</html>