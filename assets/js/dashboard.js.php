<?php
header('Content-Type: application/javascript');
?>



// Close mobile sidebar after selection
if (window.innerWidth < 1024) {
  closeMobileSidebar();
}


let currentDropdown = null;

function toggleDropdown(id) {
  const dropdown = document.getElementById(id);
  if (currentDropdown && currentDropdown !== dropdown) {
    currentDropdown.classList.add("hidden", "opacity-0");
  }
  dropdown.classList.toggle("hidden");
  setTimeout(() => dropdown.classList.toggle("opacity-0"), 10);
  currentDropdown = dropdown.classList.contains("hidden") ? null : dropdown;
}

// Close dropdowns when clicking outside
document.addEventListener("click", (e) => {
  if (
    !e.target.closest('[onclick^="toggleDropdown"]') &&
    !e.target.closest('[id$="Dropdown"]')
  ) {
    document.querySelectorAll('[id$="Dropdown"]').forEach((dropdown) => {
      dropdown.classList.add("opacity-0");
      setTimeout(() => dropdown.classList.add("hidden"), 300);
    });
    currentDropdown = null;
  }
});

//Logout Functionality
document.getElementById("logoutButton").addEventListener("click", () => {
  localStorage.clear();
  sessionStorage.clear();
  fetch("../Auth/logout", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      logout: true,
    }),
  }).then((response) => {
    if (response.ok) {
      window.location.href = "../Auth/sign_in";
    } else {
      console.error("Logout failed");
    }
  });
});