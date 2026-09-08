// public/js/api.js - Fetch Wrapper, CSRF Handling, and API Error Management

export function showAuthModal() {
  const authScreen = document.getElementById('authScreen');
  const appShell = document.querySelector('.app-shell');
  const chatWidget = document.getElementById('clientChatWidget');
  if (authScreen) {
    authScreen.style.display = 'flex';
  }
  if (appShell) {
    appShell.style.display = 'none';
  }
  if (chatWidget) {
    chatWidget.style.display = 'none';
  }
  const modal = document.getElementById('modalBackdrop');
  if (modal) modal.classList.remove('show');
  if (typeof window.stopAudio === 'function') {
    window.stopAudio();
  }
}

export function hideAuthModal() {
  const authScreen = document.getElementById('authScreen');
  const appShell = document.querySelector('.app-shell');
  const chatWidget = document.getElementById('clientChatWidget');
  if (authScreen) {
    authScreen.style.display = 'none';
  }
  if (appShell) {
    appShell.style.display = 'flex';
  }
  if (chatWidget && window.isAuthenticated) {
    chatWidget.style.display = 'block';
  }
}

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) return meta.getAttribute('content');
  // Fallback to cookie
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Central API Client Helper
 */
export async function apiFetch(endpoint, options = {}) {
  const url = endpoint.startsWith('/') ? endpoint : `/api/${endpoint}`;
  const csrf = getCsrfToken();
  
  const headers = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
    ...(options.headers || {}),
  };

  try {
    const res = await fetch(url, { ...options, headers });
    const json = await res.json().catch(() => null);

    const isAuthEndpoint = url.includes('/login') || url.includes('/register') || url.includes('/password/');

    if (res.status === 401 && !isAuthEndpoint) {
      if (typeof window.setIsAuthenticated === 'function') {
        window.setIsAuthenticated(false);
      } else {
        window.isAuthenticated = false;
      }
      showAuthModal();
      throw new Error(json?.message || 'Unauthenticated. Silakan login terlebih dahulu.');
    }

    if (json?.csrf_token) {
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) meta.setAttribute('content', json.csrf_token);
    }

    if (!res.ok) {
      let errMsg = json?.message;
      if (json?.errors) {
        errMsg = Object.values(json.errors).flat().join(', ');
      }
      if (res.status === 429) {
        errMsg = 'Terlalu banyak percobaan. Silakan tunggu 1 menit.';
      }
      throw new Error(errMsg || `HTTP error ${res.status}`);
    }
    return json;
  } catch (err) {
    if (err.message && !err.message.includes('Unauthenticated')) {
      console.error(`API Error [${options.method || 'GET'} ${url}]:`, err);
    }
    throw err;
  }
}

// Expose to window for global availability
window.apiFetch = apiFetch;
window.showAuthModal = showAuthModal;
window.hideAuthModal = hideAuthModal;
