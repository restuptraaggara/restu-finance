// public/js/utils.js - Formatting, Localization, and DOM Helpers

export const defaults = ['Makanan', 'Transportasi', 'Belanja', 'Hiburan', 'Tagihan', 'Pendidikan', 'Tabungan', 'Investasi', 'Kebutuhan', 'Lainnya'];

export const icons = {
  Makanan: 'FOOD',
  Transportasi: 'MOVE',
  Belanja: 'SHOP',
  Hiburan: 'FUN',
  Tagihan: 'BILL',
  Pendidikan: 'EDU',
  Tabungan: 'SAVE',
  Investasi: 'GROW',
  Kebutuhan: 'NEED',
  Lainnya: 'OTHER'
};

export const translations = {
  'Personal account': 'Akun pribadi', 'Dashboard': 'Dasbor', 'Transactions': 'Transaksi', 'Statistics': 'Statistik', 'Budget': 'Anggaran', 'Goals': 'Tujuan', 'Wallets': 'Dompet', 'Recurring': 'Berulang', 'Settings': 'Pengaturan',
  'Share Access (LAN)': 'Akses Jaringan (LAN)', 'Network Address': 'Alamat Jaringan', 'Share URL': 'URL Berbagi', 'Copy Link': 'Salin Link', 'Refresh IP': 'Segarkan IP',
  'Aplikasi PWA': 'Aplikasi PWA', 'Install App': 'Install Aplikasi', 'PWA Ready': 'Siap Install',
  'CURRENT BALANCE': 'SALDO SAAT INI', 'INCOME THIS MONTH': 'PEMASUKAN BULAN INI', 'EXPENSES THIS MONTH': 'PENGELUARAN BULAN INI', 'Cash flow': 'Arus kas', 'Income': 'Pemasukan', 'Expense': 'Pengeluaran', 'This month': 'Bulan ini', 'Last month': 'Bulan lalu', 'Spending by category': 'Pengeluaran berdasarkan kategori', 'Recent transactions': 'Transaksi terbaru', 'View all ->': 'Lihat semua ->', 'No spending yet': 'Belum ada pengeluaran', 'No transactions yet': 'Belum ada transaksi', 'Add transactions to get personalized insights.': 'Tambahkan transaksi untuk mendapatkan insight pribadi.', 'Smart insight:': 'Insight cerdas:', 'is your biggest expense this month at': 'adalah pengeluaran terbesar bulan ini sebesar', 'Transactions': 'Transaksi', 'YOUR MONEY TRAIL': 'RIWAYAT KEUANGAN', 'Every move, in one clear view.': 'Semua aktivitas dalam satu tampilan.', '＋ Add transaction': '＋ Tambah transaksi', '⌕  Search transactions...': '⌕  Cari transaksi...', 'All types': 'Semua tipe', 'All categories': 'Semua kategori', 'Newest first': 'Terbaru', 'Largest amount': 'Nominal terbesar', 'Transaction': 'Transaksi', 'Category': 'Kategori', 'Date': 'Tanggal', 'Amount': 'Nominal', 'MAKE BETTER DECISIONS': 'BUAT KEPUTUSAN LEBIH BAIK', 'A clearer picture of where you stand.': 'Gambaran yang lebih jelas tentang kondisi keuanganmu.', 'This year': 'Tahun ini', 'Custom range': 'Rentang khusus', 'INCOME VS EXPENSE': 'PEMASUKAN VS PENGELUARAN', 'Net cash flow this month': 'Arus kas bersih bulan ini', 'SPENDING BREAKDOWN': 'RINCIAN PENGELUARAN', 'No data for this period': 'Belum ada data untuk periode ini', 'Financial insight:': 'Insight keuangan:', 'Add income to unlock a spending ratio.': 'Tambahkan pemasukan untuk melihat rasio pengeluaran.', 'SPEND WITH INTENTION': 'BELANJA DENGAN SADAR', 'Monthly budget': 'Anggaran bulanan', 'spent': 'terpakai', '＋ Add budget': '＋ Tambah anggaran', 'Budget exceeded': 'Anggaran terlampaui', 'Approaching limit': 'Mendekati batas', 'used': 'terpakai', 'YOUR NEXT MILESTONE': 'TARGET BERIKUTNYA', 'Savings goals': 'Target tabungan', 'Small steps, meaningful progress.': 'Langkah kecil, hasil berarti.', '＋ New goal': '＋ Target baru', 'Deadline': 'Tenggat', 'WHERE YOUR MONEY LIVES': 'TEMPAT UANGMU DISIMPAN', 'Balances calculated from your transactions.': 'Saldo dihitung dari transaksi kamu.', '+ Add wallet': '+ Tambah dompet', 'Active': 'Aktif', 'Opening balance': 'Saldo awal', 'Edit': 'Edit', 'Delete': 'Hapus', 'Belum ada wallet. Tambahkan wallet untuk mulai mencatat transaksi.': 'Belum ada dompet. Tambahkan dompet untuk mulai mencatat transaksi.', 'MAKE IT YOURS': 'SESUAIKAN DENGANMU', 'Your preferences are saved automatically.': 'Preferensimu tersimpan otomatis.', 'Profile': 'Profil', 'Name': 'Nama', 'Currency': 'Mata uang', 'Save profile': 'Simpan profil', 'Appearance': 'Tampilan', 'Choose your preferred theme.': 'Pilih tema yang kamu sukai.', 'System': 'Sistem', 'Light': 'Terang', 'Dark': 'Gelap', 'Categories': 'Kategori', '+ Add category': '+ Tambah kategori', 'Export data': 'Ekspor data', 'Download your complete transaction history.': 'Unduh seluruh riwayat transaksi.', 'Export CSV ->': 'Ekspor CSV ->', 'English': 'Inggris', 'Indonesian': 'Indonesia', 'Language': 'Bahasa', 'About': 'Tentang aplikasi', 'General': 'Umum', 'Contact': 'Kontak', 'Phone': 'No. telepon', 'Instagram': 'Instagram', 'Email': 'Email', 'Version': 'Versi', 'No data yet': 'Belum ada data',
  'MANAGE OBLIGATIONS': 'KELOLA KEWAJIBAN', 'Utang & Piutang': 'Debts & Credits', '⬇ Ekspor CSV': '⬇ Export CSV', '⬆ Impor CSV': '⬆ Import CSV', 'Feedback & Bug Report': 'Kritik, Saran & Laporan Bug', 'Profil & Pengaturan Akun': 'Profile & Account Settings'
};

export const localize = html => {
  const lang = window.state?.user?.language || 'id';
  return lang === 'en' ? html : Object.entries(translations).reduce((out, [en, id]) => out.replaceAll(en, id), html);
};

export function applyStaticLanguage() {
  const lang = window.state?.user?.language || 'id';
  const en = lang === 'en';
  const text = (selector, value) => { const el = document.querySelector(selector); if (el) el.textContent = value; };
  const nav = (page, value) => { const el = document.querySelector(`.nav-item[data-page="${page}"]`); if (el && el.lastChild && el.lastChild.nodeType === 3) el.lastChild.nodeValue = value; };
  text('.profile small', en ? 'Personal account' : 'Akun pribadi');
  nav('dashboard', en ? 'Dashboard' : 'Dasbor');
  nav('transactions', en ? 'Transactions' : 'Transaksi');
  nav('statistics', en ? 'Statistics' : 'Statistik');
  nav('budget', en ? 'Budget' : 'Anggaran');
  nav('goals', en ? 'Goals' : 'Tujuan');
  nav('wallets', en ? 'Wallets' : 'Dompet');
  nav('debts', en ? 'Debts & Credits' : 'Utang & Piutang');
  nav('recurring', en ? 'Recurring' : 'Berulang');
  document.querySelectorAll('.nav-item[data-page="settings"]').forEach(el => { if (el.lastChild && el.lastChild.nodeType === 3) el.lastChild.nodeValue = en ? 'Settings' : 'Pengaturan'; });
  
  const sidebarLogout = document.getElementById('sidebarLogoutBtn');
  if (sidebarLogout && sidebarLogout.lastChild && sidebarLogout.lastChild.nodeType === 3) {
    sidebarLogout.lastChild.nodeValue = en ? 'Logout' : 'Keluar';
  }

  text('.upgrade strong', en ? 'Manage your money\nsmarter.' : 'Kelola uangmu\nlebih cerdas.');
  text('.upgrade .muted', en ? 'Safe & controlled balance' : 'Saldo aman & terkontrol');
  text('.add-trigger', en ? '＋ Add transaction' : '＋ Tambah transaksi');
  text('#modalTitle', en ? 'Add transaction' : 'Tambah transaksi');
  document.querySelectorAll('.type').forEach(el => { el.textContent = el.dataset.type === 'income' ? (en ? 'Income' : 'Pemasukan') : (en ? 'Expense' : 'Pengeluaran'); });
  const labels = document.querySelectorAll('#transactionForm > label, #transactionForm .form-grid label');
  if (labels[0] && labels[0].childNodes[0]) labels[0].childNodes[0].nodeValue = en ? 'Amount' : 'Nominal';
  if (labels[1] && labels[1].childNodes[0]) labels[1].childNodes[0].nodeValue = en ? 'Category' : 'Kategori';
  if (labels[2] && labels[2].childNodes[0]) labels[2].childNodes[0].nodeValue = en ? 'Wallet' : 'Dompet';
  if (labels[3] && labels[3].childNodes[0]) labels[3].childNodes[0].nodeValue = en ? 'Description ' : 'Deskripsi ';
  if (labels[4] && labels[4].childNodes[0]) labels[4].childNodes[0].nodeValue = en ? 'Payment method' : 'Metode pembayaran';
  if (labels[5] && labels[5].childNodes[0]) labels[5].childNodes[0].nodeValue = en ? 'Note ' : 'Catatan ';
  text('.save-btn', en ? 'Save transaction ↗' : 'Simpan transaksi ↗');
  const isLight = window.state?.user?.theme === 'light';
  const themeLabel = document.getElementById('standardThemeLabel');
  if (themeLabel) {
    themeLabel.textContent = isLight ? (en ? 'Light' : 'Terang') : (en ? 'Dark' : 'Gelap');
  }

  // Profile dropdown items
  const menuSettings = document.getElementById('profileMenuSettings');
  if (menuSettings) menuSettings.innerHTML = `<span class="dropdown-icon">⚙</span> ${en ? 'Profile & Settings' : 'Profil & Pengaturan'}`;
  const menuCopyId = document.getElementById('profileMenuCopyId');
  if (menuCopyId) menuCopyId.innerHTML = `<span class="dropdown-icon">📋</span> ${en ? 'Copy Session Info' : 'Salin ID / Info Sesi'}`;
  const menuLogout = document.getElementById('profileMenuLogout');
  if (menuLogout) menuLogout.innerHTML = `<span class="dropdown-icon">🚪</span> ${en ? 'Logout' : 'Keluar (Logout)'}`;
}

// Sanitasi HTML / teks agar aman dari serangan XSS
export function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

export const sanitizeHTML = escapeHtml;

// Format Rupiah (IDR)
export const money = n => 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
export const formatIDR = money;

// Parse amount dari input teks
export const parseAmount = value => Number(String(value ?? '').replace(/[^0-9]/g, '')) || 0;

export const currentMonth = new Date().toISOString().slice(0, 7);

export const previousMonth = () => {
  const d = new Date();
  d.setMonth(d.getMonth() - 1);
  return d.toISOString().slice(0, 7);
};

export const monthTx = (month = currentMonth) => (window.state?.transactions || []).filter(t => t.date && t.date.startsWith(month));

export const percentChange = (current, previous) => previous > 0 ? Math.round(((current - previous) / previous) * 100) : null;

export const trendMarkup = (current, previous, inverse = false) => {
  const change = percentChange(current, previous);
  if (change === null) return '';
  const down = change < 0;
  return `<span class="trend ${down ? 'down' : ''}">${down ? '↓' : '↑'} ${Math.abs(change)}%</span>`;
};

export function cashFlowChart(list) {
  const fallback = Array.from({ length: 7 }, (_, i) => {
    const d = new Date();
    d.setDate(d.getDate() - (6 - i));
    return d.toISOString().slice(0, 10);
  });
  const labels = fallback;
  const values = labels.map(date => ({
    date,
    income: total(list.filter(t => t.date === date), 'income'),
    expense: total(list.filter(t => t.date === date), 'expense')
  }));
  const max = Math.max(...values.map(x => Math.max(x.income, x.expense)), 0);
  return values.map(x => `<div class="bar-group"><i class="bar" style="height:${max ? x.income / max * 100 : 0}%"></i><i class="bar expense" style="height:${max ? x.expense / max * 100 : 0}%"></i><small class="bar-label">${new Date(x.date + 'T00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })}</small></div>`).join('');
}

export const total = (list, type) => (list || []).filter(t => !type || t.type === type).reduce((a, t) => a + (Number(t.amount) || 0), 0);

export const icon = c => icons[c] || '•';

export function financialStatus(income, expense) {
  if (!income && !expense) return { title: 'Belum ada aktivitas', text: 'Mulai catat transaksi untuk melihat kondisi keuanganmu.', tone: 'neutral' };
  const ratio = income ? expense / income : 1;
  if (ratio >= .8) return { title: 'Pengeluaran mulai tinggi', text: 'Coba cek kembali kategori dengan pengeluaran terbesar.', tone: 'warning' };
  if (ratio >= .5) return { title: 'Tetap pantau pengeluaran', text: 'Kondisi masih terkendali, tapi jangan lupa ikuti budget bulanan.', tone: 'watch' };
  return { title: 'Keuangan terlihat aman', text: 'Pengeluaranmu masih berada di bawah setengah pemasukan bulan ini.', tone: 'good' };
}

export const dateText = d => d ? new Date(d + 'T00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
export const formatDate = dateText;

export const saveLocalPreferences = () => {
  if (!window.state) return;
  localStorage.setItem('arus_last_wallet', window.state.lastWallet || '');
  localStorage.setItem('arus_last_category', window.state.lastCategory || '');
  localStorage.setItem('arus_theme', window.state.user?.theme || 'dark');
};

let toastTimer = null;
export function toast(text, type = 'info') {
  const t = document.getElementById('toast');
  if (!t) return;
  if (toastTimer) clearTimeout(toastTimer);
  t.className = 'toast';
  if (type === 'error' || type === 'danger') {
    t.classList.add('toast-error');
  } else if (type === 'success') {
    t.classList.add('toast-success');
  } else if (type === 'warning') {
    t.classList.add('toast-warning');
  }
  t.textContent = text;
  void t.offsetWidth;
  t.classList.add('show');
  toastTimer = setTimeout(() => t.classList.remove('show'), type === 'error' ? 3800 : 2600);
}

/**
 * Helper Debounce untuk menunda eksekusi (Live Search)
 */
export function debounce(fn, wait = 300) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn.apply(this, args), wait);
  };
}

/**
 * Modal konfirmasi kustom yang responsif dan touch-friendly
 */
export function showConfirmModal({ title = 'Konfirmasi', message = 'Apakah Anda yakin?', confirmText = 'Hapus', cancelText = 'Batal', isDanger = true }) {
  return new Promise((resolve) => {
    let modal = document.getElementById('customConfirmModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'customConfirmModal';
      modal.className = 'modal-backdrop';
      modal.innerHTML = `
        <div class="modal confirm-modal" role="dialog" aria-modal="true" style="max-width:400px;">
          <div class="modal-head">
            <div>
              <p class="eyebrow" id="confirmEyebrow">KONFIRMASI AKSI</p>
              <h2 id="confirmTitle" style="font-size:18px;">Konfirmasi</h2>
            </div>
            <button class="close-btn" id="confirmCloseBtn">×</button>
          </div>
          <div class="confirm-body" style="padding:14px 0;line-height:1.5;">
            <p id="confirmMessage" style="margin:0;white-space:pre-line;color:var(--text);font-size:14px;"></p>
          </div>
          <div class="confirm-actions" style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
            <button class="period" id="confirmCancelBtn" style="padding:12px 18px;min-height:44px;font-size:13px;flex:1;cursor:pointer;">Batal</button>
            <button class="${isDanger ? 'danger-btn' : 'primary'}" id="confirmOkBtn" style="padding:12px 18px;min-height:44px;font-size:13px;flex:1;cursor:pointer;">${escapeHtml(confirmText)}</button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    const titleEl = modal.querySelector('#confirmTitle');
    const msgEl = modal.querySelector('#confirmMessage');
    const okBtn = modal.querySelector('#confirmOkBtn');
    const cancelBtn = modal.querySelector('#confirmCancelBtn');
    const closeBtn = modal.querySelector('#confirmCloseBtn');

    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message;
    if (okBtn) {
      okBtn.textContent = confirmText;
      okBtn.className = isDanger ? 'danger-btn' : 'primary';
    }
    if (cancelBtn) cancelBtn.textContent = cancelText;

    const cleanup = (result) => {
      modal.classList.remove('show');
      okBtn.onclick = null;
      cancelBtn.onclick = null;
      if (closeBtn) closeBtn.onclick = null;
      modal.onclick = null;
      resolve(result);
    };

    okBtn.onclick = () => cleanup(true);
    cancelBtn.onclick = () => cleanup(false);
    if (closeBtn) closeBtn.onclick = () => cleanup(false);
    modal.onclick = (e) => {
      if (e.target === modal) cleanup(false);
    };

    modal.classList.add('show');
  });
}

// Expose to window for backward compatibility and inline HTML access
window.defaults = defaults;
window.icons = icons;
window.translations = translations;
window.localize = localize;
window.applyStaticLanguage = applyStaticLanguage;
window.escapeHtml = escapeHtml;
window.sanitizeHTML = sanitizeHTML;
window.money = money;
window.formatIDR = formatIDR;
window.parseAmount = parseAmount;
window.currentMonth = currentMonth;
window.previousMonth = previousMonth;
window.monthTx = monthTx;
window.percentChange = percentChange;
window.trendMarkup = trendMarkup;
window.cashFlowChart = cashFlowChart;
window.total = total;
window.icon = icon;
window.financialStatus = financialStatus;
window.dateText = dateText;
window.formatDate = formatDate;
window.saveLocalPreferences = saveLocalPreferences;
window.toast = toast;
window.debounce = debounce;
window.showConfirmModal = showConfirmModal;

