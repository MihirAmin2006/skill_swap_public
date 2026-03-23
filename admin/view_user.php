<?php
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

$current_page = 'view_user.php';

// Check for POST parameter first, then fallback to GET for backward compatibility
if (!isset($_POST['user_id']) && !isset($_GET['user_id'])) { 
    header("Location: manage_students.php"); 
    exit; 
}

if (isset($_POST['user_id'])) {
    $uid = intval($_POST['user_id']);
} else {
    $uid = intval($_GET['user_id']);
}

// ── Fetch user ──
$stmt = $conn->prepare("SELECT * FROM `user_master` WHERE `user_id` = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$user_arr = $res->fetch_assoc();
$user = $user_arr;

if (!$user_arr && $user) { 
    header("Location: manage_students.php"); 
    exit; 
}

// ── Skills ──
$roles_stmt = $conn->prepare(
    "SELECT sm.sub_name, ur.user_role
     FROM `user_roles` ur
     JOIN `subject_master` sm ON ur.sub_id = sm.sub_id
     WHERE ur.user_id = ?
     ORDER BY ur.user_role DESC, sm.sub_name ASC");
$roles_stmt->bind_param("i", $uid);
$roles_stmt->execute();
$roles_result = $roles_stmt->get_result();

$skills = [];
while ($r = $roles_result->fetch_assoc()) $skills[] = $r;
$teach_count = count(array_filter($skills, fn($s) => $s['user_role'] === 'teacher'));
$learn_count = count($skills) - $teach_count;

// ── Meeting stats ──
$meet_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total,
            SUM(status='upcoming')  AS upcoming,
            SUM(status='completed') AS completed,
            SUM(status='started')   AS started
     FROM `meetings` WHERE `student_id`=? OR `teacher_id`=?");
$meet_stmt->bind_param("ii", $uid, $uid);
$meet_stmt->execute();
$meet_result = $meet_stmt->get_result();
$m = $meet_result->fetch_assoc();
$meet_total     = (int)($m['total']     ?? 0);
$meet_upcoming  = (int)($m['upcoming']  ?? 0);
$meet_completed = (int)($m['completed'] ?? 0);
$meet_started   = (int)($m['started']   ?? 0);

// ── Avatar colour (per-user, deterministic) ──
$av_pool  = ['#1e3a8a','#1d4ed8','#059669','#d97706','#7c3aed','#db2777'];
$av_color = $av_pool[abs(crc32($user_arr['full_name'])) % count($av_pool)];
$initials = strtoupper(substr($user_arr['full_name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($user['full_name']) ?> · SkillSwap Admin</title>
   <?php
    include_once __DIR__ . '/../includes/scripts/common.php';
    ?>
    <link rel="stylesheet" href="../assets/styles/admin/common.css">
    <script src="../assets/js/tailwind.js.php" defer></script>
    <link rel="stylesheet" href="../assets/styles/admin/admin_tailwind_css.css">
  <style>
    /* Per-user avatar tint applied via PHP colour */
    .profile-hero::after {
      content: '';
      position: absolute; top: 3px; left: 0; right: 0; height: 100px;
      background: linear-gradient(180deg, <?= $av_color ?>10 0%, transparent 100%);
      pointer-events: none;
    }
    
    /* Enhanced avatar glow effect */
    .avatar-glow {
      position: relative;
      transition: all 0.3s ease;
    }
    
    .avatar-glow::before {
      content: '';
      position: absolute;
      inset: -2px;
      border-radius: 20px;
      background: linear-gradient(45deg, <?= $av_color ?>40, <?= $av_color ?>20, <?= $av_color ?>40);
      opacity: 0;
      transition: opacity 0.3s ease;
      z-index: -1;
    }
    
    .avatar-glow:hover::before {
      opacity: 1;
    }
    
    /* Enhanced skill pill animations */
    .skill-pill {
      position: relative;
      overflow: hidden;
    }
    
    .skill-pill::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      transform: translate(-50%, -50%);
      transition: width 0.4s ease, height 0.4s ease;
    }
    
    .skill-pill:active::after {
      width: 100px;
      height: 100px;
    }
    
    /* Enhanced stat card hover */
    .stat-mini {
      position: relative;
      overflow: hidden;
    }
    
    .stat-mini::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
      transition: left 0.6s ease;
    }
    
    .stat-mini:hover::before {
      left: 100%;
    }
    
    /* Smooth number transitions */
    .stat-num {
      transition: all 0.3s ease;
    }
    
    .stat-mini:hover .stat-num {
      transform: scale(1.05);
    }
  </style>
</head>
<body class="bg-mesh">

    <!-- ════════════ MOBILE OVERLAY ════════════ -->
    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>

    <?php
    include_once __DIR__ . '/../animated-bg.php';
    ?>

    <!-- ════════════ SIDEBAR ════════════ -->
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <!-- ════════════ MAIN CONTENT ════════════ -->
    <main class="min-h-screen relative z-10">

  <!-- Topbar -->
  <header class="topbar">
    <div class="flex items-center gap-3 px-7 py-3.5 flex-wrap">
      <button onclick="openMobileSidebar()" class="sidebar-trigger lg:hidden">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

      <div>
        <h1 class="font-bold leading-none tracking-tight text-royal-primary"
            style="font-family:'Playfair Display',Georgia,serif;font-size:18px">
          <?= htmlspecialchars($user_arr['full_name']) ?>
        </h1>
        <p class="section-label mt-0.5" style="letter-spacing:.1em">Operative Profile</p>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <form method="POST" action="manage_students" class="inline">
          <input type="hidden" name="remove_user" value="<?= $uid ?>">
          <button type="submit" 
                  onclick="return confirm('Permanently remove <?= htmlspecialchars(addslashes($user_arr['full_name'])) ?>?\n\nAll data, meetings and history will be deleted.')"
                  class="btn btn-danger">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
              <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
            </svg>
            Remove
          </button>
        </form>
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

  <div class="p-7 max-w-4xl flex flex-col gap-5">

    <!-- Profile Hero -->
    <div class="profile-hero enter">
      <div class="relative flex flex-wrap items-start gap-6">

        <!-- Avatar -->
        <div class="avatar-glow relative flex-shrink-0 flex items-center justify-center w-[72px] h-[72px] rounded-[18px] font-bold"
             style="background:<?= $av_color ?>14;border:2px solid <?= $av_color ?>35;
                    font-family:'Playfair Display',Georgia,serif;font-size:22px;
                    color:<?= $av_color ?>;box-shadow:0 8px 24px <?= $av_color ?>20">
          <?= $initials ?>
          <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full"
               style="background:#10b981;border:2px solid #f1f5f9"></div>
        </div>

        <!-- Identity -->
        <div class="flex-1 min-w-[200px]">
          <div class="flex items-center gap-2.5 flex-wrap mb-1.5">
            <h2 class="font-bold leading-none tracking-tight text-royal-primary"
                style="font-family:'Playfair Display',Georgia,serif;font-size:22px">
              <?= htmlspecialchars($user_arr['full_name']) ?>
            </h2>
            <span class="badge badge-green">
              <span class="inline-block w-1.5 h-1.5 rounded-full" style="background:#10b981"></span>
              Active
            </span>
          </div>
          <p class="text-sm mb-2 text-muted">
            @<?= htmlspecialchars($user_arr['user_name']) ?>
            &nbsp;&middot;&nbsp;
            <?= htmlspecialchars($user_arr['email']) ?>
          </p>
          <?php if (!empty($user_arr['bio'])): ?>
          <p class="text-xs leading-relaxed max-w-[480px] text-muted">
            <?= htmlspecialchars($user_arr['bio']) ?>
          </p>
          <?php endif; ?>
        </div>

        <!-- Joined -->
        <?php if ($user_arr['join_date']): ?>
        <div class="text-right flex-shrink-0">
          <p class="section-label" style="letter-spacing:.1em">Joined</p>
          <p class="text-sm font-semibold mt-0.5 ink-text"><?= date('d M Y', strtotime($user_arr['join_date'])) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 4 Key Metrics -->
    <div class="grid grid-cols-4 gap-3.5 enter d1">
      <div class="stat-mini text-center">
        <div class="stat-num text-gold"><?= number_format($user_arr['credit']) ?></div>
        <p class="section-label mt-1.5">Credits</p>
      </div>
      <div class="stat-mini text-center">
        <div class="stat-num" style="color:<?= $av_color ?>"><?= count($skills) ?></div>
        <p class="section-label mt-1.5">Skills</p>
      </div>
      <div class="stat-mini text-center">
        <div class="stat-num text-green"><?= $meet_total ?></div>
        <p class="section-label mt-1.5">Meetings</p>
      </div>
      <div class="stat-mini text-center">
        <div class="stat-num text-purple"><?= $meet_completed ?></div>
        <p class="section-label mt-1.5">Completed</p>
      </div>
    </div>

    <!-- Two-column detail -->
    <div class="two-col enter d2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

      <!-- Contact Details -->
      <div class="ss-card p-6">
        <h3 class="card-title mb-4">Contact Details</h3>

        <div class="info-row">
          <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg"
               style="background:rgba(29,78,216,.08)">
            <svg class="w-3.5 h-3.5 icon-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
          </div>
          <div>
            <p class="section-label mb-0.5" style="letter-spacing:.12em">Email</p>
            <p class="text-sm ink-text"><?= htmlspecialchars($user_arr['email']) ?></p>
          </div>
        </div>

        <div class="info-row">
          <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg"
               style="background:rgba(5,150,105,.08)">
            <svg class="w-3.5 h-3.5 icon-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.62 4.36 2 2 0 0 1 3.59 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </div>
          <div>
            <p class="section-label mb-0.5" style="letter-spacing:.12em">Phone</p>
            <p class="text-sm ink-text">
              <?= !empty($user_arr['phone']) ? htmlspecialchars($user_arr['phone']) : '<span class="text-muted">Not provided</span>' ?>
            </p>
          </div>
        </div>

        <div class="info-row">
          <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg"
               style="background:rgba(245,158,11,.08)">
            <svg class="w-3.5 h-3.5 icon-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
          <div>
            <p class="section-label mb-0.5" style="letter-spacing:.12em">Username</p>
            <code>@<?= htmlspecialchars($user_arr['user_name']) ?></code>
          </div>
        </div>

        <?php if ($meet_total > 0): ?>
        <div class="info-row">
          <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg"
               style="background:rgba(124,58,237,.08)">
            <svg class="w-3.5 h-3.5 icon-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
          <div>
            <p class="section-label mb-1.5" style="letter-spacing:.12em">Meeting Activity</p>
            <div class="flex gap-1.5 flex-wrap">
              <span class="badge badge-green"><?= $meet_completed ?> completed</span>
              <span class="badge badge-gold"><?= $meet_upcoming ?> upcoming</span>
              <?php if ($meet_started): ?><span class="badge badge-indigo"><?= $meet_started ?> started</span><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Skillset Matrix -->
      <div class="ss-card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="card-title">Skillset Matrix</h3>
          <?php if (!empty($skills)): ?>
          <span class="badge badge-royal"><?= $teach_count ?>T / <?= $learn_count ?>L</span>
          <?php endif; ?>
        </div>

        <?php if (empty($skills)): ?>
        <div class="text-center py-8">
          <svg class="w-8 h-8 mx-auto mb-2.5 block icon-muted"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
          </svg>
          <p class="text-xs text-muted">No skills assigned yet</p>
        </div>
        <?php else: ?>
        <div class="flex flex-wrap gap-1.5 mb-4">
          <?php foreach ($skills as $skill):
            $is_teacher = $skill['user_role'] === 'teacher'; ?>
          <span class="skill-pill <?= $is_teacher ? 'skill-pill-teacher' : 'skill-pill-learner' ?>">
            <?php if ($is_teacher): ?>
            <svg class="w-2.5 h-2.5 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            <?php endif; ?>
            <?= htmlspecialchars($skill['sub_name']) ?>
            <span class="opacity-50 text-[9px]">(<?= $is_teacher ? 'T' : 'L' ?>)</span>
          </span>
          <?php endforeach; ?>
        </div>

        <!-- Summary bar -->
        <div class="rounded-xl p-3 px-4" style="background:rgba(30,58,138,.04);border:1px solid rgba(30,58,138,.07)">
          <div class="flex gap-4 text-xs mb-2">
            <div class="flex items-center gap-1.5">
              <div class="w-2 h-2 rounded-full" style="background:#1e3a8a"></div>
              <span class="text-muted">Teaching:</span>
              <strong class="ink-text"><?= $teach_count ?></strong>
            </div>
            <div class="flex items-center gap-1.5">
              <div class="w-2 h-2 rounded-full" style="background:#6366f1"></div>
              <span class="text-muted">Learning:</span>
              <strong class="ink-text"><?= $learn_count ?></strong>
            </div>
          </div>
          <?php if (count($skills) > 0): $t_pct = round(($teach_count / count($skills)) * 100); ?>
          <div class="prog-track">
            <div class="prog-fill" style="width:<?= $t_pct ?>%;background:linear-gradient(90deg,#1e3a8a,#1d4ed8)"></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Footer nav -->
    <div class="flex items-center justify-between pb-2">
      <a href="manage_students" class="btn btn-outline">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Back to Operatives
      </a>
      <p class="section-label">Operative ID #<?= $uid ?></p>
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