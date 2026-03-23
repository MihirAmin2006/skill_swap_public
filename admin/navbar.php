<?php
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';
include_once __DIR__ . '/update_status_meeting.php';
$user = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT COUNT(*) as `total` FROM `meetings` WHERE `teacher_id` = ? AND `approved` = '2'");
$stmt->bind_param("i", $user);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
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
          <p class="text-xs text-muted-light dark:text-muted-dark">Admin Portal</p>
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
      <a href="index.php"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $current_page === 'index.php' ? 'active-state' : ''  ?>">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
          <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
        <span>Dashboard</span>
      </a>
      
      <a href="manage_students.php"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $current_page === 'manage_students.php' ? 'active-state' : ''  ?>">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <span>Operatives</span>
      </a>
      
      <a href="manage_subjects.php"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $current_page === 'manage_subjects.php' ? 'active-state' : ''  ?>">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
          <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
        <span>Skill Sectors</span>
      </a>
      
      <!-- <a href="settings.php"
        class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
              text-ink-light dark:text-ink-dark
              hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
              transition-all duration-200 group <?= $current_page === 'settings.php' ? 'active-state' : ''  ?>">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="3"/>
          <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
        </svg>
        <span>Settings</span>
      </a> -->
      
      <div class="pt-4 mt-4 border-t border-royal-basic/10 dark:border-royal-violet/20">
        <a href="../Auth/logout.php"
          class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
                text-red-600 dark:text-red-400
                hover:bg-red-50 dark:hover:bg-red-900/20
                transition-all duration-200 group">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          <span>Logout</span>
        </a>
      </div>
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