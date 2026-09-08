// public/js/app.js - Main Application Entry Point, State Management, Router, and PWA Setup

import * as utils from './utils.js';
import * as api from './api.js';
import * as audio from './audio.js';
import * as theme from './theme.js';
import * as walletsModule from './wallets.js';
import * as budgetsModule from './budgets.js';
import * as goalsModule from './goals.js';
import * as recurringModule from './recurring.js';
import * as transactionsModule from './transactions.js';
import * as debtsModule from './debts.js';
import * as chatModule from './chat.js';

// ============================================================
// Global State Container
// ============================================================
export const state = {
  user: {
    name: 'Restu Putra Anggara',
    email: 'restu@dev.local',
    theme: localStorage.getItem('arus_theme') || 'dark',
    currency: 'IDR',
    language: 'id'
  },
  lastWallet: localStorage.getItem('arus_last_wallet') || '',
  lastCategory: localStorage.getItem('arus_last_category') || '',
  transactions: [],
  categories: utils.defaults,
  wallets: [],
  budgets: [],
  goals: [],
  recurringTransactions: [],
  debts: [],
  lan: null,
  summary: null,
};

window.state = state;

export let currentPage = 'dashboard';
export let isAuthenticated = false;
export let deferredInstallPrompt = null;

export function setIsAuthenticated(val) {
  isAuthenticated = Boolean(val);
  window.isAuthenticated = isAuthenticated;
}
window.setIsAuthenticated = setIsAuthenticated;

// ============================================================
// LAN Share & QR Code Helper
// ============================================================
export function getEffectiveLanInfo() {
  const lan = state.lan || {};
  let ip = lan.ip;
  let port = lan.port || 8000;
  let scheme = lan.scheme || (typeof location !== 'undefined' && location.protocol === 'https:' ? 'https' : 'http');

  if (!ip && typeof location !== 'undefined') {
    if (location.hostname && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
      ip = location.hostname;
    }
  }

  if (!ip) {
    ip = '192.168.110.242';
  }

  const portSuffix = (port === 80 && scheme === 'http') || (port === 443 && scheme === 'https') ? '' : `:${port}`;
  const shareUrl = lan.share_url || `${scheme}://${ip}${portSuffix}`;

  return {
    available: true,
    ip: ip,
    port: port,
    scheme: scheme,
    share_url: shareUrl
  };
}

export function renderLanQrCode() {
  const qrContainer = document.getElementById('lanQrCode');
  if (!qrContainer) return;
  const lanInfo = getEffectiveLanInfo();
  const shareUrl = lanInfo.share_url;

  qrContainer.innerHTML = '';

  let generated = false;
  if (typeof QRCode !== 'undefined') {
    try {
      new QRCode(qrContainer, {
        text: shareUrl,
        width: 140,
        height: 140,
        colorDark: "#101b2a",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
      });
      generated = true;
    } catch (e) {
      console.warn('QRCode library error, using fallback:', e);
    }
  }

  if (!generated || qrContainer.children.length === 0) {
    const encoded = encodeURIComponent(shareUrl);
    qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=4&data=${encoded}" alt="Barcode QR Code" width="140" height="140" style="display:block;margin:auto;border-radius:6px;" onerror="this.outerHTML='<code style=\\'font-size:11px;color:#101b2a;word-break:break-all;\\'>${shareUrl}</code>'">`;
  }
}

// ============================================================
// Core Page Renderers (Dashboard, Stats, Settings)
// ============================================================
export function dashboard() {
  const tx = utils.monthTx();
  const previousTx = utils.monthTx(utils.previousMonth());
  const income = state.summary ? state.summary.month_income : utils.total(tx, 'income');
  const expense = state.summary ? state.summary.month_expense : utils.total(tx, 'expense');
  const previousIncome = utils.total(previousTx, 'income');
  const previousExpense = utils.total(previousTx, 'expense');
  const balance = state.summary ? state.summary.total_balance : (state.wallets.reduce((s, w) => s + (w.balance || 0), 0));
  const status = utils.financialStatus(income, expense);
  const cats = state.categories.map(c => ({ c, n: utils.total(tx.filter(t => t.category === c), 'expense') })).filter(x => x.n).sort((a, b) => b.n - a.n).slice(0, 5);

  return `
    <div class="cards">
      <div class="card balance-card">
        <span class="card-label">CURRENT BALANCE</span>
        <div class="balance">${utils.money(balance)}</div>
        <small>${previousIncome || previousExpense ? 'Compared with last month' : 'No comparison data yet'}</small>
      </div>
      <div class="card">
        <div class="card-top">
          <span class="card-label">INCOME THIS MONTH</span>
          ${utils.trendMarkup(income, previousIncome)}
        </div>
        <div class="stat-value">${utils.money(income)}</div>
        <span class="stat-sub">Compared to ${utils.money(previousIncome)} last month</span>
      </div>
      <div class="card">
        <div class="card-top">
          <span class="card-label">EXPENSES THIS MONTH</span>
          ${utils.trendMarkup(expense, previousExpense)}
        </div>
        <div class="stat-value">${utils.money(expense)}</div>
        <span class="stat-sub">${income ? Math.round(expense / income * 100) : 0}% of your income</span>
      </div>
    </div>
    <div class="money-status ${status.tone}">
      <span class="status-icon">✦</span>
      <div>
        <strong>${status.title}</strong>
        <p>${status.text}</p>
      </div>
      <span class="status-ratio">${income ? Math.round(expense / income * 100) : 0}% terpakai</span>
    </div>
    <div class="section-grid">
      <div class="card">
        <div class="section-head">
          <h2>Cash flow</h2>
          <div class="legend">
            <span><i></i>Income</span>
            <span><i class="grey"></i>Expense</span>
            <select class="period">
              <option>This month</option>
              <option>Last month</option>
            </select>
          </div>
        </div>
        <div class="chart">${utils.cashFlowChart(tx)}</div>
      </div>
      <div class="card">
        <div class="section-head">
          <h2>Spending by category</h2>
          <button class="icon-btn" data-page-link="statistics">↗</button>
        </div>
        ${cats.length ? cats.map(x => `
          <div class="category-row">
            <span class="cat-icon">${utils.icon(x.c)}</span>
            <span class="cat-name" title="${utils.escapeHtml(x.c)}">${utils.escapeHtml(x.c)}</span>
            <span class="progress"><i style="width:${Math.min(100, x.n / (cats[0].n || 1) * 100)}%"></i></span>
            <span class="cat-amount">${utils.money(x.n)}</span>
          </div>
        `).join('') : '<div class="empty">No spending yet</div>'}
      </div>
    </div>
    <div class="card transactions">
      <div class="section-head">
        <h2>Recent transactions</h2>
        <button class="period" data-page-link="transactions">View all ↗</button>
      </div>
      ${tx.slice().sort((a, b) => (b.date || '').localeCompare(a.date || '')).slice(0, 5).map(transactionsModule.transaction).join('') || transactionsModule.emptyState()}
    </div>
    <div class="insight">
      ✦ <strong> Smart insight:</strong> ${cats[0] ? `${cats[0].c} is your biggest expense this month at ${utils.money(cats[0].n)}.` : 'Add transactions to get personalized insights.'}
    </div>
  `;
}

export function stats() {
  const tx = utils.monthTx();
  const inc = utils.total(tx, 'income');
  const exp = utils.total(tx, 'expense');

  return `
    <div class="page-heading">
      <div>
        <p class="eyebrow">MAKE BETTER DECISIONS</p>
        <h1 class="page-title">Statistics</h1>
        <p class="muted">A clearer picture of where you stand.</p>
      </div>
      <select class="period">
        <option>This month</option>
        <option>This year</option>
        <option>Custom range</option>
      </select>
    </div>
    <div class="metric-grid">
      <div class="card">
        <span class="card-label">INCOME VS EXPENSE</span>
        <div class="stat-value">${utils.money(inc - exp)}</div>
        <span class="stat-sub">Net cash flow this month</span>
        <div class="chart">${utils.cashFlowChart(tx)}</div>
      </div>
      <div class="card">
        <span class="card-label">SPENDING BREAKDOWN</span>
        ${state.categories.map(c => ({ c, n: utils.total(tx.filter(t => t.category === c), 'expense') })).filter(x => x.n).sort((a, b) => b.n - a.n).map(x => `
          <div class="category-row">
            <span class="cat-icon">${utils.icon(x.c)}</span>
            <span class="cat-name" title="${utils.escapeHtml(x.c)}">${utils.escapeHtml(x.c)}</span>
            <span class="cat-amount">${utils.money(x.n)}</span>
          </div>
        `).join('') || '<div class="empty">No data for this period</div>'}
      </div>
    </div>
    <div class="insight">
      ✦ <strong> Financial insight:</strong> ${inc ? `Your expenses are ${Math.round(exp / inc * 100)}% of your income this month.` : 'Add income to unlock a spending ratio.'}
    </div>
  `;
}

export function settings() {
  const lan = getEffectiveLanInfo();
  const shareUrl = lan.share_url;
  const ipText = lan.ip;

  return `
    <div class="page-heading">
      <div>
        <p class="eyebrow">MAKE IT YOURS</p>
        <h1 class="page-title">Settings</h1>
        <p class="muted">Your preferences are saved automatically.</p>
      </div>
    </div>
    <div class="metric-grid">
      <div class="card">
        <h2>${state.user.language === 'id' ? 'Profil Pengguna' : 'Profile'}</h2>
        <label>${state.user.language === 'id' ? 'Nama Lengkap' : 'Name'}
          <input id="settingsName" value="${utils.escapeHtml(state.user.name || '')}">
        </label>
        <label>Email
          <input type="email" id="settingsEmail" value="${utils.escapeHtml(state.user.email || '')}">
        </label>
        <label>${state.user.language === 'id' ? 'Mata Uang' : 'Currency'}
          <select id="settingsCurrency">
            <option ${state.user.currency === 'IDR' ? 'selected' : ''}>IDR</option>
            <option ${state.user.currency === 'USD' ? 'selected' : ''}>USD</option>
          </select>
        </label>
        <button class="primary" id="saveProfile">${state.user.language === 'id' ? 'Simpan Profil' : 'Save profile'}</button>
      </div>

      <div class="card password-card">
        <h2>${state.user.language === 'id' ? 'Keamanan & Kata Sandi' : 'Security & Password'}</h2>
        <p class="muted">${state.user.language === 'id' ? 'Perbarui kata sandi akun untuk menjaga keamanan.' : 'Update your password to keep your account safe.'}</p>
        <label>${state.user.language === 'id' ? 'Kata Sandi Saat Ini' : 'Current Password'}
          <input type="password" id="settingsCurrentPassword" placeholder="••••••••" autocomplete="current-password">
        </label>
        <label>${state.user.language === 'id' ? 'Kata Sandi Baru' : 'New Password'}
          <input type="password" id="settingsNewPassword" placeholder="${state.user.language === 'id' ? 'Minimal 8 karakter' : 'Min. 8 characters'}" minlength="8" autocomplete="new-password">
        </label>
        <label>${state.user.language === 'id' ? 'Konfirmasi Kata Sandi Baru' : 'Confirm New Password'}
          <input type="password" id="settingsConfirmPassword" placeholder="${state.user.language === 'id' ? 'Ulangi kata sandi baru' : 'Repeat new password'}" minlength="8" autocomplete="new-password">
        </label>
        <button class="primary" id="savePasswordBtn" style="margin-top:10px;">${state.user.language === 'id' ? 'Simpan Kata Sandi' : 'Update Password'}</button>
      </div>

      <div class="card share-access-card">
        <div class="section-head">
          <h2>Share Access (LAN)</h2>
          <span class="trend">Available</span>
        </div>
        <p class="muted">${state.user.language === 'id' ? 'Akses Restu Finance dari HP/laptop lain di jaringan Wi-Fi/LAN yang sama.' : 'Access Restu Finance from mobile/laptop on the same Wi-Fi/LAN.'}</p>
        <div class="wallet-meta" style="margin-top:10px;">
          <small>Network Address</small>
          <strong>${ipText}</strong>
        </div>
        <div class="wallet-meta">
          <small>Share URL</small>
          <code style="word-break:break-all;font-size:12px;color:var(--primary, #00d2ff);">${shareUrl}</code>
        </div>
        <div style="margin:14px 0;display:flex;justify-content:center;">
          <div id="lanQrCode" style="background:#ffffff;padding:10px;border-radius:8px;display:inline-block;min-width:140px;min-height:140px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.15);"></div>
        </div>
        <div class="wallet-actions" style="margin-top:10px;">
          <button class="period" id="copyShareUrlBtn">📋 Copy Link</button>
          <button class="period" id="refreshLanBtn">🔄 Refresh IP</button>
        </div>
        <div style="margin-top:12px;font-size:11px;opacity:0.8;line-height:1.4;">
          <p style="margin:2px 0;">✦ <i>${state.user.language === 'id' ? 'Pastikan perangkat berada di Wi-Fi/LAN yang sama dengan server.' : 'Ensure devices are on the same Wi-Fi/LAN as the server.'}</i></p>
          <p style="margin:2px 0;">✦ ${state.user.language === 'id' ? 'Jalankan server dengan' : 'Run server with'}: <code>php artisan serve --host=0.0.0.0 --port=8000</code></p>
        </div>
      </div>

      <div class="card pwa-card">
        <div class="section-head">
          <h2>Aplikasi PWA (Mobile App)</h2>
          <span class="trend">PWA Ready</span>
        </div>
        <p class="muted">${state.user.language === 'id' ? 'Install Restu Finance ke layar utama smartphone atau desktop untuk akses cepat full-screen seperti aplikasi native.' : 'Install Restu Finance to your home screen or desktop for a fast native full-screen experience.'}</p>
        <button class="primary" id="pwaInstallBtn" style="margin-top:14px;width:100%;display:${deferredInstallPrompt ? 'flex' : 'none'};justify-content:center;align-items:center;gap:8px;">
          📲 ${state.user.language === 'id' ? 'Install Aplikasi ke Layar Utama' : 'Install App to Home Screen'}
        </button>
        <div style="margin-top:12px;font-size:11px;opacity:0.8;line-height:1.4;">
          <p style="margin:2px 0;">✦ <i>${state.user.language === 'id' ? 'Service Worker aktif untuk caching static shell, navigasi cepat, dan safe area mobile.' : 'Service Worker active for fast shell caching and mobile safe-area.'}</i></p>
        </div>
      </div>

      <div class="card">
        <h2>Appearance</h2>
        <p class="muted">Pilih mode tema yang kamu sukai.</p>
        <div class="theme-picker-grid" style="display:grid;grid-template-columns:repeat(3, 1fr);gap:8px;margin:12px 0;">
          <button type="button" class="theme-pick-card ${state.user.theme === 'dark' ? 'active' : ''}" data-theme="dark" style="padding:10px 6px;border-radius:8px;border:2px solid ${state.user.theme === 'dark' ? 'var(--green, #00ff41)' : 'var(--line, #304149)'};background:#10191d;color:#fff;cursor:pointer;text-align:center;">
            <div style="font-size:18px;">🌙</div>
            <div style="font-size:11px;font-weight:700;margin-top:4px;">${state.user.language === 'en' ? 'Dark' : 'Gelap'}</div>
            <small style="font-size:9px;opacity:0.7;">${state.user.language === 'en' ? 'Night Mode' : 'Mode Malam'}</small>
          </button>
          <button type="button" class="theme-pick-card ${state.user.theme === 'light' ? 'active' : ''}" data-theme="light" style="padding:10px 6px;border-radius:8px;border:2px solid ${state.user.theme === 'light' ? 'var(--green, #00ff41)' : 'var(--line, #304149)'};background:#f5fbfd;color:#10191d;cursor:pointer;text-align:center;">
            <div style="font-size:18px;">☀️</div>
            <div style="font-size:11px;font-weight:700;margin-top:4px;">${state.user.language === 'en' ? 'Light' : 'Terang'}</div>
            <small style="font-size:9px;opacity:0.7;">${state.user.language === 'en' ? 'Day Mode' : 'Mode Siang'}</small>
          </button>
          <button type="button" class="theme-pick-card ${state.user.theme === 'special' ? 'active' : ''}" data-theme="special" style="padding:10px 6px;border-radius:8px;border:2px solid ${state.user.theme === 'special' ? '#00ff41' : '#7b61ff'};background:#140028;color:#00ff41;cursor:pointer;text-align:center;box-shadow:${state.user.theme === 'special' ? '0 0 10px rgba(0,255,65,0.4)' : 'none'};">
            <div style="font-size:18px;">👾</div>
            <div style="font-size:11px;font-weight:700;margin-top:4px;">Special</div>
            <small style="font-size:9px;opacity:0.7;">Retro Pixel</small>
          </button>
        </div>
        <h2>Categories</h2>
        <button class="period" id="addCategory">+ Add category</button>
      </div>

      <div class="card">
        <h2>General</h2>
        <p class="muted">Manage your everyday preferences.</p>
        <label>Language
          <select id="languageSelect">
            <option value="id" ${state.user.language === 'id' ? 'selected' : ''}>Indonesia</option>
            <option value="en" ${state.user.language === 'en' ? 'selected' : ''}>English</option>
          </select>
        </label>
        <h2>Keamanan Akun</h2>
        <p class="muted">Keluar dari sesi login aplikasi.</p>
        <button class="period" id="logoutBtn" style="color:var(--red);border-color:var(--red);font-weight:600;">Keluar / Logout ↗</button>
      </div>

      <div class="card">
        <h2>${state.user.language === 'id' ? 'Impor & Ekspor Data' : 'Import & Export Data'}</h2>
        <p class="muted">${state.user.language === 'id' ? 'Unduh salinan atau pulihkan riwayat transaksi dalam format CSV.' : 'Download a backup or restore transaction history in CSV format.'}</p>
        <div style="display:flex;gap:8px;margin-top:10px;">
          <button class="period" id="exportCsv" style="flex:1;">⬇ ${state.user.language === 'id' ? 'Ekspor CSV' : 'Export CSV'}</button>
          <button class="period" id="importSettingsCsvBtn" style="flex:1;">⬆ ${state.user.language === 'id' ? 'Impor CSV' : 'Import CSV'}</button>
          <input type="file" id="settingsCsvFileInput" accept=".csv,text/csv" style="display:none;">
        </div>
      </div>

      <div class="card reset-card">
        <h2>Reset preferensi lokal</h2>
        <p class="muted">Hapus preferensi lokal seperti dompet terakhir dan cache tema di browser ini.</p>
        <button class="danger-btn" id="resetAll">Reset preferensi</button>
      </div>

      <div class="card feedback-card">
        <h2>${state.user.language === 'id' ? 'Kritik, Saran & Laporan' : 'Feedback & Bug Report'}</h2>
        <p class="muted">${state.user.language === 'id' ? 'Bantu kami menyempurnakan Restu Finance dengan memberikan masukan Anda.' : 'Help us improve Restu Finance by sharing your suggestions or reporting issues.'}</p>
        <label>${state.user.language === 'id' ? 'Jenis Masukan' : 'Feedback Type'}
          <select id="feedbackType">
            <option value="general">${state.user.language === 'id' ? '💬 Saran / Umpan Balik Umum' : '💬 General Feedback'}</option>
            <option value="feature">${state.user.language === 'id' ? '💡 Usulan Fitur Baru' : '💡 Feature Request'}</option>
            <option value="bug">${state.user.language === 'id' ? '🐛 Laporan Bug / Kendala' : '🐛 Bug Report'}</option>
          </select>
        </label>
        <label>${state.user.language === 'id' ? 'Rating Pengalaman' : 'Experience Rating'}
          <select id="feedbackRating">
            <option value="5">⭐⭐⭐⭐⭐ (Sangat Puas / 5)</option>
            <option value="4">⭐⭐⭐⭐ (Bagus / 4)</option>
            <option value="3">⭐⭐⭐ (Cukup / 3)</option>
            <option value="2">⭐⭐ (Kurang / 2)</option>
            <option value="1">⭐ (Perlu Banyak Perbaikan / 1)</option>
          </select>
        </label>
        <label>${state.user.language === 'id' ? 'Pesan / Masukan Anda' : 'Your Message'}
          <textarea id="feedbackMessage" rows="3" placeholder="${state.user.language === 'id' ? 'Tuliskan kritik, saran, atau kendala yang Anda temui...' : 'Write your suggestions or bug details...'}"></textarea>
        </label>
        <button class="primary" id="submitFeedbackBtn" style="margin-top:8px;">${state.user.language === 'id' ? 'Kirim Masukan ↗' : 'Submit Feedback ↗'}</button>
      </div>

      <div class="card about-card">
        <p class="eyebrow">ABOUT</p>
        <h2>About pengatur uang</h2>
        <p class="muted">Aplikasi sederhana untuk membantu mengatur keuangan pribadi.</p>
        <div class="contact-grid">
          <div><small>Phone</small><strong>0895365375673</strong></div>
          <div><small>Instagram</small><strong>@restuptraaggra</strong></div>
          <div><small>Email</small><strong>retsuwendi2@gmail.com</strong></div>
          <div><small>Version</small><strong>1.4.0</strong></div>
        </div>
      </div>
    </div>
  `;
}

// Map of all page views
export const pages = {
  dashboard,
  transactions: transactionsModule.transactions,
  debts: debtsModule.debts,
  statistics: stats,
  budget: budgetsModule.budget,
  goals: goalsModule.goals,
  wallets: walletsModule.wallets,
  recurring: recurringModule.recurring,
  settings,
  adminChat: chatModule.adminChat
};

const initialPage = new URLSearchParams(location.search).get('page');
if (initialPage && pages[initialPage]) currentPage = initialPage;

export function renderPage() {
  const content = document.getElementById('pageContent');
  if (!content || !pages[currentPage]) return;
  content.replaceChildren();
  content.innerHTML = utils.localize(pages[currentPage]());
  if (currentPage === 'settings') {
    renderLanQrCode();
    if (!state.lan) {
      api.apiFetch('/api/lan-info')
        .then(res => {
          if (res && res.data) {
            state.lan = res.data;
            renderLanQrCode();
          }
        })
        .catch(() => {});
    }
  }
}

export function go(page) {
  if (!pages[page]) return;
  currentPage = page;
  history.pushState({ page }, '', `?page=${encodeURIComponent(page)}`);
  render();
}

// ============================================================
// Notifications Panel Renderer (Card ♧ button)
// ============================================================
export function renderNotifications() {
  const body = document.getElementById('notifBody');
  if (!body) return;
  const isSpecial = state.user.theme === 'special';
  const lastTx = state.transactions[0];
  const tx = utils.monthTx();
  const income = state.summary ? state.summary.month_income : utils.total(tx, 'income');
  const expense = state.summary ? state.summary.month_expense : utils.total(tx, 'expense');
  const status = utils.financialStatus(income, expense);
  const currentSong = audio.BGM_PLAYLIST[audio.bgmTrackIndex]?.title || "Mia & Seb's Theme";
  const en = state.user.language === 'en';

  body.innerHTML = `
    <div class="notif-card special-card">
      <div class="notif-card-title">
        <span>🎮 ${en ? 'Active Theme Mode' : 'Status Mode Tampilan'}</span>
        <span class="notif-card-time">${en ? 'Active' : 'Aktif'}</span>
      </div>
      <div>${en ? 'Current mode' : 'Mode saat ini'}: <b>${isSpecial ? '✦ Special Mode (Retro Pixel)' : (en ? (state.user.theme === 'light' ? 'Light Mode' : 'Dark Mode') : (state.user.theme === 'light' ? 'Mode Terang' : 'Mode Gelap'))}</b>.</div>
      <div style="margin-top:6px;display:flex;gap:6px;">
        <button class="period" onclick="window.setTheme('${isSpecial ? 'dark' : 'special'}')" style="padding:4px 8px;font-size:10px;">
          ${isSpecial ? (en ? '🌙 Switch to Dark' : '🌙 Ganti ke Gelap') : '👾 Special Mode'}
        </button>
      </div>
    </div>
    <div class="notif-card music-card">
      <div class="notif-card-title">
        <span>🎵 Background Music (BGM)</span>
        <span class="notif-card-time">${audio.bgmMuted ? (en ? 'Muted' : 'Bisu') : (audio.bgmActive ? (en ? 'Playing' : 'Memutar') : 'Idle')}</span>
      </div>
      <div>${en ? 'Track' : 'Lagu'}: <i>${currentSong}</i></div>
      <div style="margin-top:6px;display:flex;gap:6px;">
        <button class="period" onclick="window.toggleMute()" style="padding:4px 8px;font-size:10px;">${audio.bgmMuted ? (en ? '🔊 Unmute' : '🔊 Putar Musik') : '🔇 Mute'}</button>
        <button class="period" onclick="window.nextTrack()" style="padding:4px 8px;font-size:10px;">⏭ ${en ? 'Next Track' : 'Lagu Berikutnya'}</button>
      </div>
    </div>
    <div class="notif-card ${status.tone === 'warning' ? 'warning' : ''}">
      <div class="notif-card-title">
        <span>💰 ${utils.escapeHtml(status.title)}</span>
        <span class="notif-card-time">${en ? 'This month' : 'Bulan ini'}</span>
      </div>
      <div>${utils.escapeHtml(status.text)}</div>
      <div style="margin-top:4px;font-size:11px;opacity:0.8;">${en ? 'Income' : 'Pemasukan'}: <b>${utils.money(income)}</b> | ${en ? 'Expense' : 'Pengeluaran'}: <b>${utils.money(expense)}</b></div>
    </div>
    ${lastTx ? `
    <div class="notif-card">
      <div class="notif-card-title">
        <span>📝 ${en ? 'Latest Transaction' : 'Catatan Transaksi Terakhir'}</span>
        <span class="notif-card-time">${utils.dateText(lastTx.date)}</span>
      </div>
      <div><b>${utils.escapeHtml(lastTx.description || lastTx.category)}</b> (${lastTx.type === 'income' ? '+' : '−'} ${utils.money(lastTx.amount)}) di ${utils.escapeHtml(lastTx.wallet || 'Utama')}</div>
    </div>` : ''}
  `;
}

// ============================================================
// Core Render Loop
// ============================================================
export function render() {
  const name = state.user.name || 'Restu Putra Anggara';
  const pn = document.getElementById('profileName'); if (pn) pn.textContent = name;
  const hn = document.getElementById('headingName'); if (hn) hn.textContent = name;
  const initials = name.split(' ').map(x => x[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || 'RP';
  const pa = document.getElementById('profileAvatar'); if (pa) pa.textContent = initials;
  const ta = document.getElementById('topAvatar'); if (ta) ta.textContent = initials;
  const gr = document.getElementById('greeting'); if (gr) gr.textContent = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).toUpperCase();

  document.body.dataset.theme = state.user.theme || 'dark';
  document.body.classList.toggle('special-mode', state.user.theme === 'special');
  document.body.classList.toggle('theme-light', state.user.theme === 'light');
  theme.applySpecialMode();
  theme.updateThemeButtons();

  const tc = document.getElementById('transactionCount');
  if (tc) tc.textContent = state.transactions.length;

  document.querySelectorAll('.nav-item').forEach(n => n.classList.toggle('active', n.dataset.page === currentPage));

  // Tampilkan tab Admin Inbox jika user adalah admin
  const adminNav = document.getElementById('adminChatNavItem');
  if (adminNav) {
    adminNav.style.display = state.user?.is_admin ? 'flex' : 'none';
  }

  renderPage();
  utils.applyStaticLanguage();
  bindPage();
}

// ============================================================
// User Logout Handler with Touch Confirmation
// ============================================================
export async function handleLogout() {
  const isId = state.user?.language === 'id';
  const confirmed = await utils.showConfirmModal({
    title: isId ? 'Keluar dari Aplikasi' : 'Logout Confirmation',
    message: isId ? 'Apakah Anda yakin ingin keluar dari akun Anda?' : 'Are you sure you want to log out of your account?',
    confirmText: isId ? 'Ya, Keluar' : 'Logout',
    cancelText: isId ? 'Batal' : 'Cancel',
    isDanger: true,
  });
  if (!confirmed) return;

  // Hentikan BGM dan musik seketika saat user logout
  if (typeof audio.stopAudio === 'function') {
    audio.stopAudio();
  }

  const chatWidget = document.getElementById('clientChatWidget');
  if (chatWidget) chatWidget.style.display = 'none';

  try {
    const res = await api.apiFetch('/api/logout', { method: 'POST' });
    if (res?.csrf_token) {
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) meta.setAttribute('content', res.csrf_token);
    }
    utils.toast(isId ? 'Logout berhasil' : 'Logged out successfully', 'info');
  } catch (e) {
    console.warn('Logout notice:', e);
  }
  setIsAuthenticated(false);
  api.showAuthModal();
}
window.handleLogout = handleLogout;

// ============================================================
// Event Binding for Page Components
// ============================================================
export function bindPage() {
  // Bind modular handlers
  walletsModule.initWalletHandlers();
  budgetsModule.initBudgetHandlers();
  goalsModule.initGoalHandlers();
  recurringModule.initRecurringHandlers();
  transactionsModule.initTransactionHandlers();
  debtsModule.initDebtHandlers();
  if (currentPage === 'adminChat') {
    chatModule.initAdminChat();
  }

  // Page Link Attributes
  document.querySelectorAll('[data-page-link]').forEach(b => b.onclick = () => go(b.dataset.pageLink));

  // Reset Preferensi Lokal
  const resetAll = document.getElementById('resetAll');
  if (resetAll) {
    resetAll.onclick = async () => {
      const isId = state.user.language === 'id';
      const confirmed = await utils.showConfirmModal({
        title: isId ? 'Reset Preferensi Lokal' : 'Reset Preferences',
        message: isId ? 'Hapus semua preferensi lokal seperti dompet terakhir dan cache tema di browser ini?' : 'Clear all local preferences and theme cache on this device?',
        confirmText: isId ? 'Ya, Reset' : 'Reset All',
        cancelText: isId ? 'Batal' : 'Cancel',
        isDanger: true,
      });
      if (!confirmed) return;
      ['arus_transactions', 'arus_categories', 'arus_wallets', 'arus_budgets', 'arus_goals', 'arus_user', 'arus_last_wallet', 'arus_last_category', 'arus_clean_start', 'arus_wallet_reset_v2', 'arus_budget_reset_v1', 'arus_goals_reset_v3'].forEach(key => localStorage.removeItem(key));
      location.reload();
    };
  }

  // Tombol Logout
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.onclick = handleLogout;
  }

  // Tambah Kategori via API
  const addCategory = document.getElementById('addCategory');
  if (addCategory) {
    addCategory.onclick = async () => {
      const isId = state.user.language === 'id';
      const name = prompt(isId ? 'Nama kategori:' : 'Category name:');
      if (!name || !name.trim()) return;
      try {
        await api.apiFetch('/api/categories', {
          method: 'POST',
          body: JSON.stringify({ name: name.trim(), type: 'expense' }),
        });
        utils.toast(isId ? 'Kategori berhasil ditambahkan' : 'Category added');
        await loadApp();
      } catch (err) {
        utils.toast(err.message || (isId ? 'Gagal menambah kategori' : 'Failed to add category'));
      }
    };
  }

  // Simpan Profil via API
  const saveProfile = document.getElementById('saveProfile');
  if (saveProfile) {
    saveProfile.onclick = async () => {
      const isId = state.user.language === 'id';
      const name = document.getElementById('settingsName')?.value.trim() || 'Restu Putra Anggara';
      const email = document.getElementById('settingsEmail')?.value.trim() || state.user.email;
      const currency = document.getElementById('settingsCurrency')?.value || 'IDR';

      if (!name || !email) {
        return utils.toast(isId ? 'Nama dan email tidak boleh kosong' : 'Name and email cannot be empty');
      }

      try {
        saveProfile.disabled = true;
        saveProfile.textContent = isId ? 'Menyimpan...' : 'Saving...';
        await api.apiFetch('/api/profile', {
          method: 'PUT',
          body: JSON.stringify({ name, email, currency }),
        });
        state.user.name = name;
        state.user.email = email;
        state.user.currency = currency;
        utils.saveLocalPreferences();
        render();
        utils.toast(isId ? 'Profil berhasil diperbarui' : 'Profile updated');
      } catch (err) {
        utils.toast(err.message || (isId ? 'Gagal memperbarui profil' : 'Failed to update profile'));
      } finally {
        saveProfile.disabled = false;
        saveProfile.textContent = isId ? 'Simpan Profil' : 'Save profile';
      }
    };
  }

  // Ganti Kata Sandi via API
  const savePasswordBtn = document.getElementById('savePasswordBtn');
  if (savePasswordBtn) {
    savePasswordBtn.onclick = async () => {
      const isId = state.user.language === 'id';
      const currentPassword = document.getElementById('settingsCurrentPassword')?.value;
      const newPassword = document.getElementById('settingsNewPassword')?.value;
      const confirmPassword = document.getElementById('settingsConfirmPassword')?.value;

      if (!currentPassword || !newPassword || !confirmPassword) {
        return utils.toast(isId ? 'Harap lengkapi seluruh kolom kata sandi' : 'Please fill all password fields');
      }
      if (newPassword.length < 8) {
        return utils.toast(isId ? 'Kata sandi baru minimal 8 karakter' : 'New password must be at least 8 characters');
      }
      if (newPassword !== confirmPassword) {
        return utils.toast(isId ? 'Konfirmasi kata sandi baru tidak cocok' : 'Password confirmation does not match');
      }

      try {
        savePasswordBtn.disabled = true;
        savePasswordBtn.textContent = isId ? 'Memproses...' : 'Processing...';
        await api.apiFetch('/api/profile/password', {
          method: 'PUT',
          body: JSON.stringify({
            current_password: currentPassword,
            password: newPassword,
            password_confirmation: confirmPassword,
          }),
        });
        const curInput = document.getElementById('settingsCurrentPassword');
        const newInput = document.getElementById('settingsNewPassword');
        const confInput = document.getElementById('settingsConfirmPassword');
        if (curInput) curInput.value = '';
        if (newInput) newInput.value = '';
        if (confInput) confInput.value = '';
        utils.toast(isId ? 'Kata sandi berhasil diperbarui' : 'Password updated successfully');
      } catch (err) {
        utils.toast(err.message || (isId ? 'Gagal mengubah kata sandi' : 'Failed to change password'));
      } finally {
        savePasswordBtn.disabled = false;
        savePasswordBtn.textContent = isId ? 'Simpan Kata Sandi' : 'Update Password';
      }
    };
  }

  // Ganti Bahasa via API
  const languageSelect = document.getElementById('languageSelect');
  if (languageSelect) {
    languageSelect.value = state.user.language || 'id';
    languageSelect.onchange = async () => {
      const lang = languageSelect.value;
      try {
        await api.apiFetch('/api/profile', { method: 'PUT', body: JSON.stringify({ language: lang }) });
      } catch (e) {}
      state.user.language = lang;
      utils.saveLocalPreferences();
      render();
    };
  }

  // Ganti Tema via Cards
  document.querySelectorAll('.theme-pick-card').forEach(btn => {
    btn.onclick = () => {
      theme.setTheme(btn.dataset.theme);
    };
  });

  // Copy Share URL LAN
  const copyShareUrlBtn = document.getElementById('copyShareUrlBtn');
  if (copyShareUrlBtn) {
    copyShareUrlBtn.onclick = async () => {
      const shareUrl = state.lan?.share_url || getEffectiveLanInfo().share_url;
      if (!shareUrl) {
        utils.toast(state.user.language === 'en' ? 'LAN Share URL not available' : 'Share URL tidak tersedia');
        return;
      }
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(shareUrl);
        } else {
          const textArea = document.createElement("textarea");
          textArea.value = shareUrl;
          textArea.style.position = "fixed";
          textArea.style.opacity = "0";
          document.body.appendChild(textArea);
          textArea.focus();
          textArea.select();
          document.execCommand('copy');
          document.body.removeChild(textArea);
        }
        utils.toast(state.user.language === 'en' ? 'Share link copied to clipboard!' : 'Link share berhasil disalin ke clipboard!');
      } catch (err) {
        utils.toast(state.user.language === 'en' ? 'Failed to copy link' : 'Gagal menyalin link');
      }
    };
  }

  // Refresh LAN Info
  const refreshLanBtn = document.getElementById('refreshLanBtn');
  if (refreshLanBtn) {
    refreshLanBtn.onclick = async () => {
      try {
        const res = await api.apiFetch('/api/lan-info');
        if (res && res.data) {
          state.lan = res.data;
          render();
          utils.toast(state.user.language === 'en' ? 'LAN info refreshed' : 'Info LAN diperbarui');
        }
      } catch (err) {
        utils.toast(err.message || 'Gagal memuat status LAN');
      }
    };
  }

  // PWA Install Button
  const pwaInstallBtn = document.getElementById('pwaInstallBtn');
  if (pwaInstallBtn) {
    if (deferredInstallPrompt) {
      pwaInstallBtn.style.display = 'flex';
    }
    pwaInstallBtn.onclick = async () => {
      if (!deferredInstallPrompt) {
        utils.toast(state.user.language === 'id' ? 'Aplikasi sudah terinstall atau browser belum mendukung install prompt otomatis.' : 'App already installed or browser does not support install prompt.');
        return;
      }
      try {
        deferredInstallPrompt.prompt();
        const { outcome } = await deferredInstallPrompt.userChoice;
        if (outcome === 'accepted') {
          utils.toast(state.user.language === 'id' ? 'Menginstall aplikasi...' : 'Installing app...');
        }
        deferredInstallPrompt = null;
        pwaInstallBtn.style.display = 'none';
      } catch (err) {
        console.warn('Install prompt error:', err);
      }
    };
  }

  // Submit Feedback / Saran via API
  const submitFeedbackBtn = document.getElementById('submitFeedbackBtn');
  if (submitFeedbackBtn) {
    submitFeedbackBtn.onclick = async () => {
      const isId = state.user.language === 'id';
      const type = document.getElementById('feedbackType')?.value || 'general';
      const rating = parseInt(document.getElementById('feedbackRating')?.value || '5', 10);
      const message = document.getElementById('feedbackMessage')?.value?.trim();

      if (!message) {
        return utils.toast(isId ? 'Harap masukkan pesan atau saran Anda' : 'Please enter your message or feedback');
      }

      try {
        submitFeedbackBtn.disabled = true;
        submitFeedbackBtn.textContent = isId ? 'Mengirim...' : 'Submitting...';
        await api.apiFetch('/api/feedback', {
          method: 'POST',
          body: JSON.stringify({ type, rating, message }),
        });
        const msgInput = document.getElementById('feedbackMessage');
        if (msgInput) msgInput.value = '';
        utils.toast(isId ? 'Terima kasih atas masukan dan saran Anda!' : 'Thank you for your feedback!');
      } catch (err) {
        utils.toast(err.message || (isId ? 'Gagal mengirim saran' : 'Failed to submit feedback'));
      } finally {
        submitFeedbackBtn.disabled = false;
        submitFeedbackBtn.textContent = isId ? 'Kirim Masukan ↗' : 'Submit Feedback ↗';
      }
    };
  }

  // Settings CSV Import Trigger
  const importSettingsCsvBtn = document.getElementById('importSettingsCsvBtn');
  const settingsCsvFileInput = document.getElementById('settingsCsvFileInput');
  if (importSettingsCsvBtn && settingsCsvFileInput) {
    importSettingsCsvBtn.onclick = () => {
      settingsCsvFileInput.value = '';
      settingsCsvFileInput.click();
    };
    settingsCsvFileInput.onchange = (e) => {
      const file = e.target.files?.[0];
      if (file) transactionsModule.uploadTransactionsCsv(file);
    };
  }
}

// ============================================================
// Inisialisasi dan Sinkronisasi State Aplikasi (Laravel API)
export async function loadApp() {
  const pageContent = document.getElementById('pageContent');
  if (pageContent && (!state.transactions || state.transactions.length === 0)) {
    pageContent.innerHTML = `
      <div class="skeleton-wrap">
        <div class="skeleton-row">
          <div class="skeleton-card skeleton-banner"></div>
          <div class="skeleton-card skeleton-stat"></div>
          <div class="skeleton-card skeleton-stat"></div>
        </div>
        <div class="skeleton-card skeleton-large"></div>
      </div>
    `;
  }
  try {
    const res = await api.apiFetch('/api/init');
    if (res && res.success && res.data) {
      const d = res.data; // Fixed: explicitly declare d = res.data
      setIsAuthenticated(true);
      api.hideAuthModal();
      if (typeof audio.initAudioAfterLogin === 'function') {
        audio.initAudioAfterLogin();
      }

      if (d.user) {
        const localTheme = localStorage.getItem('arus_theme');
        const themeToKeep = (state.user.theme === 'special' || localTheme === 'special') ? 'special' : (d.user.theme || localTheme || 'special');
        state.user = { ...state.user, ...d.user, theme: themeToKeep };
        localStorage.setItem('arus_theme', themeToKeep);
      }

      state.wallets = (d.wallets || []).map(w => ({
        id: w.id,
        name: w.name,
        opening: Number(w.opening_balance ?? w.opening ?? 0),
        balance: Number(w.current_balance ?? w.balance ?? 0),
        is_active: w.is_active,
      }));

      state.categories = (d.categories || []).map(c => typeof c === 'string' ? c : c.name);
      if (!state.categories.length) state.categories = utils.defaults;

      state.transactions = (d.transactions || []).map(t => ({
        id: t.id,
        type: t.type,
        amount: Number(t.amount),
        category: t.category,
        wallet: t.wallet,
        description: t.description || t.note || t.category,
        method: t.method || 'E-wallet',
        date: t.date,
        time: t.time || '12:00',
        note: t.note || '',
        goal: t.goal || '',
      }));

      state.budgets = (d.budgets || []).map(b => ({
        id: b.id,
        category: b.category,
        limit: Number(b.limit_amount ?? b.limit ?? 0),
        used: Number(b.used ?? 0),
        remaining: Number(b.remaining ?? 0),
        percentage: Number(b.percentage ?? 0),
      }));

      state.goals = (d.goals || []).map(g => ({
        id: g.id,
        name: g.name,
        target: Number(g.target_amount ?? g.target ?? 0),
        saved: Number(g.saved ?? g.saved_amount ?? 0),
        remaining: Number(g.remaining ?? 0),
        percentage: Number(g.percentage ?? 0),
        deadline: g.deadline,
      }));

      state.recurringTransactions = (d.recurring_transactions || []).map(r => ({
        id: r.id,
        wallet: r.wallet,
        category: r.category,
        type: r.type,
        amount: Number(r.amount),
        description: r.description || '',
        frequency: r.frequency,
        next_date: r.next_date,
        start_date: r.start_date,
        end_date: r.end_date,
        is_active: Boolean(r.is_active),
      }));

      state.debts = (d.debts || []).map(debt => ({
        id: debt.id,
        wallet_id: debt.wallet_id,
        wallet: debt.wallet?.name || (state.wallets.find(w => w.id === debt.wallet_id)?.name || 'Utama'),
        type: debt.type,
        person_name: debt.person_name,
        amount: Number(debt.amount || 0),
        paid_amount: Number(debt.paid_amount || 0),
        remaining_amount: Number(debt.remaining_amount ?? Math.max(0, (debt.amount || 0) - (debt.paid_amount || 0))),
        percentage: Number(debt.percentage ?? 0),
        due_date: debt.due_date,
        status: debt.status || 'unpaid',
        notes: debt.notes || '',
        is_overdue: Boolean(debt.is_overdue),
        payments: debt.payments || [],
      }));

      state.lan = d.lan || null;
      state.summary = d.summary || null;

      render();
    }
  } catch (err) {
    console.warn('loadApp notice:', err.message);
    if (!err.message.includes('Unauthenticated')) {
      utils.toast(state.user.language === 'en' ? 'Failed to connect to backend server' : 'Gagal menghubungkan ke server');
    }
  }
}

// ============================================================
// Topbar & Global App Event Listeners
// ============================================================
export function initGlobalListeners() {
  // Nav items click
  document.querySelectorAll('.nav-item').forEach(n => {
    n.onclick = () => go(n.dataset.page);
  });

  // Modal close triggers
  const modalCloseBtn = document.getElementById('modalClose');
  if (modalCloseBtn) modalCloseBtn.onclick = transactionsModule.closeModal;

  const modalBackdrop = document.getElementById('modalBackdrop');
  if (modalBackdrop) {
    modalBackdrop.onclick = e => {
      if (e.target.id === 'modalBackdrop') transactionsModule.closeModal();
    };
  }

  // Split allocation add row button
  const addSplitBtn = document.getElementById('addSplit');
  if (addSplitBtn) {
    addSplitBtn.onclick = () => {
      const row = document.createElement('div');
      row.className = 'split-row';
      row.innerHTML = '<select class="split-category">' + transactionsModule.categoryOptions() + '</select><input class="split-amount" inputmode="numeric" placeholder="Nominal"><button type="button" class="icon-btn remove-split" aria-label="Remove allocation">×</button>';
      document.getElementById('splitRows')?.appendChild(row);
      row.querySelector('.remove-split').onclick = () => row.remove();
    };
  }

  // Standard theme button in Topbar
  const standardThemeBtn = document.getElementById('standardThemeBtn');
  if (standardThemeBtn) {
    standardThemeBtn.onclick = () => {
      const en = state.user.language === 'en';
      let nextTheme = 'dark';
      if (state.user.theme === 'dark') nextTheme = 'light';
      else if (state.user.theme === 'light') nextTheme = 'dark';
      else nextTheme = 'dark';
      theme.setTheme(nextTheme);
      const label = nextTheme === 'light' ? (en ? 'Light' : 'Terang') : (en ? 'Dark' : 'Gelap');
      utils.toast(en ? `Switched to ${label} mode` : `Beralih ke mode ${label}`);
    };
  }

  // Special theme button in Topbar
  const specialThemeBtn = document.getElementById('specialThemeBtn');
  if (specialThemeBtn) {
    specialThemeBtn.onclick = () => {
      theme.setTheme('special');
      utils.toast('✦ Special Mode (Retro Pixel) aktif!');
    };
  }

  // Notifications dropdown panel
  const notifBtn = document.getElementById('notifBtn');
  const notifDropdown = document.getElementById('notifDropdown');
  const closeNotifBtn = document.getElementById('closeNotifBtn');
  if (notifBtn && notifDropdown) {
    notifBtn.onclick = (e) => {
      e.stopPropagation();
      const isHidden = notifDropdown.style.display === 'none';
      if (isHidden) {
        renderNotifications();
        notifDropdown.style.display = 'block';
        const badge = document.getElementById('notifBadge');
        if (badge) badge.style.display = 'none';
      } else {
        notifDropdown.style.display = 'none';
      }
    };
  }

  if (closeNotifBtn && notifDropdown) {
    closeNotifBtn.onclick = () => {
      notifDropdown.style.display = 'none';
    };
  }

  document.addEventListener('click', (e) => {
    if (notifDropdown && notifDropdown.style.display !== 'none' && !notifDropdown.contains(e.target) && e.target !== notifBtn) {
      notifDropdown.style.display = 'none';
    }
  });

  // Top Avatar -> Go to Settings
  const topAvatar = document.getElementById('topAvatar');
  if (topAvatar) {
    topAvatar.onclick = () => go('settings');
  }

  // Profile More Menu (Three-Dots ••• Dropdown)
  const profileMoreBtn = document.getElementById('profileMoreBtn');
  const profileDropdown = document.getElementById('profileDropdown');
  const profileMenuSettings = document.getElementById('profileMenuSettings');
  const profileMenuCopyId = document.getElementById('profileMenuCopyId');
  const profileMenuLogout = document.getElementById('profileMenuLogout');

  if (profileMoreBtn && profileDropdown) {
    profileMoreBtn.onclick = (e) => {
      e.stopPropagation();
      const isHidden = profileDropdown.style.display === 'none';
      profileDropdown.style.display = isHidden ? 'block' : 'none';
    };

    if (profileMenuSettings) {
      profileMenuSettings.onclick = () => {
        profileDropdown.style.display = 'none';
        go('settings');
      };
    }

    if (profileMenuCopyId) {
      profileMenuCopyId.onclick = async () => {
        profileDropdown.style.display = 'none';
        const sessionInfo = `User: ${state.user.name || 'User'} | Email: ${state.user.email || 'N/A'} | ID: ${state.user.id || 'N/A'}`;
        try {
          if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(sessionInfo);
          } else {
            const textArea = document.createElement('textarea');
            textArea.value = sessionInfo;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
          }
          utils.toast(state.user.language === 'en' ? 'Account session info copied to clipboard!' : 'Info sesi akun berhasil disalin ke clipboard!');
        } catch (err) {
          utils.toast(state.user.language === 'en' ? 'Failed to copy session info' : 'Gagal menyalin info sesi');
        }
      };
    }

    if (profileMenuLogout) {
      profileMenuLogout.onclick = () => {
        profileDropdown.style.display = 'none';
        handleLogout();
      };
    }

    document.addEventListener('click', (e) => {
      if (profileDropdown && profileDropdown.style.display !== 'none' && !profileDropdown.contains(e.target) && e.target !== profileMoreBtn) {
        profileDropdown.style.display = 'none';
      }
    });
  }

  // Header and Sidebar Logout Buttons
  const sidebarLogoutBtn = document.getElementById('sidebarLogoutBtn');
  if (sidebarLogoutBtn) sidebarLogoutBtn.onclick = handleLogout;
  const topbarLogoutBtn = document.getElementById('topbarLogoutBtn');
  if (topbarLogoutBtn) topbarLogoutBtn.onclick = handleLogout;

  // Dedicated Auth Tabs (Login vs Register) Switcher
  const authTabLogin = document.getElementById('authTabLogin');
  const authTabRegister = document.getElementById('authTabRegister');
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const switchToRegisterBtn = document.getElementById('switchToRegisterBtn');
  const switchToLoginBtn = document.getElementById('switchToLoginBtn');
  const authTitle = document.getElementById('authTitle');
  const authSubtitle = document.getElementById('authSubtitle');

  function setAuthError(elementId, message) {
    const banner = document.getElementById(elementId);
    if (!banner) return;
    if (!message) {
      banner.style.display = 'none';
      banner.innerHTML = '';
    } else {
      banner.style.display = 'flex';
      banner.innerHTML = `<span class="banner-icon">⚠️</span><span>${utils.escapeHtml(message)}</span>`;
    }
  }

  function setAuthMode(mode) {
    setAuthError('loginErrorBanner', null);
    setAuthError('registerErrorBanner', null);
    if (mode === 'register') {
      authTabRegister?.classList.add('active');
      authTabLogin?.classList.remove('active');
      if (loginForm) loginForm.style.display = 'none';
      if (registerForm) registerForm.style.display = 'block';
      if (authTitle) authTitle.textContent = 'Daftar Akun Baru';
      if (authSubtitle) authSubtitle.textContent = 'Mulai kelola keuangan Anda secara rapi dan mandiri.';
    } else {
      authTabLogin?.classList.add('active');
      authTabRegister?.classList.remove('active');
      if (loginForm) loginForm.style.display = 'block';
      if (registerForm) registerForm.style.display = 'none';
      if (authTitle) authTitle.textContent = 'Selamat Datang';
      if (authSubtitle) authSubtitle.textContent = 'Kelola keuangan pribadi dengan rapi, aman, dan terencana.';
    }
  }

  if (authTabLogin) authTabLogin.onclick = () => setAuthMode('login');
  if (authTabRegister) authTabRegister.onclick = () => setAuthMode('register');
  if (switchToRegisterBtn) switchToRegisterBtn.onclick = () => setAuthMode('register');
  if (switchToLoginBtn) switchToLoginBtn.onclick = () => setAuthMode('login');

  // Helper Toggle Show / Hide Password (Mata)
  function initPasswordToggles() {
    document.querySelectorAll('.password-toggle-btn').forEach(btn => {
      btn.onclick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        const targetId = btn.dataset.target;
        const input = document.getElementById(targetId);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        const icon = btn.querySelector('.eye-icon') || btn;
        icon.textContent = isPassword ? '🙈' : '👁';
        btn.title = isPassword ? 'Sembunyikan kata sandi' : 'Lihat kata sandi';
      };
    });
  }
  initPasswordToggles();

  // Form Login Listener
  if (loginForm) {
    loginForm.onsubmit = async (e) => {
      e.preventDefault();
      setAuthError('loginErrorBanner', null);
      const email = document.getElementById('loginEmail')?.value.trim();
      const password = document.getElementById('loginPassword')?.value;
      const submitBtn = document.getElementById('loginSubmitBtn');

      if (!email || !password) {
        setAuthError('loginErrorBanner', 'Masukkan alamat email dan kata sandi Anda.');
        return utils.toast('Masukkan email dan password', 'error');
      }

      try {
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Memverifikasi...';
        }

        const res = await api.apiFetch('/api/login', {
          method: 'POST',
          body: JSON.stringify({ email, password }),
        });

        if (res && res.success) {
          setIsAuthenticated(true);
          api.hideAuthModal();
          audio.initAudioAfterLogin();
          chatModule.initClientChat();
          utils.toast('Login berhasil! Selamat datang kembali.', 'success');
          await loadApp();
        }
      } catch (err) {
        setAuthError('loginErrorBanner', err.message || 'Login gagal. Periksa kembali email dan password.');
        utils.toast(err.message || 'Login gagal. Periksa kembali email dan password.', 'error');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Masuk ke Aplikasi <span>↗</span>';
        }
      }
    };
  }

  // Form Register Listener
  if (registerForm) {
    registerForm.onsubmit = async (e) => {
      e.preventDefault();
      setAuthError('registerErrorBanner', null);
      const name = document.getElementById('registerName')?.value.trim();
      const email = document.getElementById('registerEmail')?.value.trim();
      const registration_key = document.getElementById('registerRegistrationKey')?.value.trim();
      const password = document.getElementById('registerPassword')?.value;
      const password_confirmation = document.getElementById('registerPasswordConfirmation')?.value;
      const submitBtn = document.getElementById('registerSubmitBtn');

      if (!name || !email || !registration_key || !password || !password_confirmation) {
        setAuthError('registerErrorBanner', 'Lengkapi seluruh formulir pendaftaran termasuk kode akses.');
        return utils.toast('Lengkapi seluruh formulir pendaftaran', 'error');
      }
      if (password.length < 8) {
        setAuthError('registerErrorBanner', 'Kata sandi minimal 8 karakter.');
        return utils.toast('Kata sandi minimal 8 karakter', 'error');
      }
      if (password !== password_confirmation) {
        setAuthError('registerErrorBanner', 'Konfirmasi kata sandi tidak cocok.');
        return utils.toast('Konfirmasi kata sandi tidak cocok', 'error');
      }

      try {
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Mendaftarkan...';
        }

        const res = await api.apiFetch('/api/register', {
          method: 'POST',
          body: JSON.stringify({ name, email, password, password_confirmation, registration_key }),
        });

        if (res && res.success) {
          setIsAuthenticated(true);
          api.hideAuthModal();
          audio.initAudioAfterLogin();
          chatModule.initClientChat();
          utils.toast('Pendaftaran berhasil! Selamat datang, ' + name, 'success');
          await loadApp();
        }
      } catch (err) {
        setAuthError('registerErrorBanner', err.message || 'Pendaftaran gagal.');
        utils.toast(err.message || 'Pendaftaran gagal', 'error');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Buat Akun Baru <span>↗</span>';
        }
      }
    };
  }

  // Alur Lupa Kata Sandi via Kode OTP 2 Langkah
  function initForgotPasswordFlow() {
    const triggerBtn = document.getElementById('forgotPasswordTriggerBtn');
    const modal = document.getElementById('forgotModalBackdrop');
    const closeBtn = document.getElementById('closeForgotModalBtn');
    const step1Form = document.getElementById('forgotStep1Form');
    const step2Form = document.getElementById('forgotStep2Form');
    const stepBadge = document.getElementById('forgotStepBadge');
    const modalTitle = document.getElementById('forgotModalTitle');
    const step1SubmitBtn = document.getElementById('forgotStep1SubmitBtn');
    const step2SubmitBtn = document.getElementById('forgotStep2SubmitBtn');
    const noticeEmail = document.getElementById('forgotNoticeEmail');
    const resendBtn = document.getElementById('resendOtpBtn');

    let currentForgotEmail = '';

    function openForgotModal() {
      if (!modal) return;
      currentForgotEmail = document.getElementById('loginEmail')?.value.trim() || '';
      const forgotEmailInput = document.getElementById('forgotEmail');
      if (forgotEmailInput && currentForgotEmail) forgotEmailInput.value = currentForgotEmail;

      // Reset ke Langkah 1
      if (step1Form) step1Form.style.display = 'block';
      if (step2Form) step2Form.style.display = 'none';
      if (stepBadge) stepBadge.textContent = 'LANGKAH 1 DARI 2';
      if (modalTitle) modalTitle.textContent = 'Lupa Kata Sandi';
      setAuthError('forgotStep1Error', null);
      setAuthError('forgotStep2Error', null);
      modal.style.display = 'flex';
    }

    function closeForgotModal() {
      if (modal) modal.style.display = 'none';
    }

    if (triggerBtn) triggerBtn.onclick = openForgotModal;
    if (closeBtn) closeBtn.onclick = closeForgotModal;
    if (modal) {
      modal.onclick = (e) => {
        if (e.target.id === 'forgotModalBackdrop') closeForgotModal();
      };
    }

    // Step 1: Submit email untuk kirim OTP
    if (step1Form) {
      step1Form.onsubmit = async (e) => {
        e.preventDefault();
        const email = document.getElementById('forgotEmail')?.value.trim();
        if (!email) {
          setAuthError('forgotStep1Error', 'Masukkan alamat email Anda.');
          return utils.toast('Masukkan alamat email', 'error');
        }

        setAuthError('forgotStep1Error', null);
        try {
          if (step1SubmitBtn) {
            step1SubmitBtn.disabled = true;
            step1SubmitBtn.textContent = 'Mengirim OTP...';
          }

          const res = await api.apiFetch('/api/password/forgot', {
            method: 'POST',
            body: JSON.stringify({ email }),
          });

          if (res && res.success) {
            currentForgotEmail = email;
            if (noticeEmail) noticeEmail.textContent = email;
            if (step1Form) step1Form.style.display = 'none';
            if (step2Form) step2Form.style.display = 'block';
            if (stepBadge) stepBadge.textContent = 'LANGKAH 2 DARI 2';
            if (modalTitle) modalTitle.textContent = 'Verifikasi OTP & Sandi Baru';
            utils.toast('Kode OTP berhasil dikirim ke email Anda!', 'success');
            const otpInput = document.getElementById('forgotOtp');
            if (otpInput) setTimeout(() => otpInput.focus(), 150);
          }
        } catch (err) {
          setAuthError('forgotStep1Error', err.message || 'Gagal mengirim OTP.');
          utils.toast(err.message || 'Gagal mengirim OTP', 'error');
        } finally {
          if (step1SubmitBtn) {
            step1SubmitBtn.disabled = false;
            step1SubmitBtn.innerHTML = 'Kirim Kode OTP <span>✉</span>';
          }
        }
      };
    }

    // Step 2: Submit OTP dan Password Baru
    if (step2Form) {
      step2Form.onsubmit = async (e) => {
        e.preventDefault();
        const otp = document.getElementById('forgotOtp')?.value.trim();
        const password = document.getElementById('forgotNewPassword')?.value;
        const password_confirmation = document.getElementById('forgotNewPasswordConfirmation')?.value;

        if (!otp || otp.length !== 6) {
          setAuthError('forgotStep2Error', 'Masukkan 6-digit kode OTP numerik.');
          return utils.toast('Kode OTP harus 6 digit', 'error');
        }
        if (!password || password.length < 8) {
          setAuthError('forgotStep2Error', 'Kata sandi baru minimal 8 karakter.');
          return utils.toast('Kata sandi minimal 8 karakter', 'error');
        }
        if (password !== password_confirmation) {
          setAuthError('forgotStep2Error', 'Konfirmasi kata sandi tidak cocok.');
          return utils.toast('Konfirmasi kata sandi tidak cocok', 'error');
        }

        setAuthError('forgotStep2Error', null);
        try {
          if (step2SubmitBtn) {
            step2SubmitBtn.disabled = true;
            step2SubmitBtn.textContent = 'Memperbarui Sandi...';
          }

          const res = await api.apiFetch('/api/password/reset-otp', {
            method: 'POST',
            body: JSON.stringify({
              email: currentForgotEmail,
              otp,
              password,
              password_confirmation
            }),
          });

          if (res && res.success) {
            closeForgotModal();
            utils.toast('Kata sandi berhasil diperbarui! Silakan login.', 'success');
            const loginEmail = document.getElementById('loginEmail');
            const loginPassword = document.getElementById('loginPassword');
            if (loginEmail) loginEmail.value = currentForgotEmail;
            if (loginPassword) loginPassword.value = '';
            setAuthMode('login');
            setTimeout(() => loginPassword?.focus(), 150);
          }
        } catch (err) {
          setAuthError('forgotStep2Error', err.message || 'Gagal mereset kata sandi.');
          utils.toast(err.message || 'Gagal mereset kata sandi', 'error');
        } finally {
          if (step2SubmitBtn) {
            step2SubmitBtn.disabled = false;
            step2SubmitBtn.innerHTML = 'Perbarui Kata Sandi <span>✓</span>';
          }
        }
      };
    }

    if (resendBtn) {
      resendBtn.onclick = () => {
        if (step2Form) step2Form.style.display = 'none';
        if (step1Form) step1Form.style.display = 'block';
        if (stepBadge) stepBadge.textContent = 'LANGKAH 1 DARI 2';
        if (modalTitle) modalTitle.textContent = 'Kirim Ulang Kode OTP';
        setAuthError('forgotStep1Error', null);
      };
    }
  }
  initForgotPasswordFlow();

  // PWA Service Worker Registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('./sw.js')
        .then((reg) => {
          if (typeof reg.update === 'function') {
            reg.update();
          }
        })
        .catch(err => console.warn('[PWA] Service Worker registration failed:', err));
    });

    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (!refreshing) {
        refreshing = true;
        window.location.reload();
      }
    });
  }

  // PWA Install Prompt Lifecycle
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    const pwaBtn = document.getElementById('pwaInstallBtn');
    if (pwaBtn) pwaBtn.style.display = 'flex';
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    const pwaBtn = document.getElementById('pwaInstallBtn');
    if (pwaBtn) pwaBtn.style.display = 'none';
    utils.toast(state.user.language === 'id' ? 'Restu Finance berhasil diinstall!' : 'Restu Finance installed successfully!');
  });

  // Online / Offline Detection
  window.addEventListener('online', () => {
    utils.toast(state.user.language === 'id' ? 'Terhubung kembali ke jaringan' : 'Back online');
    if (isAuthenticated) loadApp();
  });

  window.addEventListener('offline', () => {
    utils.toast(state.user.language === 'id' ? 'Koneksi server/LAN terputus (Offline)' : 'Server connection lost (Offline)');
  });
}

// Expose all core functions to window for global access
window.loadApp = loadApp;
window.render = render;
window.renderPage = renderPage;
window.go = go;
window.dashboard = dashboard;
window.stats = stats;
window.settings = settings;
window.renderNotifications = renderNotifications;
window.getEffectiveLanInfo = getEffectiveLanInfo;
window.renderLanQrCode = renderLanQrCode;
window.bindPage = bindPage;

// ============================================================
// Initial Application Boot
// ============================================================
initGlobalListeners();
audio.initAudioUI();
loadApp();
theme.updateThemeButtons();
chatModule.initClientChat();
