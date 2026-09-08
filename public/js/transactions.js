// public/js/transactions.js - Transactions Management, Filter, Split Needs, and CSV Export

import { escapeHtml, money, dateText, total, monthTx, icon, saveLocalPreferences, toast, debounce, showConfirmModal } from './utils.js';
import { apiFetch } from './api.js';
import { showPixelDialog } from './theme.js';

export function transaction(t) {
  return `
    <div class="transaction" data-id="${t.id}">
      <span class="cat-icon">${icon(t.category)}</span>
      <div class="transaction-info">
        <strong>${escapeHtml(t.description)}</strong>
        <small>${escapeHtml(t.category)} · ${dateText(t.date)} · ${escapeHtml(t.wallet)}${t.goal ? ` · Goal: ${escapeHtml(t.goal)}` : ''}</small>
      </div>
      <div class="transaction-amount ${t.type === 'income' ? 'income-text' : 'expense-text'}">
        ${t.type === 'income' ? '+' : '−'} ${money(t.amount)}
        <small>${escapeHtml(t.method)}</small>
      </div>
    </div>
  `;
}

export function emptyState() {
  return `
    <div class="empty-state-card" style="padding:40px 20px;text-align:center;">
      <div class="empty-icon" style="font-size:36px;margin-bottom:8px;">⇄</div>
      <h3 style="margin:0 0 6px;">Belum Ada Transaksi</h3>
      <p class="muted" style="margin:0 0 16px;">Mulai catat pemasukan dan pengeluaran harianmu dengan menekan tombol di bawah.</p>
      <button class="primary add-trigger" style="display:inline-flex;align-items:center;gap:6px;margin:auto;">＋ Catat Transaksi Baru</button>
    </div>
  `;
}

export function emptySearchState(query = '') {
  return `
    <div class="empty-search-state" style="padding:40px 20px;text-align:center;">
      <div class="empty-search-icon" style="font-size:36px;margin-bottom:8px;">🔍</div>
      <h3 style="margin:0 0 6px;">Transaksi Tidak Ditemukan</h3>
      <p class="muted" style="margin:0 0 16px;">Tidak ada transaksi yang cocok dengan kata kunci "<strong>${escapeHtml(query)}</strong>". Coba kata kunci lain atau reset filter.</p>
      <button class="period" id="resetSearchBtn" style="padding:8px 18px;font-size:13px;cursor:pointer;">✕ Reset Pencarian</button>
    </div>
  `;
}

export function transactions() {
  const state = window.state || { transactions: [], categories: [] };
  const txList = (state.transactions || []).slice().sort((a, b) => (b.date || '').localeCompare(a.date || ''));

  return `
    <div class="transaction-page-head">
      <div>
        <p class="eyebrow">YOUR MONEY TRAIL</p>
        <h1 class="page-title">Transactions</h1>
        <p class="muted">Kelola, cari, dan pantau semua aktivitas keuanganmu.</p>
      </div>
    </div>
    <div class="toolbar">
      <input class="search" id="search" placeholder="⌕  Search transactions...">
      <select class="filter" id="typeFilter">
        <option value="">All types</option>
        <option value="income">Income</option>
        <option value="expense">Expense</option>
      </select>
      <select class="filter" id="catFilter">
        <option value="">All categories</option>
        ${(state.categories || []).map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('')}
      </select>
      <select class="filter" id="sortFilter">
        <option value="date">Newest first</option>
        <option value="amount">Largest amount</option>
      </select>
      <button class="primary add-trigger transaction-add">＋ Add transaction</button>
      <button class="period" id="exportFilteredCsv" title="Unduh data transaksi (.csv)">📥 Ekspor CSV</button>
      <button class="period" id="importCsvBtn" title="Impor data transaksi (.csv)">⬆ Impor CSV</button>
      <input type="file" id="csvFileInput" accept=".csv,text/csv" style="display:none;">
    </div>
    <div class="transaction-summary">
      <div>
        <small>Total transaksi</small>
        <strong>${(state.transactions || []).length}</strong>
        <span>Semua aktivitas tercatat</span>
      </div>
      <div>
        <small>Pemasukan bulan ini</small>
        <strong class="income-text">${money(total(monthTx(), 'income'))}</strong>
        <span>Uang masuk</span>
      </div>
      <div>
        <small>Pengeluaran bulan ini</small>
        <strong>${money(total(monthTx(), 'expense'))}</strong>
        <span>Uang keluar</span>
      </div>
    </div>
    <div class="card table-card">
      <div class="table-head">
        <span>Transaction</span>
        <span>Category</span>
        <span>Date</span>
        <span>Amount</span>
      </div>
      <div id="transactionList">
        ${txList.map(t => `
          <div class="table-row" data-id="${t.id}">
            <div class="transaction">${transaction(t).replace(/^<div class="transaction"[^>]*>|<\/div>$/g, '')}</div>
            <span>${escapeHtml(t.category)}</span>
            <span>${dateText(t.date)}</span>
            <span class="${t.type === 'income' ? 'income-text' : ''}">${t.type === 'income' ? '+' : '−'}${money(t.amount)}</span>
          </div>
        `).join('') || emptyState()}
      </div>
    </div>
  `;
}

export function categoryOptions(selected = '') {
  const state = window.state || { categories: [] };
  return (state.categories || []).map(c => `<option ${c === selected ? 'selected' : ''} value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
}

export function refreshSplitRows() {
  document.querySelectorAll('.split-category').forEach(el => {
    el.innerHTML = categoryOptions(el.value);
  });
}

export function toggleAllocation() {
  const income = document.querySelector('.type.active')?.dataset.type === 'income';
  const header = document.querySelector('.split-header');
  const rows = document.getElementById('splitRows');
  const categoryField = document.getElementById('categoryField');

  if (header) {
    header.hidden = income;
    header.style.display = income ? 'none' : 'flex';
  }
  if (rows) {
    rows.hidden = income;
    rows.style.display = income ? 'none' : 'grid';
  }
  if (categoryField) {
    categoryField.hidden = income;
    categoryField.style.display = income ? 'none' : 'block';
  }
  if (income) {
    const catInput = document.getElementById('category');
    if (catInput) catInput.value = 'Pemasukan';
  }
}

export function openModal() {
  const state = window.state || { categories: [], wallets: [], goals: [] };
  const backdrop = document.getElementById('modalBackdrop');
  if (backdrop) backdrop.classList.add('show');

  const catSelect = document.getElementById('category');
  if (catSelect) {
    catSelect.innerHTML = categoryOptions(state.lastCategory || state.categories[0] || 'Makanan');
  }

  const walletSelect = document.getElementById('wallet');
  if (walletSelect) {
    walletSelect.innerHTML = (state.wallets || []).map(w => `<option ${w.name === (state.lastWallet || state.wallets[0]?.name) ? 'selected' : ''} value="${escapeHtml(w.name)}">${escapeHtml(w.name)} (${money(w.balance)})</option>`).join('');
  }

  const goalSelect = document.getElementById('goal');
  if (goalSelect) {
    goalSelect.innerHTML = '<option value="">Bukan alokasi target tabungan</option>' + (state.goals || []).map(g => `<option value="${escapeHtml(g.name)}">${escapeHtml(g.name)}</option>`).join('');
  }

  const dateInput = document.getElementById('date');
  if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);

  const timeInput = document.getElementById('time');
  if (timeInput) timeInput.value = new Date().toTimeString().slice(0, 5);

  refreshSplitRows();
  toggleAllocation();
  checkWalletBalance();
}

export function closeModal() {
  const backdrop = document.getElementById('modalBackdrop');
  if (backdrop) backdrop.classList.remove('show');
  const form = document.getElementById('transactionForm');
  if (form) form.reset();
  const warningEl = document.getElementById('insufficientWarning');
  if (warningEl) warningEl.style.display = 'none';
  document.querySelectorAll('.type').forEach(x => x.classList.toggle('active', x.dataset.type === 'expense'));
  toggleAllocation();
}

export function checkWalletBalance() {
  const state = window.state || { wallets: [] };
  const warningEl = document.getElementById('insufficientWarning');
  if (!warningEl) return;

  const isExpense = document.querySelector('.type.active')?.dataset.type !== 'income';
  if (!isExpense) {
    warningEl.style.display = 'none';
    return;
  }

  const amountInput = document.getElementById('amount');
  const amount = Number((amountInput?.value || '').replace(/[^0-9]/g, ''));
  const walletName = document.getElementById('wallet')?.value;
  const wallet = (state.wallets || []).find(w => w.name === walletName);

  if (wallet && amount > (wallet.balance || 0)) {
    const diff = amount - (wallet.balance || 0);
    warningEl.style.display = 'flex';
    warningEl.innerHTML = `
      <div style="flex:1;">
        <strong style="color:var(--red, #ff8f87);">⚠️ Saldo dompet tidak mencukupi!</strong>
        <p style="margin:2px 0 0;font-size:12px;opacity:0.9;">Saldo "${escapeHtml(wallet.name)}": ${money(wallet.balance)}. Kurang ${money(diff)}.</p>
      </div>
      <button type="button" class="period convert-debt-btn" id="convertToDebtBtn" style="white-space:nowrap;font-size:11px;padding:6px 12px;font-weight:700;color:var(--red);border-color:var(--red);cursor:pointer;">
        ➕ Catat sebagai Utang
      </button>
    `;

    const convertBtn = warningEl.querySelector('#convertToDebtBtn');
    if (convertBtn) {
      convertBtn.onclick = () => {
        const note = document.getElementById('note')?.value || '';
        closeModal();
        if (typeof window.go === 'function') window.go('debts');
        if (typeof window.openDebtModal === 'function') {
          setTimeout(() => {
            window.openDebtModal(null, {
              type: 'debt',
              amount: amount,
              notes: note || `Kekurangan pembayaran di ${wallet.name}`,
            });
          }, 200);
        }
      };
    }
  } else {
    warningEl.style.display = 'none';
  }
}

export function filter() {
  const state = window.state || { transactions: [] };
  const allTx = (state.transactions || []).slice();
  let a = allTx.slice();
  const q = (document.getElementById('search')?.value || '').toLowerCase().trim();
  const rawDigits = q.replace(/[^0-9]/g, '');
  const type = document.getElementById('typeFilter')?.value;
  const cat = document.getElementById('catFilter')?.value;

  a = a.filter(t => {
    let matchQ = true;
    if (q) {
      const descMatch = (t.description || '').toLowerCase().includes(q);
      const catMatch = (t.category || '').toLowerCase().includes(q);
      const walletMatch = (t.wallet || '').toLowerCase().includes(q);
      const noteMatch = (t.note || '').toLowerCase().includes(q);
      const amountNumMatch = rawDigits ? String(t.amount || '').includes(rawDigits) : false;
      const amountFmtMatch = money(t.amount).toLowerCase().includes(q);
      matchQ = descMatch || catMatch || walletMatch || noteMatch || amountNumMatch || amountFmtMatch;
    }

    const matchType = !type || t.type === type;
    const matchCat = !cat || t.category === cat;

    return matchQ && matchType && matchCat;
  });

  if (document.getElementById('sortFilter')?.value === 'amount') {
    a.sort((x, y) => y.amount - x.amount);
  } else {
    a.sort((x, y) => (y.date || '').localeCompare(x.date || ''));
  }

  const listEl = document.getElementById('transactionList');
  if (listEl) {
    if (allTx.length === 0) {
      listEl.innerHTML = emptyState();
    } else if (a.length === 0) {
      listEl.innerHTML = emptySearchState(q);
      const resetBtn = document.getElementById('resetSearchBtn');
      if (resetBtn) {
        resetBtn.onclick = () => {
          const s = document.getElementById('search');
          const tf = document.getElementById('typeFilter');
          const cf = document.getElementById('catFilter');
          if (s) s.value = '';
          if (tf) tf.value = '';
          if (cf) cf.value = '';
          filter();
        };
      }
    } else {
      listEl.innerHTML = a.map(t => `
        <div class="table-row" data-id="${t.id}">
          <div class="transaction">${transaction(t).replace(/^<div class="transaction"[^>]*>|<\/div>$/g, '')}</div>
          <span>${escapeHtml(t.category)}</span>
          <span>${dateText(t.date)}</span>
          <span class="${t.type === 'income' ? 'income-text' : ''}">${t.type === 'income' ? '+' : '−'}${money(t.amount)}</span>
        </div>
      `).join('');
    }
  }

  // Re-bind row click handlers
  bindRowClicks();
}

function bindRowClicks() {
  const state = window.state || { transactions: [], user: {} };
  document.querySelectorAll('#transactionList [data-id], .card.transactions [data-id]').forEach(x => {
    x.onclick = async () => {
      const t = (state.transactions || []).find(t => t.id == x.dataset.id);
      if (!t) return;
      const isId = state.user?.language === 'id';

      const confirmed = await showConfirmModal({
        title: isId ? 'Hapus Transaksi' : 'Delete Transaction',
        message: `${t.description || t.category}\n${money(t.amount)} · ${t.wallet}\n\n${isId ? 'Apakah Anda yakin ingin menghapus transaksi ini? Saldo dompet akan disesuaikan otomatis.' : 'Are you sure you want to delete this transaction? Wallet balance will be updated automatically.'}`,
        confirmText: isId ? 'Ya, Hapus' : 'Delete',
        cancelText: isId ? 'Batal' : 'Cancel',
        isDanger: true,
      });

      if (confirmed) {
        try {
          await apiFetch(`/api/transactions/${t.id}`, { method: 'DELETE' });
          toast(isId ? 'Transaksi berhasil dihapus' : 'Transaction deleted');
          if (typeof window.loadApp === 'function') await window.loadApp();
        } catch (err) {
          toast(err.message || (isId ? 'Gagal menghapus transaksi' : 'Failed to delete transaction'));
        }
      }
    };
  });
}

/**
 * Enhanced CSV Exporter with UTF-8 BOM, strict RFC 4180 escaping,
 * and support for current active filters.
 */
export function exportTransactionsCsv(filteredOnly = false) {
  const state = window.state || { transactions: [] };
  let list = (state.transactions || []).slice();

  if (filteredOnly) {
    const q = (document.getElementById('search')?.value || '').toLowerCase();
    const type = document.getElementById('typeFilter')?.value;
    const cat = document.getElementById('catFilter')?.value;
    list = list.filter(t => (!q || `${t.description} ${t.category}`.toLowerCase().includes(q)) && (!type || t.type === type) && (!cat || t.category === cat));
    if (document.getElementById('sortFilter')?.value === 'amount') {
      list.sort((x, y) => y.amount - x.amount);
    } else {
      list.sort((x, y) => (y.date || '').localeCompare(x.date || ''));
    }
  }

  // Header row matching RFC 4180 standard for Excel
  const headers = ['No', 'Tanggal', 'Tipe', 'Kategori', 'Dompet', 'Deskripsi', 'Nominal', 'Metode', 'Catatan'];

  const rows = list.map((t, idx) => [
    idx + 1,
    t.date || '',
    t.type === 'income' ? 'Income' : 'Expense',
    t.category || '',
    t.wallet || '',
    t.description || '',
    Number(t.amount || 0),
    t.method || '',
    t.note || ''
  ]);

  // Escape CSV cells
  const escapeCsvCell = cell => `"${String(cell ?? '').replaceAll('"', '""')}"`;

  const csvContent = [
    headers.map(escapeCsvCell).join(','),
    ...rows.map(row => row.map(escapeCsvCell).join(','))
  ].join('\r\n');

  // Prepend UTF-8 BOM (\uFEFF) for Excel / Google Sheets compatibility
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  const now = new Date().toISOString().slice(0, 10);
  a.download = `restu-finance-transactions-${now}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(a.href);

  toast(window.state?.user?.language === 'en' ? 'CSV downloaded successfully!' : 'File CSV berhasil diunduh!');
}

/**
 * Upload and import CSV file of transactions
 */
export async function uploadTransactionsCsv(file) {
  if (!file) return;
  const isId = window.state?.user?.language === 'id';
  const formData = new FormData();
  formData.append('file', file);

  try {
    toast(isId ? 'Mengunggah dan memproses berkas CSV...' : 'Uploading and processing CSV file...');
    const res = await apiFetch('/api/transactions/import', {
      method: 'POST',
      body: formData,
    });
    if (res && res.success) {
      const count = res.data?.imported_count ?? 0;
      toast(isId ? `Berhasil mengimpor ${count} transaksi!` : `Successfully imported ${count} transactions!`);
      if (typeof window.loadApp === 'function') {
        await window.loadApp();
      }
    }
  } catch (err) {
    toast(err.message || (isId ? 'Gagal mengimpor file CSV' : 'Failed to import CSV file'));
  }
}

export function initTransactionHandlers() {
  document.querySelectorAll('.add-trigger').forEach(b => b.onclick = openModal);

  const modalCloseBtn = document.getElementById('modalClose');
  if (modalCloseBtn) modalCloseBtn.onclick = closeModal;

  const modalBackdrop = document.getElementById('modalBackdrop');
  if (modalBackdrop) {
    modalBackdrop.onclick = e => {
      if (e.target === modalBackdrop) closeModal();
    };
  }

  // Type buttons (Income vs Expense in modal)
  document.querySelectorAll('.type').forEach(btn => {
    btn.onclick = () => {
      document.querySelectorAll('.type').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      toggleAllocation();
      checkWalletBalance();
    };
  });

  // Real-time balance check listeners
  const amountInput = document.getElementById('amount');
  if (amountInput) {
    amountInput.addEventListener('input', checkWalletBalance);
  }
  const walletSelect = document.getElementById('wallet');
  if (walletSelect) {
    walletSelect.addEventListener('change', checkWalletBalance);
  }

  // Search & Filter listeners (Debounced 300ms)
  const s = document.getElementById('search');
  if (s) s.oninput = debounce(filter, 300);

  ['typeFilter', 'catFilter', 'sortFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.onchange = filter;
  });

  // Export CSV triggers
  const exportFilteredBtn = document.getElementById('exportFilteredCsv');
  if (exportFilteredBtn) {
    exportFilteredBtn.onclick = () => exportTransactionsCsv(true);
  }

  document.querySelectorAll('#exportCsv').forEach(el => {
    el.onclick = () => exportTransactionsCsv(false);
  });

  // Import CSV triggers
  const importCsvBtn = document.getElementById('importCsvBtn');
  const csvFileInput = document.getElementById('csvFileInput');
  if (importCsvBtn && csvFileInput) {
    importCsvBtn.onclick = () => {
      csvFileInput.value = '';
      csvFileInput.click();
    };
    csvFileInput.onchange = e => {
      const file = e.target.files?.[0];
      if (file) uploadTransactionsCsv(file);
    };
  }

  // Bind row clicks
  bindRowClicks();

  // Form Submit Handler
  const txForm = document.getElementById('transactionForm');
  if (txForm) {
    txForm.onsubmit = async e => {
      e.preventDefault();
      const state = window.state || { categories: [] };
      const amountInput = document.getElementById('amount');
      const amount = Number((amountInput?.value || '').replace(/[^0-9]/g, ''));
      if (!amount || amount <= 0) return toast('Masukkan nominal yang valid');

      const type = document.querySelector('.type.active')?.dataset.type || 'expense';
      const rows = [...document.querySelectorAll('.split-row')].map(row => ({
        category: row.querySelector('.split-category')?.value,
        amount: Number((row.querySelector('.split-amount')?.value || '').replace(/[^0-9]/g, ''))
      })).filter(row => row.amount > 0);

      if (!rows.length) {
        rows.push({
          category: type === 'income' ? 'Pemasukan' : document.getElementById('category')?.value || state.categories[0] || 'Lainnya',
          amount
        });
      }

      const splitTotal = rows.reduce((sum, row) => sum + row.amount, 0);
      if (splitTotal !== amount) return toast(`Total alokasi harus ${money(amount)}`);

      const description = (document.getElementById('note')?.value || '').trim();
      const wallet = document.getElementById('wallet')?.value || 'Utama';
      const method = document.getElementById('method')?.value || 'E-wallet';
      const date = document.getElementById('date')?.value || new Date().toISOString().slice(0, 10);
      const time = document.getElementById('time')?.value || new Date().toTimeString().slice(0, 5);
      const goalName = document.getElementById('goal')?.value || null;

      try {
        for (const row of rows) {
          await apiFetch('/api/transactions', {
            method: 'POST',
            body: JSON.stringify({
              type,
              amount: row.amount,
              category: row.category,
              description: description || row.category,
              wallet,
              method,
              date,
              time,
              note: description,
              goal: goalName || null,
            }),
          });
        }

        state.lastCategory = rows[0].category;
        state.lastWallet = wallet;
        saveLocalPreferences();
        closeModal();
        toast(state.user?.language === 'id' ? 'Transaksi berhasil disimpan' : 'Transaction saved');

        // Character dialog feedback
        if (type === 'income') {
          showPixelDialog('Good lah!', 'income');
        } else {
          showPixelDialog('Jangan boros-boros gitu!', 'expense');
        }

        if (typeof window.loadApp === 'function') await window.loadApp();
      } catch (err) {
        toast(err.message || 'Gagal menyimpan transaksi');
      }
    };
  }
}

// Expose on window
window.transactions = transactions;
window.transaction = transaction;
window.emptyState = emptyState;
window.filter = filter;
window.openModal = openModal;
window.closeModal = closeModal;
window.categoryOptions = categoryOptions;
window.refreshSplitRows = refreshSplitRows;
window.toggleAllocation = toggleAllocation;
window.exportTransactionsCsv = exportTransactionsCsv;
window.initTransactionHandlers = initTransactionHandlers;
