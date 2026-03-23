<?php
header('Content-Type: application/javascript');
?>

  document.addEventListener("DOMContentLoaded", () => {


    const lecturesNav = document.querySelector('a[href="lectures.php"]');
    if (lecturesNav) {
      lecturesNav.classList.add("active-state");
    }

    // Close sidebar on mobile
    if (window.innerWidth < 1024) {
      document.querySelectorAll(".nav-item").forEach(item => {
        item.addEventListener("click", closeMobileSidebar);
      });
    }

    /* ───────── FILTERING LOGIC ───────── */

    const rows = document.querySelectorAll("tbody tr");

    const searchInput = document.getElementById("filterSearch");
    const dateInput = document.getElementById("filterDate");
    const subjectInput = document.getElementById("filterSubject");
    const teacherInput = document.getElementById("filterTeacher");
    const statusInput = document.getElementById("filterStatus");

    function applyFilters() {
      const search = searchInput.value.toLowerCase();
      const date = dateInput.value;
      const subject = subjectInput.value;
      const teacher = teacherInput.value;
      const status = statusInput.value;

      rows.forEach(row => {
        const topic = row.dataset.topic.toLowerCase();
        const rDate = row.dataset.date;
        const rSub = row.dataset.subject;
        const rTeach = row.dataset.teacher;
        const rStatus = row.dataset.status;

        let visible = true;

        if (search && !topic.includes(search)) visible = false;
        if (date && rDate !== date) visible = false;
        if (subject && rSub !== subject) visible = false;
        if (teacher && rTeach !== teacher) visible = false;
        if (status && rStatus !== status) visible = false;

        row.style.display = visible ? "" : "none";
      });
    }

    [searchInput, dateInput, subjectInput, teacherInput, statusInput]
      .forEach(el => el && el.addEventListener("input", applyFilters));
  });
