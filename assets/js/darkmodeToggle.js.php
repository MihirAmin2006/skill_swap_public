<?php
header('Content-Type: application/javascript');
?>

// restore on load
document.addEventListener("DOMContentLoaded", () => {
  const dark = localStorage.getItem("theme") === "dark";
  applyTheme(dark);
});

// Dark mode toggle
function applyTheme(dark) {
  const html = document.documentElement;

  html.classList.toggle("dark", dark);
  localStorage.setItem("theme", dark ? "dark" : "light");

  // icons
  document.getElementById("iconSun")?.classList.toggle("hidden", dark);
  document.getElementById("iconMoon")?.classList.toggle("hidden", !dark);

  // knob
  const knob = document.getElementById("toggleKnob");
  if (knob) {
    knob.style.transform = dark ? "translateX(24px)" : "translateX(0px)";
  }

  // SSO icon
  const ssoSvg = document.querySelector("#ssoBtn svg");
  if (ssoSvg) {
    ssoSvg.setAttribute("stroke", dark ? "#a5b4fc" : "#1e3a8a");
  }

  // hint pills
  ["hintLen", "hintUpper", "hintNum"].forEach((id) => {
    const el = document.getElementById(id);
    if (!el || el.classList.contains("bg-green-100")) return;

    el.classList.remove(
      "bg-royal-DEFAULT/7",
      "text-muted-light",
      "bg-royal-indigo/13",
      "text-muted-dark",
    );

    if (dark) {
      el.classList.add("bg-royal-indigo/13", "text-muted-dark");
    } else {
      el.classList.add("bg-royal-DEFAULT/7", "text-muted-light");
    }
  });
}

function toggleTheme() {
  const dark = !document.documentElement.classList.contains("dark");
  applyTheme(dark);
}