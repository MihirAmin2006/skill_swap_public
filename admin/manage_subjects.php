<?php
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';
require_once __DIR__ . '/validations.php';

$current_page = 'manage_subjects.php';
$error = '';

// ── Add Subject ──
if (isset($_POST['add_subject'])) {
    $sub_name = trim(mysqli_real_escape_string($conn, $_POST['sub_name']));
    if (!empty($sub_name)) {
        $dup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM subject_master WHERE sub_name = '$sub_name'"));
        if ($dup['c'] > 0) {
            $error = 'A sector with this name already exists.';
        } else {
            mysqli_query($conn, "INSERT INTO subject_master (sub_name, total_teachers, total_students) VALUES ('$sub_name', 0, 0)");
            header("Location: manage_subjects.php?status=added");
            exit;
        }
    } else {
        $error = 'Sector name cannot be empty.';
    }
}

// ── Delete Subject ──
if (isset($_GET['delete_sub'])) {
    $sid = intval($_GET['delete_sub']);
    mysqli_query($conn, "DELETE FROM assignment     WHERE sub_id = $sid");
    mysqli_query($conn, "DELETE FROM video_lectures WHERE sub_id = $sid");
    mysqli_query($conn, "DELETE FROM meetings       WHERE sub_id = $sid");
    mysqli_query($conn, "DELETE FROM user_roles     WHERE sub_id = $sid");
    mysqli_query($conn, "DELETE FROM subject_master WHERE sub_id = $sid");
    header("Location: manage_subjects.php?status=removed");
    exit;
}

// ── Fetch sectors ──
$subjects_res = mysqli_query($conn,
    "SELECT sm.sub_id, sm.sub_name,
            COALESCE(SUM(ur.user_role = 'teacher'),0) AS teacher_count,
            COALESCE(SUM(ur.user_role = 'student'),0) AS student_count,
            COUNT(ur.user_id) AS total_count
     FROM subject_master sm
     LEFT JOIN user_roles ur ON sm.sub_id = ur.sub_id
     where sm.sub_name != 'admin'
     GROUP BY sm.sub_id, sm.sub_name
     ORDER BY total_count DESC, sm.sub_name ASC");
$total_sectors = mysqli_num_rows($subjects_res);

$sectors = [];
while ($row = mysqli_fetch_assoc($subjects_res)) $sectors[] = $row;
$max_total = max(1, ...array_column($sectors, 'total_count') ?: [1]);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Skill Sectors · SkillSwap Admin</title>
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
              style="font-family:'Playfair Display',Georgia,serif;font-size:20px">Skill Sectors</h1>
          <p class="section-label mt-1" style="letter-spacing:.1em">Deploy and manage subject categories</p>
        </div>
      </div>
      <div class="flex items-center gap-2.5">
        <?php if (isset($_GET['status'])): ?>
        <span class="badge toast <?= $_GET['status']==='added' ? 'badge-green' : 'badge-royal' ?>"
              style="padding:5px 12px;font-size:11px">
          &#10003; Sector <?= $_GET['status']==='added' ? 'deployed' : 'removed' ?>
        </span>
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

    <!-- Deploy Form -->
    <div class="ss-card enter p-7">
      <div class="flex items-start gap-4 mb-5">
        <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-xl"
             style="background:linear-gradient(135deg,var(--gold-secondary),var(--gold-primary));box-shadow:0 4px 12px rgba(245,158,11,.25)">
          <svg class="w-4.5 h-4.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
          </svg>
        </div>
        <div>
          <h3 class="card-title">Deploy New Sector</h3>
          <p class="section-label mt-1" style="letter-spacing:.1em">Add a new skill category to the platform</p>
        </div>
      </div>

      <?php if ($error): ?>
      <div class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-semibold badge-red"
           style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.18)">
        <svg class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="flex gap-2.5 flex-wrap items-center">
        <input class="form-input" type="text" name="sub_name" required
               placeholder="e.g., UI Design, Python, Machine Learning, JAVA…"
               autocomplete="off">
        <button type="submit" name="add_subject" class="btn btn-gold">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Deploy Sector
        </button>
      </form>
    </div>

    <!-- Count header -->
    <div class="flex items-center justify-between enter d1">
      <p class="section-label">
        <strong class="ink-text" style="font-size:13px;font-weight:700"><?= $total_sectors ?></strong>
        sector<?= $total_sectors != 1 ? 's' : '' ?> deployed
      </p>
      <p class="section-label">Sorted by activity</p>
    </div>

    <!-- Sectors grid -->
    <?php if ($total_sectors === 0): ?>
    <div class="ss-card enter d1 text-center" style="padding:64px">
      <svg class="w-10 h-10 mx-auto mb-3 block icon-muted"
           viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
      </svg>
      <p class="text-sm text-muted">No skill sectors deployed yet. Add one above.</p>
    </div>
    <?php else: ?>
    <div class="enter d2" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
      <?php
      $accent_colors = ['#1e3a8a','#d97706','#059669','#7c3aed','#0891b2','#db2777','#f97316','#0f766e'];
      foreach ($sectors as $i => $row):
        $accent = $accent_colors[$i % count($accent_colors)];
        $pct    = round(($row['total_count'] / $max_total) * 100);
        $is_top = ($i === 0 && $row['total_count'] > 0);
      ?>
      <div class="sector-card" style="--accent-color:<?= $accent ?>">

        <!-- Top row: icon + name + delete -->
        <div class="flex items-start justify-between gap-3 mb-4">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div class="flex-shrink-0 flex items-center justify-center w-9.5 h-9.5 rounded-xl"
                 style="width:38px;height:38px;background:<?= $accent ?>14;border:1px solid <?= $accent ?>30">
              <svg class="w-4 h-4 icon-royal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
              </svg>
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-1.5 flex-wrap">
                <p class="font-bold text-sm ink-text truncate max-w-[160px]">
                  <?= htmlspecialchars($row['sub_name']) ?>
                </p>
                <?php if ($is_top): ?>
                <span class="badge badge-gold" style="font-size:9px;padding:2px 7px;letter-spacing:.05em">&#9733; TOP</span>
                <?php endif; ?>
              </div>
              <p class="text-xs mt-0.5 text-muted"><?= $row['total_count'] ?> enrolled</p>
            </div>
          </div>
          <a href="?delete_sub=<?= $row['sub_id'] ?>"
             onclick="return confirm('Remove sector &quot;<?= htmlspecialchars(addslashes($row['sub_name'])) ?>&quot; and all linked data?\n\nThis cannot be undone.')"
             class="btn btn-danger flex-shrink-0" style="padding:5px 10px;font-size:11px">
            <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
            </svg>
            Remove
          </a>
        </div>

        <!-- Badges row -->
        <div class="flex gap-2 flex-wrap mb-3">
          <span class="badge badge-royal">
            <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            <?= (int)$row['teacher_count'] ?> teacher<?= $row['teacher_count'] != 1 ? 's' : '' ?>
          </span>
          <span class="badge badge-indigo">
            <?= (int)$row['student_count'] ?> student<?= $row['student_count'] != 1 ? 's' : '' ?>
          </span>
        </div>

        <!-- Activity bar -->
        <div class="prog-track">
          <div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $accent ?>;opacity:.7"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Footer nav -->
    <div class="text-center pb-2">
      <a href="index.php"
         class="inline-block text-xs font-semibold no-underline px-3.5 py-1.5 rounded-lg transition-colors text-blue"
         style="background:rgba(29,78,216,.07);border:1px solid rgba(29,78,216,.15)"
         onmouseover="this.style.background='rgba(29,78,216,.13)'"
         onmouseout="this.style.background='rgba(29,78,216,.07)'">
        &larr; Back to Dashboard
      </a>
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