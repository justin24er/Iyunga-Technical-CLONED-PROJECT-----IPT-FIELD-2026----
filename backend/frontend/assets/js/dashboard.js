let attendanceChart, demographicsChart;

document.addEventListener("DOMContentLoaded", async () => {
  applyStoredTheme();
  initSidebarToggle();
  initUserMenu();

  const user = await requireSession();
  if (!user) return; // requireSession already redirected to login

  renderGreeting(user);
  loadSummary(user);
  loadAttendance(10);
  loadDemographics();
  loadPerformance();
  renderCalendar(new Date());

  document.getElementById("attendance-range")?.addEventListener("change", (e) => {
    loadAttendance(Number(e.target.value));
  });

  document.getElementById("logout-btn")?.addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });
});

// ---------- Header ----------
function renderGreeting(user) {
  const hour = new Date().getHours();
  const greeting = hour < 12 ? "Good Morning" : hour < 17 ? "Good Afternoon" : "Good Evening";

  document.getElementById("greeting-text").textContent = `${greeting}, ${user.name.split(" ")[0]}`;
  document.getElementById("school-welcome").textContent = `Welcome to ${user.school?.name ?? "Academia"}`;
  document.getElementById("user-name").textContent = user.name;
  document.getElementById("user-email").textContent = user.email;

  const avatar = document.getElementById("user-avatar");
  if (user.avatar) {
    avatar.src = user.avatar;
  } else {
    avatar.replaceWith(Object.assign(document.createElement("div"), {
      id: "user-avatar",
      textContent: initials(user.name),
      style: "width:44px;height:44px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;",
    }));
  }
}

// ---------- Stat cards ----------
async function loadSummary(user) {
  const grid = document.getElementById("stats-grid");
  try {
    const { data } = await api.get("/dashboard/summary");
    const currency = user.school?.currency || "TZS";

    grid.innerHTML = `
      ${statCard("Total Students", data.totalStudents.toLocaleString(), "tone-green", iconUsers())}
      ${statCard("Total Teachers", data.totalTeachers.toLocaleString(), "tone-amber", iconBriefcase())}
      ${statCard("Total Subjects", data.totalSubjects.toLocaleString(), "tone-blue", iconBook())}
      ${statCard("Total Earnings", formatCurrency(data.totalRevenue, currency), "tone-rose", iconCoin())}
    `;

    renderEvents(data.upcomingEvents);
  } catch (err) {
    grid.innerHTML = `<div class="error-state">Failed to load dashboard statistics.</div>`;
    showToast(err.message, "error");
  }
}

function statCard(label, value, tone, icon) {
  return `
    <div class="card stat-card ${tone}">
      <button class="stat-card-menu" aria-label="Card options">⋮</button>
      <div class="stat-icon">${icon}</div>
      <div class="stat-label">${label}</div>
      <div class="stat-value">${value}</div>
    </div>`;
}

// ---------- Attendance chart ----------
async function loadAttendance(rangeDays) {
  const wrap = document.getElementById("attendance-chart-wrap");
  try {
    const { data } = await api.get("/dashboard/attendance", { range: rangeDays });

    if (!data.length) {
      wrap.innerHTML = `<div class="empty-state">No attendance has been recorded yet for this period.</div>`;
      return;
    }

    if (!document.getElementById("attendanceChart")) {
      wrap.innerHTML = `<canvas id="attendanceChart"></canvas>`;
    }
    const ctx = document.getElementById("attendanceChart").getContext("2d");

    const labels = data.map((d) => formatDate(d.date, { day: "2-digit", month: "short" }));
    const present = data.map((d) => d.present);
    const absent = data.map((d) => d.absent);

    if (attendanceChart) attendanceChart.destroy();
    attendanceChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels,
        datasets: [
          { label: "Present", data: present, backgroundColor: "#d99000", borderRadius: 6, maxBarThickness: 14 },
          { label: "Absent", data: absent, backgroundColor: "#171717", borderRadius: 6, maxBarThickness: 14 },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: "#ebe7de" }, beginAtZero: true },
        },
      },
    });
  } catch (err) {
    wrap.innerHTML = `<div class="error-state">Failed to load the attendance report.</div>`;
  }
}

// ---------- Demographics donut ----------
async function loadDemographics() {
  const wrap = document.getElementById("demographics-wrap");
  try {
    const { data } = await api.get("/dashboard/student-demographics");

    document.getElementById("donut-total").textContent = data.total.toLocaleString();
    document.getElementById("donut-girls").textContent = data.girls.toLocaleString();
    document.getElementById("donut-boys").textContent = data.boys.toLocaleString();

    const ctx = document.getElementById("demographicsChart").getContext("2d");
    if (demographicsChart) demographicsChart.destroy();
    demographicsChart = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: ["Girls", "Boys"],
        datasets: [{ data: [data.girls, data.boys], backgroundColor: ["#d99000", "#7a5200"], borderWidth: 0 }],
      },
      options: {
        cutout: "72%",
        plugins: { legend: { display: false } },
      },
    });
  } catch (err) {
    wrap.innerHTML = `<div class="error-state">Failed to load student demographics.</div>`;
  }
}

// ---------- Student performance table ----------
async function loadPerformance() {
  const tbody = document.getElementById("performance-body");
  try {
    const { data } = await api.get("/dashboard/performance", { limit: 6 });

    if (!data.length) {
      tbody.innerHTML = `<tr><td colspan="5" class="empty-state">No approved results yet.</td></tr>`;
      return;
    }

    tbody.innerHTML = data
      .map(
        (row) => `
      <tr>
        <td>
          <div class="student-cell">
            <img src="${row.photo || `https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(row.name)}`}" alt="">
            <div>
              <div class="name">${row.name}</div>
              <div class="id">ID: ${row.studentNumber}</div>
            </div>
          </div>
        </td>
        <td>${row.class}</td>
        <td>Grade ${row.grade ?? "—"}</td>
        <td>${row.percentage.toFixed(2)}%</td>
        <td><button class="btn btn-outline" onclick="location.href='student-view.html?id=${row.studentId}'">View</button></td>
      </tr>`
      )
      .join("");
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="5" class="error-state">Failed to load student performance.</td></tr>`;
  }
}

// ---------- Upcoming events (right panel) ----------
function renderEvents(events) {
  const list = document.getElementById("events-list");
  if (!events?.length) {
    list.innerHTML = `<div class="empty-state">No upcoming events.</div>`;
    return;
  }
  list.innerHTML = events
    .map(
      (ev) => `
    <div class="event-item">
      <div>
        <div class="dot-date">${formatDate(ev.date)}</div>
        <div class="title">${ev.title}</div>
      </div>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    </div>`
    )
    .join("");
}

// ---------- Calendar ----------
function renderCalendar(date) {
  const monthLabel = document.getElementById("calendar-month");
  const grid = document.getElementById("calendar-grid");
  const year = date.getFullYear();
  const month = date.getMonth();
  const today = new Date();

  monthLabel.textContent = date.toLocaleDateString("en-GB", { month: "long", year: "numeric" });

  const firstDay = new Date(year, month, 1);
  const startOffset = firstDay.getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const daysInPrevMonth = new Date(year, month, 0).getDate();

  const dows = ["S", "M", "T", "W", "T", "F", "S"];
  let html = dows.map((d) => `<div class="dow">${d}</div>`).join("");

  for (let i = startOffset - 1; i >= 0; i--) {
    html += `<div class="calendar-day muted">${daysInPrevMonth - i}</div>`;
  }
  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
    html += `<div class="calendar-day ${isToday ? "today" : ""}">${d}</div>`;
  }
  const remaining = (7 - (grid.children.length % 7)) % 7;
  for (let d = 1; d <= 6; d++) {
    if ((startOffset + daysInMonth + d - 1) % 7 === 0) break;
    html += `<div class="calendar-day muted">${d}</div>`;
  }

  grid.innerHTML = html;

  document.getElementById("calendar-prev").onclick = () => renderCalendar(new Date(year, month - 1, 1));
  document.getElementById("calendar-next").onclick = () => renderCalendar(new Date(year, month + 1, 1));
}

// ---------- Sidebar / user menu interactions ----------
function initSidebarToggle() {
  const shell = document.getElementById("app-shell");
  document.getElementById("collapse-toggle")?.addEventListener("click", () => {
    shell.classList.toggle("sidebar-collapsed");
  });
  document.getElementById("mobile-toggle")?.addEventListener("click", () => {
    document.querySelector(".sidebar").classList.toggle("open");
  });
}

function initUserMenu() {
  const trigger = document.getElementById("user-menu-trigger");
  const dropdown = document.getElementById("user-dropdown");
  trigger?.addEventListener("click", (e) => {
    e.stopPropagation();
    dropdown.classList.toggle("open");
  });
  document.addEventListener("click", () => dropdown?.classList.remove("open"));
}

// ---------- Inline icon helpers ----------
function iconUsers() {
  return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7a5200" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`;
}
function iconBriefcase() {
  return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7a5200" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>`;
}
function iconBook() {
  return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7a5200" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>`;
}
function iconCoin() {
  return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7a5200" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9h4.5a1.5 1.5 0 0 1 0 3H10a1.5 1.5 0 0 0 0 3h5"/></svg>`;
}
