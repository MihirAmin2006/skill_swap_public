<?php
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';

$user = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT COUNT(*) as `total` FROM `meetings` WHERE `teacher_id` = ? AND `approved` = '2'");
$stmt->bind_param("i", $user);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

// Unread notification count
$notif_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND msg_read = 'no'");
$notif_stmt->bind_param("i", $user);
$notif_stmt->execute();
$notif_count = $notif_stmt->get_result()->fetch_assoc()['cnt'];
$notif_stmt->close();

// Pending assignments (completed meetings where student has no mcq_result yet)
$assign_stmt = $conn->prepare("
    SELECT COUNT(*) as cnt
    FROM mcq_assignments ma
    WHERE ma.student_id = ?
      AND NOT EXISTS (
          SELECT 1 FROM mcq_results mr
          WHERE mr.assignment_id = ma.assignment_id AND mr.student_id = ?
      )
");
$assign_stmt->bind_param("ii", $user, $user);
$assign_stmt->execute();
$pending_assignments = (int) $assign_stmt->get_result()->fetch_assoc()['cnt'];
$assign_stmt->close();

// Get latest pending assignment meeting_id for direct link
$latest_assign_stmt = $conn->prepare("
    SELECT ma.meeting_id
    FROM mcq_assignments ma
    WHERE ma.student_id = ?
      AND NOT EXISTS (
          SELECT 1 FROM mcq_results mr
          WHERE mr.assignment_id = ma.assignment_id AND mr.student_id = ?
      )
    ORDER BY ma.created_at DESC
    LIMIT 1
");
$latest_assign_stmt->bind_param("ii", $user, $user);
$latest_assign_stmt->execute();
$latest_assign = $latest_assign_stmt->get_result()->fetch_assoc();
$latest_assign_stmt->close();
$assign_href = $latest_assign ? 'assignment?meeting_id=' . urlencode($latest_assign['meeting_id']) : 'dashboard';
?>

<div id="navbar">
  <aside id="sidebar"
    class="fixed left-0 top-0 h-screen w-64 z-50
              bg-white/95 dark:bg-card-dark/95
              backdrop-blur-xl
              border-r border-royal-basic/10 dark:border-royal-violet/20
              shadow-nav
              flex flex-col
              transition-all duration-300">

    <!-- Logo -->
    <div class="p-6 border-b border-royal-basic/10 dark:border-royal-violet/20">
      <div class="flex items-center gap-2.5 logo-wrapper">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
          style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
        </div>
        <div class="logo-text">
          <h2 class="font-display font-bold text-lg text-royal-basic dark:text-royal-soft">
            Skill<span class="text-gold-basic">Swap</span>
          </h2>
          <p class="text-xs text-muted-light dark:text-muted-dark">Student Portal</p>
        </div>

        <button id="pinToggle"
          class="absolute top-6 right-4 p-2 rounded-lg
         bg-royal-basic/10 dark:bg-royal-soft/20
         hover:bg-royal-basic/20 dark:hover:bg-royal-soft/30
         transition-all duration-300"
          title="Pin / Unpin Sidebar">
          <svg id="pinIcon" class="w-4 h-4 text-royal-basic dark:text-royal-soft transition-transform duration-300"
            viewBox="0 0 20 20" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <g id="layer1">
              <path d="M 10 1.4394531 L 7.21875 7.0742188 L 1 7.9785156 L 5.5 12.367188 L 4.4375 18.558594 L 10 15.634766 L 15.5625 18.558594 L 14.5 12.367188 L 19 7.9785156 L 12.78125 7.0742188 L 10 1.4394531 z M 10 3.703125 L 12.117188 7.9882812 L 16.849609 8.6757812 L 13.425781 12.017578 L 14.234375 16.730469 L 10 14.505859 L 5.765625 16.730469 L 6.5742188 12.017578 L 3.1503906 8.6757812 L 7.8828125 7.9882812 L 10 3.703125 z " style="fill:#222222; fill-opacity:1; stroke:none; stroke-width:0px;"></path>
            </g>
          </svg>
        </button>

        <!-- Mobile close button -->
        <button id="closeSidebar" class="lg:hidden absolute top-6 right-4 p-2 rounded-lg
                    bg-royal-basic/10 dark:bg-royal-soft/20
                    hover:bg-royal-basic/20 dark:hover:bg-royal-soft/30
                    transition-all duration-300"
          onclick="closeMobileSidebar()">
          <svg class="w-5 h-5 text-royal-basic dark:text-royal-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>


    <!-- Nav Items -->
    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
      <a href="dashboard"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $path === 'dashboard' || $path === 'dashboard.php' ? 'active-state' : ''  ?>">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
          <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
        <span>Home</span>
      </a>
      <a href="notifications"
    class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
          text-ink-light dark:text-ink-dark
          hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
          transition-all duration-200 group <?= $path === 'notifications' || $path === 'notifications.php' ? 'active-state' : '' ?>">
    <div class="relative">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        <?php if ($notif_count > 0): ?>
            <span style="
                position:absolute;
                top:-6px;
                right:-6px;
                background:#ef4444;
                color:white;
                font-size:0.6rem;
                font-weight:800;
                min-width:16px;
                height:16px;
                border-radius:999px;
                display:flex;
                align-items:center;
                justify-content:center;
                padding:0 4px;
                line-height:1;
            "><?= $notif_count > 99 ? '99+' : $notif_count ?></span>
        <?php endif; ?>
    </div>
    <span>Notification</span>
</a>
      <?php if ($res['total'] > 0): ?>
        <a href="review_request"
          class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
                text-ink-light dark:text-ink-dark
                hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
                transition-all duration-200 group <?= $path === 'review_request' || $path === 'review_request.php' ? 'active-state' : ''  ?>">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <g transform="translate(0, 0)">
              <path d="M7 10v12"></path>
              <path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z"></path>
            </g>

          </svg>
          <span>Review Request</span>
        </a>
      <?php endif ?>

      <a href="room"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $path === 'room.php' || $path === 'room' ? 'active-state' : '' ?>">
        <svg
          class="w-5 h-5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round">
          <circle cx="6.5" cy="13.5" r="2.5" />
          <path d="M1.5 21v-1.3c0-1.8 3.3-2.7 5-2.7s5 .9 5 2.7V21" />

          <circle cx="17.5" cy="13.5" r="2.5" />
          <path d="M12.5 21v-1.3c0-1.8 3.3-2.7 5-2.7s5 .9 5 2.7V21" />

          <path d="M9.2 9c2.6 0 4.8-1.7 4.8-3.8S11.8 1.5 9.2 1.5 4.5 3.2 4.5 5.2c0 .5.1 1 .4 1.4-.2.4-.4 1.1-.4 1.8.7-.3 1.3-.6 2-.9.8.5 1.8.8 2.7.8z" />

          <path d="M15.8 10c.9 0 1.8-.3 2.4-.7.4.2.7.3.9.5 0-.4.1-.9-.1-1.3.2-.4.4-.8.4-1.3 0-1.7-1.7-3-3.8-3-.3 0-.6 0-.9.1" />
        </svg>
        <span>Meeting</span>
      </a>

      <a href="book_session"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $path === 'book_session' || $path === 'book_session.php' ? 'active-state' : ''  ?>">
        <svg
          class="w-5 h-5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />

          <line x1="3" y1="10" x2="21" y2="10" />

          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="16" y1="2" x2="16" y2="6" />

          <path d="M9 14l6 6" />
          <path d="M15 14l-6 6" />
        </svg>
        <span>Book Session</span>
      </a>

      <?php if ($pending_assignments > 0 || $path === 'assignment' || $path === 'assignment.php'): ?>
      <a href="<?= $assign_href ?>"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $path === 'assignment' || $path === 'assignment.php' ? 'active-state' : '' ?>">
        <div class="relative">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
            <polyline points="10 9 9 9 8 9"/>
          </svg>
          <?php if ($pending_assignments > 0): ?>
            <span style="
              position:absolute; top:-6px; right:-6px;
              background:#ef4444; color:white;
              font-size:.6rem; font-weight:800;
              min-width:16px; height:16px; border-radius:999px;
              display:flex; align-items:center; justify-content:center;
              padding:0 4px; line-height:1;">
              <?= $pending_assignments ?>
            </span>
          <?php endif; ?>
        </div>
        <span>Assignments</span>
        <?php if ($pending_assignments > 0): ?>
          <span class="ml-auto text-[10px] font-bold bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-1.5 py-0.5 rounded-full">
            <?= $pending_assignments ?> due
          </span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <a href="lectures"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $path === 'lectures.php' || $path === 'lectures' ? 'active-state' : '' ?>">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
          <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
        </svg>
        <span>Lectures</span>
      </a>

      <a href="timetable"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $path === 'timetable.php' || $path === 'timetable' ? 'active-state' : '' ?>">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        <span>Timetable</span>
      </a>

      <a href="profile"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $path === 'profile.php' || $path === 'profile' ? 'active-state' : '' ?>">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
        </svg>
        <span>Profile</span>
      </a>
    </nav>
</div>
</aside>
</div>
<script>
  const navbar = document.getElementById('navbar');

  /* ───────── SIDEBAR PIN LOGIC ───────── */
  const pinBtn = document.getElementById("pinToggle");
  const body = document.body;

  // Restore state on desktop only
  if (
    window.innerWidth > 1024 &&
    localStorage.getItem("sidebarPinned") === "false"
  ) {
    body.classList.toggle("sidebar-collapsed");
    localStorage.setItem(
      "sidebarPinned",
      !body.classList.contains("sidebar-collapsed"),
    );
  }

  if (pinBtn) {
    pinBtn.addEventListener("click", () => {
      body.classList.toggle("sidebar-collapsed");
      localStorage.setItem(
        "sidebarPinned",
        !body.classList.contains("sidebar-collapsed"),
      );
    });
  }

  const sidebar = document.getElementById("sidebar");

  sidebar.addEventListener("mouseenter", () => {
    if (
      document.body.classList.contains("sidebar-collapsed") &&
      window.innerWidth > 1024
    ) {
      sidebar.classList.add("sidebar-hovered");
    }
  });

  sidebar.addEventListener("mouseleave", () => {
    sidebar.classList.remove("sidebar-hovered");
  });

  // Auto-collapse sidebar on mobile
  if (window.innerWidth <= 1024) {
    document.body.classList.add("sidebar-collapsed");
  }

  /* ───────── MOBILE SIDEBAR FUNCTIONS ───────── */
  function openMobileSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("mobileOverlay");
    sidebar.classList.add("mobile-open");
    overlay.classList.add("active");
    document.body.style.overflow = "hidden";
  }

  function closeMobileSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("mobileOverlay");
    sidebar.classList.remove("mobile-open");
    overlay.classList.remove("active");
    document.body.style.overflow = "";
  }

  // Close sidebar on window resize to desktop
  window.addEventListener("resize", () => {
    if (window.innerWidth > 1024) {
      closeMobileSidebar();
    }
  });
  if ("scrollRestoration" in history) {
    history.scrollRestoration = "manual";
  }
  window.scrollTo(0, 0);

  // Navigation active state
  function setActive(el) {
    document.querySelectorAll(".nav-item").forEach((item) => {
      item.classList.remove("active-state");
      item.classList.add("text-ink-light", "dark:text-ink-dark");
      item.classList.add("hover:bg-royal-basic/5", "dark:hover:bg-royal-soft/10");
    });

    el.classList.add("active-state");
    el.classList.remove(
      "text-ink-light",
      "dark:text-ink-dark",
      "hover:bg-royal-basic/5",
      "dark:hover:bg-royal-soft/10",
    );
  }
</script>