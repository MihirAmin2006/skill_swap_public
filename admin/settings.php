<?php
/* ═══════════════════════════════════════════════════════════════
   settings.php  –  SkillSwap Admin  –  Configuration Management
   ═══════════════════════════════════════════════════════════════ */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';
require_once __DIR__ . '/validations.php';

$current_page = 'settings.php';
$error = '';
$success = '';

// ── Handle Settings Update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } elseif (!checkRateLimit('settings_update', 5, 300)) {
        $error = 'Too many setting updates. Please wait before trying again.';
    } else {
        // Site Settings
        $site_name = sanitizeInput($_POST['site_name'] ?? 'SkillSwap');
        $site_description = sanitizeInput($_POST['site_description'] ?? '');
        $admin_email = sanitizeInput($_POST['admin_email'] ?? '');
        $max_users_per_subject = (int)($_POST['max_users_per_subject'] ?? 50);
        $default_credits = (int)($_POST['default_credits'] ?? 100);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $registration_enabled = isset($_POST['registration_enabled']) ? 1 : 0;
        
        // Validation
        if (empty($site_name) || strlen($site_name) > 50) {
            $error = 'Site name is required and must be under 50 characters.';
        } elseif (!validateEmail($admin_email)) {
            $error = 'Invalid admin email address.';
        } elseif ($max_users_per_subject < 1 || $max_users_per_subject > 1000) {
            $error = 'Max users per subject must be between 1 and 1000.';
        } elseif ($default_credits < 0 || $default_credits > 10000) {
            $error = 'Default credits must be between 0 and 10000.';
        } else {
            // Update settings in database
            $settings = [
                'site_name' => $site_name,
                'site_description' => $site_description,
                'admin_email' => $admin_email,
                'max_users_per_subject' => $max_users_per_subject,
                'default_credits' => $default_credits,
                'maintenance_mode' => $maintenance_mode,
                'registration_enabled' => $registration_enabled
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->bind_param("sss", $key, $value, $value);
                $stmt->execute();
            }
            
            logAdminAction('SETTINGS_UPDATE', json_encode($settings));
            $success = 'Settings updated successfully!';
        }
    }
}

// ── Handle Password Change ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } elseif (!checkRateLimit('password_change', 3, 900)) {
        $error = 'Too many password change attempts. Please wait before trying again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (!validatePassword($new_password)) {
            $error = 'New password must be at least 8 characters with 1 uppercase, 1 lowercase, and 1 number.';
        } else {
            // Verify current password
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT password FROM user_master WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE user_master SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                $stmt->execute();
                
                logAdminAction('PASSWORD_CHANGE', 'Admin password changed');
                $success = 'Password changed successfully!';
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
}

// ── Fetch Current Settings ──
$settings_query = "SELECT setting_key, setting_value FROM admin_settings";
$settings_result = mysqli_query($conn, $settings_query);
$settings = [];

while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values if not set
$settings = array_merge([
    'site_name' => 'SkillSwap',
    'site_description' => 'College Skill-Swap & Micro-Mentoring Platform',
    'admin_email' => 'admin@skillswap.com',
    'max_users_per_subject' => 50,
    'default_credits' => 100,
    'maintenance_mode' => 0,
    'registration_enabled' => 1
], $settings);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings · SkillSwap Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:opsz,wght@9..40,300;400;500;600;700&display=swap" rel="stylesheet">
  <?php include_once __DIR__ . '/../includes/scripts/common.php'; ?>
  <link rel="stylesheet" href="../assets/styles/admin/common.css">
  <link rel="stylesheet" href="../assets/styles/admin/admin_tailwind_css.css">
  <script src="../assets/js/tailwind.js.php" defer></script>
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
    <div class="flex items-center justify-between px-7 py-3.5 gap-3 flex-wrap">
      <div class="flex items-center gap-3.5">
        <button onclick="openMobileSidebar()" class="sidebar-trigger lg:hidden">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <div>
          <h1 class="font-bold leading-none tracking-tight text-royal-primary"
              style="font-family:'Playfair Display',Georgia,serif;font-size:20px">System Settings</h1>
          <p class="section-label mt-1" style="letter-spacing:.1em">Configure platform preferences</p>
        </div>
      </div>
      <div class="flex items-center gap-2.5">
        <?php if ($success): ?>
        <span class="badge badge-green">✓ <?= htmlspecialchars($success) ?></span>
        <?php endif; ?>
        <?php if ($error): ?>
        <span class="badge badge-red">✗ <?= htmlspecialchars($error) ?></span>
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

    <!-- Site Settings -->
    <div class="ss-card enter p-7">
      <div class="flex items-start gap-4 mb-6">
        <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-xl"
             style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);box-shadow:0 4px 12px rgba(30,58,138,.25)">
          <svg class="w-4.5 h-4.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
          </svg>
        </div>
        <div>
          <h3 class="card-title">Site Configuration</h3>
          <p class="section-label mt-1" style="letter-spacing:.1em">General platform settings</p>
        </div>
      </div>

      <form method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <div class="grid gap-5" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
          <div>
            <label class="block text-sm font-semibold mb-2 ink-text">Site Name</label>
            <input class="form-input w-full" type="text" name="site_name" 
                   value="<?= htmlspecialchars($settings['site_name']) ?>" required>
          </div>
          
          <div>
            <label class="block text-sm font-semibold mb-2 ink-text">Admin Email</label>
            <input class="form-input w-full" type="email" name="admin_email" 
                   value="<?= htmlspecialchars($settings['admin_email']) ?>" required>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold mb-2 ink-text">Site Description</label>
          <textarea class="form-input w-full" name="site_description" rows="3"><?= htmlspecialchars($settings['site_description']) ?></textarea>
        </div>

        <div class="grid gap-5" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
          <div>
            <label class="block text-sm font-semibold mb-2 ink-text">Max Users per Subject</label>
            <input class="form-input w-full" type="number" name="max_users_per_subject" 
                   value="<?= (int)$settings['max_users_per_subject'] ?>" min="1" max="1000" required>
          </div>
          
          <div>
            <label class="block text-sm font-semibold mb-2 ink-text">Default Credits</label>
            <input class="form-input w-full" type="number" name="default_credits" 
                   value="<?= (int)$settings['default_credits'] ?>" min="0" max="10000" required>
          </div>
        </div>

        <div class="flex gap-6">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="maintenance_mode" value="1" 
                   <?= $settings['maintenance_mode'] ? 'checked' : '' ?> 
                   class="w-4 h-4 text-royal border-gray-300 rounded focus:ring-royal">
            <span class="text-sm font-semibold ink-text">Maintenance Mode</span>
          </label>
          
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="registration_enabled" value="1" 
                   <?= $settings['registration_enabled'] ? 'checked' : '' ?> 
                   class="w-4 h-4 text-royal border-gray-300 rounded focus:ring-royal">
            <span class="text-sm font-semibold ink-text">Enable Registration</span>
          </label>
        </div>

        <button type="submit" name="update_settings" class="btn btn-royal">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
            <polyline points="17 21 17 13 7 13 7 21"/>
            <polyline points="7 3 7 8 15 8"/>
          </svg>
          Save Settings
        </button>
      </form>
    </div>

    <!-- Password Change -->
    <div class="ss-card enter d1 p-7">
      <div class="flex items-start gap-4 mb-6">
        <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-xl"
             style="background:linear-gradient(135deg,#dc2626,#ef4444);box-shadow:0 4px 12px rgba(239,68,68,.25)">
          <svg class="w-4.5 h-4.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
        </div>
        <div>
          <h3 class="card-title">Change Password</h3>
          <p class="section-label mt-1" style="letter-spacing:.1em">Update your admin password</p>
        </div>
      </div>

      <form method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <div class="grid gap-5" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
          <div>
            <label class="block text-sm font-semibold mb-2 ink-text">Current Password</label>
            <input class="form-input w-full" type="password" name="current_password" required>
          </div>
          
          <div>
            <label class="block text-sm font-semibold mb-2 ink-text">New Password</label>
            <input class="form-input w-full" type="password" name="new_password" required>
          </div>
          
          <div>
            <label class="block text-sm font-semibold mb-2 ink-text">Confirm New Password</label>
            <input class="form-input w-full" type="password" name="confirm_password" required>
          </div>
        </div>

        <button type="submit" name="change_password" class="btn btn-danger">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 12l2 2 4-4"/>
            <path d="M21 12c-1.5-4.5-6-7.5-12-7.5S1.5 7.5 0 12c1.5 4.5 6 7.5 12 7.5s10.5-3 12-7.5z"/>
          </svg>
          Change Password
        </button>
      </form>
    </div>

    <!-- System Info -->
    <div class="ss-card enter d2 p-7">
      <div class="flex items-start gap-4 mb-6">
        <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-xl"
             style="background:linear-gradient(135deg,#059669,#10b981);box-shadow:0 4px 12px rgba(16,185,129,.25)">
          <svg class="w-4.5 h-4.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
          </svg>
        </div>
        <div>
          <h3 class="card-title">System Information</h3>
          <p class="section-label mt-1" style="letter-spacing:.1em">Platform status and details</p>
        </div>
      </div>

      <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(250px,1fr))">
        <div class="info-row">
          <div class="flex-shrink-0">
            <svg class="w-4 h-4 icon-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
          <div>
            <p class="text-xs font-semibold text-muted">PHP Version</p>
            <p class="text-sm font-bold ink-text"><?= PHP_VERSION ?></p>
          </div>
        </div>

        <div class="info-row">
          <div class="flex-shrink-0">
            <svg class="w-4 h-4 icon-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            </svg>
          </div>
          <div>
            <p class="text-xs font-semibold text-muted">MySQL Version</p>
            <p class="text-sm font-bold ink-text"><?= mysqli_get_server_info($conn) ?></p>
          </div>
        </div>

        <div class="info-row">
          <div class="flex-shrink-0">
            <svg class="w-4 h-4 icon-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/>
              <path d="M2 17l10 5 10-5"/>
              <path d="M2 12l10 5 10-5"/>
            </svg>
          </div>
          <div>
            <p class="text-xs font-semibold text-muted">Platform Version</p>
            <p class="text-sm font-bold ink-text">v2.1.0</p>
          </div>
        </div>

        <div class="info-row">
          <div class="flex-shrink-0">
            <svg class="w-4 h-4 icon-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div>
            <p class="text-xs font-semibold text-muted">Last Backup</p>
            <p class="text-sm font-bold ink-text"><?= date('M j, Y H:i', filemtime(__DIR__ . '/../db/SKILL_SWAP.sql')) ?></p>
          </div>
        </div>
      </div>
    </div>

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
