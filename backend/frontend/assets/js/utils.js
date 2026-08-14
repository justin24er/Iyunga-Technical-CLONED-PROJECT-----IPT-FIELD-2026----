// ---------- Toast notifications ----------
function ensureToastContainer() {
  let el = document.querySelector(".toast-container");
  if (!el) {
    el = document.createElement("div");
    el.className = "toast-container";
    document.body.appendChild(el);
  }
  return el;
}

function showToast(message, type = "info") {
  const container = ensureToastContainer();
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

// ---------- Formatting ----------
function formatCurrency(amount, currency = "TZS") {
  return new Intl.NumberFormat("en-TZ", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(amount || 0);
}

function formatCompactNumber(value) {
  return new Intl.NumberFormat("en", { notation: "compact" }).format(value || 0);
}

function formatDate(dateStr, opts = { day: "numeric", month: "short", year: "numeric" }) {
  return new Date(dateStr).toLocaleDateString("en-GB", opts);
}

function initials(name) {
  return (name || "")
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

// ---------- Debounce ----------
function debounce(fn, delay = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

// ---------- Theme ----------
function applyStoredTheme() {
  const stored = localStorage.getItem("academia_theme") || "system";
  const systemDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  const isDark = stored === "dark" || (stored === "system" && systemDark);
  document.documentElement.setAttribute("data-theme", isDark ? "dark" : "light");
}

function setTheme(mode) {
  localStorage.setItem("academia_theme", mode);
  applyStoredTheme();
}
