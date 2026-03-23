<?php
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';
require_once __DIR__ . '/validations.php';

$current_page = 'index.php';

/* ── Stats ── */
$total_users     = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM user_master"))['c']                                                          ?? 0);
$total_subs      = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM subject_master"))['c']                                                       ?? 0);
$active_meetings = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM meetings WHERE status='upcoming'"))['c']                                     ?? 0);
$total_credits   = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(credit),0) AS c FROM user_master"))['c']                                           ?? 0);
$new_users_month = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM user_master WHERE join_date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY)"))['c']  ?? 0);

/* ── Recent 6 users ── */
$recent_users = mysqli_query($conn,
    "SELECT user_id, full_name, email, credit, join_date FROM user_master ORDER BY user_id DESC LIMIT 6");

/* ── Top 4 sectors ── */
$top_sectors_res = mysqli_query($conn,
    "SELECT sm.sub_name,
            COALESCE(SUM(ur.user_role='teacher'),0) AS teachers,
            COALESCE(SUM(ur.user_role='student'),0) AS students,
            COUNT(ur.user_id) AS total
     FROM subject_master sm
     LEFT JOIN user_roles ur ON sm.sub_id = ur.sub_id
     GROUP BY sm.sub_id, sm.sub_name
     ORDER BY total DESC LIMIT 4");

$sector_data = [];
$max_sector  = 1;
while ($s = mysqli_fetch_assoc($top_sectors_res)) {
    $sector_data[] = $s;
    if ((int)$s['total'] > $max_sector) $max_sector = (int)$s['total'];
}

$admin_first = htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Admin')[0]);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard &middot; SkillSwap Admin</title>
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

    <!-- ════════════ MAIN CONTENT ════════════ -->
    <main class="min-h-screen relative z-10">

  <!-- ── Top Bar ── -->
  <header class="topbar">
    <div class="flex items-center justify-between px-7 py-3.5 gap-3 flex-wrap">

      <div class="flex items-center gap-3.5">
        <button onclick="openMobileSidebar()" class="sidebar-trigger lg:hidden">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <div>
          <h1 class="font-serif text-xl font-bold tracking-tight leading-none text-royal-primary"
              style="font-family:'Playfair Display',Georgia,serif">Command Center</h1>
          <p class="text-xs mt-0.5 font-medium text-muted">
            <?= date('l, F j, Y') ?>&nbsp;&nbsp;·&nbsp;&nbsp;Live platform overview
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2.5">
        <?php if ($new_users_month > 0): ?>
        <span class="badge badge-green">+ <?= $new_users_month ?> this month</span>
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

  <!-- ── Page Content ── -->
  <div class="p-7 max-w-screen-xl flex flex-col gap-6">

    <!-- Welcome Banner -->
    <div class="enter relative overflow-hidden rounded-2xl px-9 py-8"
         style="background:linear-gradient(135deg,rgba(30,58,138,.10) 0%,rgba(29,78,216,.08) 50%,rgba(245,158,11,.06) 100%);
                border:1px solid rgba(30,58,138,.13)">
      <!-- Dot-grid overlay -->
      <div class="absolute inset-0 pointer-events-none"
           style="background-image:linear-gradient(rgba(30,58,138,.04) 1px,transparent 1px),
                                    linear-gradient(90deg,rgba(30,58,138,.04) 1px,transparent 1px);
                  background-size:28px 28px"></div>
      <!-- Top accent stripe -->
      <div class="absolute top-0 left-0 right-0 h-0.5"
           style="background:linear-gradient(90deg,#1e3a8a,#1d4ed8,#f59e0b,#1d4ed8,#1e3a8a)"></div>

      <div class="relative flex flex-wrap items-center justify-between gap-5">
        <div>
          <p class="section-label mb-2 text-blue">Admin Console</p>
          <h2 class="font-bold mb-1.5 leading-none tracking-tight text-royal-primary"
              style="font-family:'Playfair Display',Georgia,serif;font-size:26px">
            Welcome back, <?= $admin_first ?> &#x1F44B;
          </h2>
          <p class="text-sm text-muted">Here's what's happening across the SkillSwap platform today.</p>
        </div>
        <div class="flex gap-2.5 flex-wrap">
          <a href="manage_students.php" class="btn btn-royal">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            </svg>
            View Operatives
          </a>
          <a href="manage_subjects.php" class="btn btn-gold">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
              <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
            Manage Sectors
          </a>
        </div>
      </div>
    </div>

    <!-- 4 Stat Cards -->
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">

      <!-- Operatives -->
      <div class="ss-card enter d1 p-5">
        <div class="flex items-start justify-between mb-3.5">
          <div class="stat-icon" style="background:rgba(30,58,138,.08)">
            <svg class="w-5 h-5 icon-royal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <span class="badge badge-royal">&#8593; Active</span>
        </div>
        <div class="stat-num text-royal-primary" style="animation-delay:.05s"><?= number_format($total_users) ?></div>
        <p class="section-label mt-1.5">Total Operatives</p>
        <a href="manage_students.php" class="block mt-1.5 text-xs font-semibold no-underline text-blue">Manage &rarr;</a>
        <div class="prog-track">
          <div class="prog-fill" style="width:<?= min(100,$total_users) ?>%;background:linear-gradient(90deg,#1e3a8a,#1d4ed8)"></div>
        </div>
      </div>

      <!-- Skill Sectors -->
      <div class="ss-card enter d2 p-5">
        <div class="flex items-start justify-between mb-3.5">
          <div class="stat-icon" style="background:rgba(245,158,11,.10)">
            <svg class="w-5 h-5 icon-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
              <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
          </div>
          <span class="badge badge-gold">Deployed</span>
        </div>
        <div class="stat-num text-gold" style="animation-delay:.10s"><?= number_format($total_subs) ?></div>
        <p class="section-label mt-1.5">Skill Sectors</p>
        <a href="manage_subjects.php" class="block mt-1.5 text-xs font-semibold no-underline text-gold">Manage &rarr;</a>
        <div class="prog-track">
          <div class="prog-fill" style="width:<?= min(100,$total_subs*5) ?>%;background:linear-gradient(90deg,#d97706,#f59e0b)"></div>
        </div>
      </div>

      <!-- Upcoming Meetings -->
      <div class="ss-card enter d3 p-5">
        <div class="flex items-start justify-between mb-3.5">
          <div class="stat-icon" style="background:rgba(16,185,129,.10)">
            <svg class="w-5 h-5 icon-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
          <?php if ($active_meetings > 0): ?>
          <span class="badge badge-green">
            <span class="pulse-dot" style="width:5px;height:5px;border-radius:50%;background:#10b981;display:inline-block"></span>
            Live
          </span>
          <?php else: ?>
          <span class="badge badge-green">Upcoming</span>
          <?php endif; ?>
        </div>
        <div class="stat-num text-green" style="animation-delay:.15s"><?= number_format($active_meetings) ?></div>
        <p class="section-label mt-1.5">Upcoming Sessions</p>
        <p class="mt-1.5 text-xs text-muted">Scheduled sessions</p>
        <div class="prog-track">
          <div class="prog-fill" style="width:<?= min(100,$active_meetings*4) ?>%;background:linear-gradient(90deg,#059669,#10b981)"></div>
        </div>
      </div>

      <!-- Platform Credits -->
      <div class="ss-card enter d4 p-5">
        <div class="flex items-start justify-between mb-3.5">
          <div class="stat-icon" style="background:rgba(99,102,241,.10)">
            <svg class="w-5 h-5 icon-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
            </svg>
          </div>
          <span class="badge badge-indigo">Platform</span>
        </div>
        <div class="stat-num text-purple" style="animation-delay:.20s"><?= number_format($total_credits) ?></div>
        <p class="section-label mt-1.5">Total Credits</p>
        <p class="mt-1.5 text-xs text-muted">Across all operatives</p>
        <div class="prog-track">
          <div class="prog-fill" style="width:72%;background:linear-gradient(90deg,#4338ca,#6366f1)"></div>
        </div>
      </div>
    </div>

    <!-- Two-column: table + top sectors -->
    <div class="two-col-grid" style="display:grid;grid-template-columns:1fr 310px;gap:16px;align-items:start">

      <!-- Recent Operatives table -->
      <div class="ss-card overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4.5"
             style="border-bottom:1px solid rgba(30,58,138,.07)">
          <div>
            <h3 class="card-title">Recent Operatives</h3>
            <p class="text-xs mt-0.5 text-muted">Latest 6 registered users</p>
          </div>
          <a href="manage_students.php"
             class="text-xs font-bold no-underline px-3 py-1.5 rounded-lg transition-colors text-blue"
             style="border:1px solid rgba(29,78,216,.18);background:rgba(29,78,216,.07)"
             onmouseover="this.style.background='rgba(29,78,216,.13)'"
             onmouseout="this.style.background='rgba(29,78,216,.07)'">
            View All &rarr;
          </a>
        </div>
        <div class="overflow-x-auto">
          <table>
            <thead>
              <tr>
                <th>Operative</th>
                <th class="sm-cell" style="display:none">Email</th>
                <th>Credits</th>
                <th class="md-cell" style="display:none">Joined</th>
                <th style="text-align:right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $av_pool = ['#1e3a8a','#1d4ed8','#059669','#d97706','#7c3aed','#db2777'];
              while ($row = mysqli_fetch_assoc($recent_users)):
                $initials = strtoupper(substr($row['full_name'], 0, 2));
                $av_color = $av_pool[abs(crc32($row['full_name'])) % count($av_pool)];
              ?>
              <tr>
                <td>
                  <div class="flex items-center gap-3">
                    <div class="avatar" style="background:<?= $av_color ?>18;border:1px solid <?= $av_color ?>35;color:<?= $av_color ?>">
                      <?= $initials ?>
                    </div>
                    <span class="ink-text font-semibold text-sm"><?= htmlspecialchars($row['full_name']) ?></span>
                  </div>
                </td>
                <td class="sm-cell text-xs text-muted" style="display:none"><?= htmlspecialchars($row['email']) ?></td>
                <td><span class="badge badge-gold"><?= number_format($row['credit']) ?></span></td>
                <td class="md-cell text-xs text-muted" style="display:none">
                  <?= $row['join_date'] ? date('d M Y', strtotime($row['join_date'])) : '&mdash;' ?>
                </td>
                <td class="text-right">
                  <a href="view_user.php?uid=<?= (int)$row['user_id'] ?>"
                     class="text-xs font-bold no-underline px-2.5 py-1 rounded-lg inline-block transition-colors text-blue"
                     style="background:rgba(29,78,216,.07);border:1px solid rgba(29,78,216,.15)"
                     onmouseover="this.style.background='rgba(29,78,216,.14)'"
                     onmouseout="this.style.background='rgba(29,78,216,.07)'">
                    View &rarr;
                  </a>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Top Sectors -->
      <div class="ss-card p-5">
        <div class="flex items-center justify-between mb-4 pb-3.5" style="border-bottom:1px solid rgba(30,58,138,.07)">
          <div>
            <h3 class="card-title">Top Sectors</h3>
            <p class="text-xs mt-0.5 text-muted">By total enrollment</p>
          </div>
          <a href="manage_subjects.php"
             class="text-xs font-bold no-underline px-2.5 py-1 rounded-lg text-gold"
             style="border:1px solid rgba(245,158,11,.2);background:rgba(245,158,11,.08)">
            All &rarr;
          </a>
        </div>
        <div class="flex flex-col gap-4">
          <?php
          $s_accents = ['#1e3a8a','#d97706','#059669','#4f46e5'];
          foreach ($sector_data as $i => $s):
            $pct = $max_sector > 0 ? round((int)$s['total'] / $max_sector * 100) : 0;
            $clr = $s_accents[$i % count($s_accents)];
          ?>
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <div class="flex items-center gap-2 min-w-0 flex-1">
                <div class="w-2 h-2 rounded-full flex-shrink-0" style="background:<?= $clr ?>"></div>
                <span class="text-xs font-semibold ink-text truncate max-w-[160px]">
                  <?= htmlspecialchars($s['sub_name']) ?>
                </span>
              </div>
              <span class="text-xs font-bold flex-shrink-0 ml-2 text-muted"><?= (int)$s['total'] ?></span>
            </div>
            <div class="prog-track">
              <div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $clr ?>;opacity:.75"></div>
            </div>
            <div class="flex gap-2.5 mt-1">
              <span class="text-xs text-muted"><?= (int)$s['teachers'] ?> teachers</span>
              <span class="text-xs text-muted">&middot;</span>
              <span class="text-xs text-muted"><?= (int)$s['students'] ?> students</span>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($sector_data)): ?>
          <p class="text-xs text-center py-4 text-muted">No sectors deployed yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div>
      <p class="section-label mb-3.5">Quick Actions</p>
      <div class="grid gap-3.5" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">

        <a href="manage_students.php" class="quick-action">
          <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-2xl"
               style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);box-shadow:0 4px 14px rgba(30,58,138,.35)">
            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="ink-text font-bold text-sm mb-0.5">Manage Operatives</p>
            <p class="text-xs text-muted">View, inspect and remove users</p>
          </div>
          <svg class="arrow-icon w-4 h-4 flex-shrink-0 icon-muted"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18l6-6-6-6"/>
          </svg>
        </a>

        <a href="manage_subjects.php" class="quick-action">
          <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-2xl"
               style="background:linear-gradient(135deg,#d97706,#f59e0b);box-shadow:0 4px 14px rgba(245,158,11,.3)">
            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
              <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="ink-text font-bold text-sm mb-0.5">Skill Sectors</p>
            <p class="text-xs text-muted">Deploy and manage subject categories</p>
          </div>
          <svg class="arrow-icon w-4 h-4 flex-shrink-0 icon-muted"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18l6-6-6-6"/>
          </svg>
        </a>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  'use strict';
  var html  = document.documentElement;
  var sun   = document.getElementById('iconSun');
  var moon  = document.getElementById('iconMoon');
  function applyTheme(dark) {
    html.classList.toggle('dark', dark);
    sun.style.display  = dark ? 'block' : 'none';
    moon.style.display = dark ? 'none'  : 'block';
  }
  applyTheme(localStorage.getItem('ss_theme') === 'dark');
  window.toggleTheme = function () {
    var isDark = html.classList.toggle('dark');
    localStorage.setItem('ss_theme', isDark ? 'dark' : 'light');
    sun.style.display  = isDark ? 'block' : 'none';
    moon.style.display = isDark ? 'none'  : 'block';
  };
})();
</script>
</body>
</html>