<?php
// Tell browser this is JavaScript
header("Content-Type: application/javascript");

// Example: get admin name from session
session_start();
$adminName = $_SESSION['admin_name'] ?? "Administrator";
?>

// ===============================
// Dashboard Control Script (PHP Powered)
// ===============================

document.addEventListener("DOMContentLoaded", function () {

    const ADMIN_NAME = "<?php echo $adminName; ?>";

    console.log("Welcome,", ADMIN_NAME);

    // 📱 MOBILE SIDEBAR
    window.openMobileSidebar = function () {
        document.getElementById("sidebar")?.classList.add("translate-x-0");
        document.getElementById("mobileOverlay")?.classList.add("active");
    };

    window.closeMobileSidebar = function () {
        document.getElementById("sidebar")?.classList.remove("translate-x-0");
        document.getElementById("mobileOverlay")?.classList.remove("active");
    };

    // 🔽 DROPDOWN TOGGLE
    window.toggleDropdown = function (id) {
        const dropdown = document.getElementById(id);
        if (!dropdown) return;

        document.querySelectorAll("[id$='Dropdown']").forEach(el => {
            if (el !== dropdown) el.classList.add("hidden");
        });

        dropdown.classList.toggle("hidden");
    };

    // 🌙 DARK MODE TOGGLE
    window.toggleTheme = function () {
        const html = document.documentElement;
        const knob = document.getElementById("toggleKnob");
        const sun = document.getElementById("iconSun");
        const moon = document.getElementById("iconMoon");

        html.classList.toggle("dark");

        if (html.classList.contains("dark")) {
            knob.style.transform = "translateX(24px)";
            sun.classList.add("hidden");
            moon.classList.remove("hidden");
            localStorage.setItem("theme", "dark");
        } else {
            knob.style.transform = "translateX(0)";
            sun.classList.remove("hidden");
            moon.classList.add("hidden");
            localStorage.setItem("theme", "light");
        }
    };

    // 🚪 LOGOUT CONFIRMATION
    const logoutBtn = document.getElementById("logoutButton");
    if (logoutBtn) {
        logoutBtn.addEventListener("click", function () {
            const confirmLogout = confirm("Admin " + ADMIN_NAME + ", logout?");
            if (confirmLogout) {
                window.location.href = "../logout.php";
            }
        });
    }

});