<?php
header("Content-Type: application/javascript");
?>

// ================= GLOBAL VARIABLES =================
let currentDate = new Date();
let selectedDate = new Date();
let timetableData = {};

let title, grid, prevBtn, nextBtn, tbody;

const monthNames = [
    "January","February","March","April","May","June",
    "July","August","September","October","November","December"
];

// ================= FETCH TIMETABLE =================
async function fetchTimetable() {
    try {
        const response = await fetch('/skill_swap/user/fetch_timetable');
        if (!response.ok) throw new Error(`HTTP ${response.status} - ${response.statusText}`);

        timetableData = await response.json();
        renderCalendar();
        loadTimetable(formatDate(selectedDate));

    } catch (error) {
        console.error("Error fetching timetable:", error);
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-10 italic text-red-500">
                    Failed to load timetable.
                </td>
            </tr>`;
    }
}

// ================= FORMAT DATE =================
function formatDate(dateObj) {
    return `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2,"0")}-${String(dateObj.getDate()).padStart(2,"0")}`;
}

// ================= RENDER CALENDAR =================
function renderCalendar() {
    if (!grid || !title) return;

    grid.innerHTML = "";
    title.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const offset = firstDay === 0 ? 6 : firstDay - 1;

    // empty cells for offset
    for (let i = 0; i < offset; i++) {
        const emptyCell = document.createElement("div");
        emptyCell.className = "border-b border-r min-h-[60px] dark:border-white/10";
        grid.appendChild(emptyCell);
    }

    // days
    for (let day = 1; day <= daysInMonth; day++) {
        const dateObj = new Date(year, month, day);
        const dateStr = formatDate(dateObj);

        const cell = document.createElement("div");
        cell.textContent = day;
        cell.className = "p-3 cursor-pointer border-b border-r border-gray-300/40 dark:border-white/10 relative flex items-center justify-center min-h-[60px]";

        // Dot Indicator
        if (timetableData.hasOwnProperty(dateStr)) {
            const dot = document.createElement("span");
            dot.className = "absolute bottom-2 left-1/2 -translate-x-1/2 w-2 h-2 bg-blue-500 rounded-full";
            cell.appendChild(dot);
        }

        // Selected date style
        if (dateObj.toDateString() === selectedDate.toDateString()) {
            cell.classList.add("bg-blue-600", "text-white", "font-bold");
        }

        cell.onclick = () => {
            selectedDate = dateObj;
            renderCalendar();
            loadTimetable(dateStr);
        };

        grid.appendChild(cell);
    }
}

// ================= LOAD TIMETABLE =================
function loadTimetable(dateStr) {
    if (!tbody) return;

    tbody.innerHTML = "";
    const data = timetableData[dateStr] || [];

    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-10 italic">
                    No classes scheduled for this date.
                </td>
            </tr>`;
        updateStats(data);
        return;
    }

    data.forEach(item => {
        let badgeClass = "";
        const status = item.status;

        if (status === "completed") {
            badgeClass = "bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200/50 dark:border-green-500/20";
        } else if (status === "upcoming") {
            badgeClass = "bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 border border-yellow-200/50 dark:border-yellow-500/20";
        } else if (status === "started") {
            badgeClass = "bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border border-blue-200/50 dark:border-blue-500/20";
        }

        let action = "—";
        if (status === "started") {
            action = `
                <a href="room"
                    class="btn px-4 py-2 text-xs font-black bg-royal-mid text-white rounded-lg hover:bg-royal-basic transition-all shadow-md active:scale-95">
                    JOIN
                </a>`;
        }

        tbody.innerHTML += `
            <tr class="hover:bg-royal-basic/[0.02] transition-colors">
                <td class="px-6 py-5 text-sm font-semibold text-royal-basic dark:text-royal-soft">${item.time}</td>
                <td class="px-6 py-5"><div class="font-bold flex items-center gap-3">${item.subject}</div></td>
                <td class="px-6 py-5 text-sm italic text-muted-light dark:text-muted-dark">
                    <span class="px-3 py-1 text-[10px] uppercase tracking-wider rounded-full ${badgeClass} whitespace-nowrap">${status}</span>
                </td>
                <td class="px-6 py-5 text-center">${action}</td>
            </tr>`;
    });

    updateStats(data);
}

// ================= UPDATE STATS =================
function updateStats(data) {
    const now = new Date();
    document.getElementById("totalClasses").innerText = data.length;
    document.getElementById("todayClasses").innerText = data.filter(d => {
    const [hour, minute] = d.time.split(':').map(Number);
    const classDate = new Date(selectedDate);
    classDate.setHours(hour, minute, 0, 0);
    return d.status === "started" && classDate <= new Date();
}).length;

    if (data.length > 0) {
        // Find the next class that is in the future
        const next = data.find(d => {
            const [hour, minute] = d.time.split(':').map(Number);
            const classDate = new Date(selectedDate);
            classDate.setHours(hour, minute, 0, 0);
            return classDate > now; // only future classes
        });

        if (next) {
            document.getElementById("lastClassTime").innerText = next.time;
        } else {
            document.getElementById("lastClassTime").innerText = "—"; // all classes passed
        }
    } else {
        document.getElementById("lastClassTime").innerText = "—";
    }
}

// ================= DOM READY =================
document.addEventListener("DOMContentLoaded", () => {
    title = document.getElementById("calendarTitle");
    grid = document.getElementById("calendarGrid");
    prevBtn = document.getElementById("prevMonth");
    nextBtn = document.getElementById("nextMonth");
    tbody = document.getElementById("timetableBody");

    prevBtn.onclick = () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    };

    nextBtn.onclick = () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    };

    fetchTimetable(); // initial fetch

    // Optional auto-refresh every 15 seconds
    setInterval(fetchTimetable, 15000);
});