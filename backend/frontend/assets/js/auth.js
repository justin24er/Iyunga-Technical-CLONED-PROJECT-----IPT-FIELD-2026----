// ---------- Login page ----------
function initLoginForm() {
  const form = document.getElementById("login-form");
  if (!form) return;

  const errorBanner = document.getElementById("login-error");
  const submitBtn = document.getElementById("login-submit");
  const toggleBtn = document.getElementById("toggle-password");
  const passwordInput = document.getElementById("password");

  toggleBtn?.addEventListener("click", () => {
    const isHidden = passwordInput.type === "password";
    passwordInput.type = isHidden ? "text" : "password";
    toggleBtn.textContent = isHidden ? "Hide" : "Show";
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    errorBanner.classList.remove("show");

    const email = document.getElementById("email").value.trim();
    const password = passwordInput.value;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Signing in…';

    try {
      const { data } = await api.post("/auth/login", { email, password });
      showToast(`Welcome back, ${data.name.split(" ")[0]}!`, "success");
      setTimeout(() => (location.href = "dashboard.html"), 400);
    } catch (err) {
      errorBanner.textContent = err.message;
      errorBanner.classList.add("show");
      submitBtn.disabled = false;
      submitBtn.textContent = "Sign In";
    }
  });
}

// ---------- Route guard for protected pages ----------
async function requireSession() {
  try {
    const { data } = await api.get("/auth/me");
    return data;
  } catch {
    location.href = "login.html";
    return null;
  }
}

// ---------- Logout ----------
async function logout() {
  try {
    await api.post("/auth/logout");
  } finally {
    location.href = "login.html";
  }
}

document.addEventListener("DOMContentLoaded", initLoginForm);
