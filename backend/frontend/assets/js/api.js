// Backend base URL. Configure per-environment by editing this value at build/deploy time,
// or injecting it via a small <script>window.__API_URL__ = "..."</script> before this file loads.
const API_BASE_URL = window.__API_URL__ || "http://localhost:3000/api";

/**
 * Thin wrapper around fetch that:
 * - always sends cookies (credentials: "include") so the HTTP-only session cookie is used
 * - never reads/writes any auth token in localStorage/sessionStorage
 * - normalizes the { success, data | message, errors } response envelope
 * - redirects to the login page on 401
 */
async function apiRequest(path, { method = "GET", body, params } = {}) {
  let url = `${API_BASE_URL}${path}`;

  if (params) {
    const query = new URLSearchParams(
      Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== "")
    ).toString();
    if (query) url += `?${query}`;
  }

  let res;
  try {
    res = await fetch(url, {
      method,
      credentials: "include",
      headers: body ? { "Content-Type": "application/json" } : undefined,
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch (networkErr) {
    throw new Error("Network error. Please check your connection.");
  }

  if (res.status === 401) {
    if (!location.pathname.endsWith("login.html")) {
      location.href = "login.html";
    }
    throw new Error("Session expired. Please sign in again.");
  }

  const payload = await res.json().catch(() => null);

  if (!res.ok || !payload?.success) {
    throw new Error(payload?.message || "Something went wrong");
  }

  return payload;
}

const api = {
  get: (path, params) => apiRequest(path, { method: "GET", params }),
  post: (path, body) => apiRequest(path, { method: "POST", body }),
  put: (path, body) => apiRequest(path, { method: "PUT", body }),
  delete: (path) => apiRequest(path, { method: "DELETE" }),
};
