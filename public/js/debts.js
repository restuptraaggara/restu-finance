// public/js/debts.js - Debt & Credit Management (Utang & Piutang Tracker)

import { escapeHtml, money, parseAmount, dateText, toast, showConfirmModal } from './utils.js';
import { apiFetch } from './api.js';

let activeDebtTab = 'all'; // 'all', 'debt', 'credit'
let activeStatusFilter = ''; // '', 'unpaid', 'partial', 'paid'
let editingDebtId = null;

export function debts() {
  const state = window.state || { debts: [], wallets: [] };
  const allDebts = state.debts || [];

  // Filter list
  let filtered = allDebts.filter(d => {
    if (activeDebtTab !== 'all' && d.type !== activeDebtTab) return false;
    if (activeStatusFilter && d.status !== activeStatusFilter) return false;
    return true;
  });

  // Calculate summary stats
  const totalDebtRemaining = allDebts
    .filter(d => d.type === 'debt' && d.status !== 'paid')
    .reduce((sum, d) => sum + (d.remaining_amount ?? Math.max(0, d.amount - d.paid_amount)), 0);

  const totalCreditRemaining = allDebts
    .filter(d => d.type === 'credit' && d.status !== 'paid')
    .reduce((sum, d) => sum + (d.remaining_amount ?? Math.max(0, d.amount - d.paid_amount)), 0);

  const overdueCount = allDebts.filter(d => d.is_overdue).length;

  const emptyHtml = `
    <div class="empty-state-card" style="grid-column: 1 / -1;padding:40px 20px;text-align:center;">
      <div class="empty-icon" style="font-size:36px;margin-bottom:8px;">⚖</div>
      <h3 style="margin:0 0 6px;">Belum Ada Catatan Utang / Piutang</h3>
      <p class="muted" style="margin:0 0 16px;">Catat pinjaman uang yang harus kamu bayar (Utang) atau uang yang dipinjam orang lain (Piutang) beserta tenggat waktunya.</p>
      <button class="primary" id="addDebtEmptyBtn" style="margin:auto;">＋ Catat Utang / Piutang Pertama</button>
    </div>
  `;

  return `
    <div class="page-heading">
      <div>
        <p class="eyebrow">MANAGE OBLIGATIONS</p>
        <h1 class="page-title">Utang & Piutang</h1>
        <p class="muted">Pantau sisa pinjaman, tagihan, dan riwayat pembayaran cicilan dengan rapi.</p>
      </div>
      <button class="primary" id="addDebtBtn">＋ Catat Baru</button>
    </div>

    <!-- Summary Metric Cards -->
    <div class="cards" style="margin-bottom:24px;">
      <div class="card debt-summary-card">
        <div class="card-top">
          <span class="card-label">UTANG SAYA (HARUS DIBAYAR)</span>
          <span class="trend" style="color:var(--red, #ff8f87);">Kewajiban</span>
        </div>
        <div class="stat-value" style="color:var(--red, #ff8f87);">${money(totalDebtRemaining)}</div>
        <span class="stat-sub">Total sisa utang yang belum lunas</span>
      </div>

      <div class="card credit-summary-card">
        <div class="card-top">
          <span class="card-label">PIUTANG SAYA (HARUS DITAGIH)</span>
          <span class="trend" style="color:var(--green, #d7f56f);">Hak Tagih</span>
        </div>
        <div class="stat-value" style="color:var(--green, #d7f56f);">${money(totalCreditRemaining)}</div>
        <span class="stat-sub">Total uang Anda di orang lain</span>
      </div>

      <div class="card alert-summary-card">
        <div class="card-top">
          <span class="card-label">STATUS TENGGAT WAKTU</span>
          <span class="trend ${overdueCount > 0 ? 'over' : ''}">${overdueCount > 0 ? 'Perhatian' : 'Aman'}</span>
        </div>
        <div class="stat-value" style="${overdueCount > 0 ? 'color:var(--red, #ff8f87);' : ''}">${overdueCount} Jatuh Tempo</div>
        <span class="stat-sub">${overdueCount > 0 ? 'Ada utang/piutang melewati batas waktu' : 'Semua pembayaran berjalan sesuai jadwal'}</span>
      </div>
    </div>

    <!-- Filter & Toolbar -->
    <div class="toolbar" style="margin-bottom:20px;">
      <div class="debt-tab-group" style="display:flex;gap:4px;background:var(--panel-2);border:1px solid var(--line);border-radius:10px;padding:3px;">
        <button type="button" class="period debt-tab ${activeDebtTab === 'all' ? 'active' : ''}" data-debt-tab="all" style="padding:6px 14px;border:none;border-radius:7px;cursor:pointer;">Semua</button>
        <button type="button" class="period debt-tab ${activeDebtTab === 'debt' ? 'active' : ''}" data-debt-tab="debt" style="padding:6px 14px;border:none;border-radius:7px;cursor:pointer;">🔴 Utang Saya</button>
        <button type="button" class="period debt-tab ${activeDebtTab === 'credit' ? 'active' : ''}" data-debt-tab="credit" style="padding:6px 14px;border:none;border-radius:7px;cursor:pointer;">🟢 Piutang Saya</button>
      </div>

      <select class="filter" id="debtStatusFilter">
        <option value="" ${activeStatusFilter === '' ? 'selected' : ''}>Semua Status</option>
        <option value="unpaid" ${activeStatusFilter === 'unpaid' ? 'selected' : ''}>Belum Dibayar</option>
        <option value="partial" ${activeStatusFilter === 'partial' ? 'selected' : ''}>Dicicil (Sebagian)</option>
        <option value="paid" ${activeStatusFilter === 'paid' ? 'selected' : ''}>Sudah Lunas</option>
      </select>
    </div>

    <!-- Debt Cards Grid -->
    <div class="metric-grid">
      ${filtered.map(d => {
        const remaining = d.remaining_amount ?? Math.max(0, d.amount - d.paid_amount);
        const pct = d.percentage ?? (d.amount > 0 ? Math.min(100, Math.round((d.paid_amount / d.amount) * 100)) : 100);
        const isDebt = d.type === 'debt';
        const isOverdue = Boolean(d.is_overdue);
        const isPaid = d.status === 'paid';

        return `
          <div class="card debt-card ${isOverdue ? 'overdue-card' : ''}" data-id="${d.id}">
            <div class="section-head" style="margin-bottom:8px;">
              <div>
                <span class="badge ${isDebt ? 'debt-badge-danger' : 'debt-badge-success'}" style="font-size:11px;padding:3px 8px;border-radius:6px;font-weight:700;display:inline-block;margin-bottom:6px;">
                  ${isDebt ? '🔴 Utang (Saya Berutang)' : '🟢 Piutang (Dipinjam Orang)'}
                </span>
                <h2 style="font-size:17px;margin:0;">${escapeHtml(d.person_name)}</h2>
              </div>
              <span class="trend ${isPaid ? 'paid-trend' : isOverdue ? 'over' : ''}">
                ${isPaid ? '✓ Lunas' : isOverdue ? '⚠️ Lewat Tenggat' : d.status === 'partial' ? 'Cicilan' : 'Belum Bayar'}
              </span>
            </div>

            <!-- Amount Breakdown -->
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin:12px 0 6px;">
              <div>
                <small class="muted" style="display:block;font-size:11px;">Sisa Tagihan</small>
                <strong style="font-size:20px;color:${isPaid ? 'var(--green)' : isDebt ? 'var(--red)' : 'var(--green)'};">${money(remaining)}</strong>
              </div>
              <div style="text-align:right;">
                <small class="muted" style="display:block;font-size:11px;">Total Pokok</small>
                <span style="font-size:13px;opacity:0.8;">${money(d.amount)}</span>
              </div>
            </div>

            <!-- Progress Bar -->
            <div class="wide-progress" style="margin:8px 0 6px;">
              <i class="${isPaid ? 'over' : pct >= 50 ? 'warn' : ''}" style="width:${pct}%"></i>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:12px;">
              <span>Terbayar: <b>${money(d.paid_amount)}</b> (${pct}%)</span>
              <span>${d.due_date ? 'Jatuh Tempo: ' + dateText(d.due_date) : 'Tanpa tenggat'}</span>
            </div>

            ${d.notes ? `<p style="font-size:12px;color:var(--muted);margin:0 0 12px;background:var(--panel-2);padding:6px 10px;border-radius:6px;">💬 ${escapeHtml(d.notes)}</p>` : ''}

            <!-- Card Actions -->
            <div class="wallet-actions" style="display:flex;gap:6px;">
              ${!isPaid ? `<button class="primary pay-debt-trigger" data-debt-id="${d.id}" style="flex:1.2;font-size:12px;padding:8px 12px;">💸 Bayar / Cicil</button>` : ''}
              <button class="period edit-debt-trigger" data-debt-id="${d.id}" style="flex:1;font-size:12px;padding:8px 10px;">✏️ Edit</button>
              <button class="period delete-debt-trigger" data-debt-id="${d.id}" style="color:var(--red);border-color:var(--red);font-size:12px;padding:8px 10px;">🗑</button>
            </div>
          </div>
        `;
      }).join('') || emptyHtml}
    </div>
  `;
}

// ============================================================
// Modal & Action Handlers for Debt Module
// ============================================================
export function initDebtHandlers() {
  const state = window.state || { debts: [], wallets: [] };

  // Filter Tabs click
  document.querySelectorAll('.debt-tab').forEach(btn => {
    btn.onclick = () => {
      activeDebtTab = btn.dataset.debtTab || 'all';
      if (typeof window.renderPage === 'function') window.renderPage();
    };
  });

  // Status Filter change
  const statusFilter = document.getElementById('debtStatusFilter');
  if (statusFilter) {
    statusFilter.onchange = () => {
      activeStatusFilter = statusFilter.value;
      if (typeof window.renderPage === 'function') window.renderPage();
    };
  }

  // Open Create Modal
  const addBtn = document.getElementById('addDebtBtn');
  const addEmptyBtn = document.getElementById('addDebtEmptyBtn');
  if (addBtn) addBtn.onclick = () => openDebtModal();
  if (addEmptyBtn) addEmptyBtn.onclick = () => openDebtModal();

  // Pay Debt Triggers
  document.querySelectorAll('.pay-debt-trigger').forEach(btn => {
    btn.onclick = () => {
      const debtId = Number(btn.dataset.debtId);
      const debt = (state.debts || []).find(d => d.id === debtId);
      if (debt) openPayDebtModal(debt);
    };
  });

  // Edit Debt Triggers
  document.querySelectorAll('.edit-debt-trigger').forEach(btn => {
    btn.onclick = () => {
      const debtId = Number(btn.dataset.debtId);
      const debt = (state.debts || []).find(d => d.id === debtId);
      if (debt) openDebtModal(debt);
    };
  });

  // Delete Debt Triggers
  document.querySelectorAll('.delete-debt-trigger').forEach(btn => {
    btn.onclick = async () => {
      const debtId = Number(btn.dataset.debtId);
      const debt = (state.debts || []).find(d => d.id === debtId);
      if (!debt) return;

      const isDebt = debt.type === 'debt';
      const confirmed = await showConfirmModal({
        title: isDebt ? 'Hapus Catatan Utang' : 'Hapus Catatan Piutang',
        message: `Apakah Anda yakin ingin menghapus catatan kepada "${debt.person_name}" sebesar ${money(debt.amount)}? Seluruh riwayat cicilan terkait juga akan dihapus.`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        isDanger: true,
      });

      if (confirmed) {
        try {
          await apiFetch(`/api/debts/${debt.id}`, { method: 'DELETE' });
          toast('Catatan berhasil dihapus');
          if (typeof window.loadApp === 'function') await window.loadApp();
        } catch (err) {
          toast(err.message || 'Gagal menghapus catatan');
        }
      }
    };
  });
}

// ============================================================
// Modal Form: Tambah / Edit Utang & Piutang
// ============================================================
export function openDebtModal(debt = null, prefill = {}) {
  editingDebtId = debt ? debt.id : null;
  let modal = document.getElementById('debtModalBackdrop');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'debtModalBackdrop';
    modal.className = 'modal-backdrop';
    document.body.appendChild(modal);
  }

  const isEdit = Boolean(debt);
  const initialType = debt ? debt.type : (prefill.type || 'debt');
  const initialName = debt ? debt.person_name : (prefill.person_name || '');
  const initialAmount = debt ? debt.amount : (prefill.amount || '');
  const initialDueDate = debt ? (debt.due_date ? String(debt.due_date).slice(0, 10) : '') : '';
  const initialNotes = debt ? (debt.notes || '') : (prefill.notes || '');

  modal.innerHTML = `
    <div class="modal" role="dialog" aria-modal="true" style="max-width:440px;">
      <div class="modal-head">
        <div>
          <p class="eyebrow">${isEdit ? 'PERBARUI DATA' : 'CATATAN BARU'}</p>
          <h2>${isEdit ? 'Edit Utang / Piutang' : 'Catat Utang / Piutang'}</h2>
        </div>
        <button class="close-btn" id="closeDebtModalBtn">×</button>
      </div>

      <form id="debtForm">
        <div class="type-toggle" style="margin-bottom:14px;">
          <button type="button" class="type ${initialType === 'debt' ? 'active' : ''}" id="debtTypeBtnDebt">🔴 Utang Saya</button>
          <button type="button" class="type income ${initialType === 'credit' ? 'active' : ''}" id="debtTypeBtnCredit">🟢 Piutang Saya</button>
        </div>

        <label id="debtPersonLabel">${initialType === 'debt' ? 'Pemberi Pinjaman (Nama Orang / Lembaga)' : 'Peminjam (Nama Orang)'}
          <input type="text" id="debtPersonName" value="${escapeHtml(initialName)}" placeholder="Contoh: Budi Santoso, Bank BCA" required>
        </label>

        <label>Nominal Pinjaman
          <div class="amount-input">
            <span>Rp</span>
            <input type="text" id="debtAmount" inputmode="numeric" value="${initialAmount ? String(initialAmount) : ''}" placeholder="0" required>
          </div>
        </label>

        <label>Jatuh Tempo (Opsional)
          <input type="date" id="debtDueDate" value="${initialDueDate}">
        </label>

        <label>Catatan Tambahan
          <textarea id="debtNotes" rows="2" placeholder="Keterangan keperluan atau perjanjian pinjaman">${escapeHtml(initialNotes)}</textarea>
        </label>

        <button class="primary save-btn" type="submit" style="margin-top:14px;width:100%;min-height:46px;">
          ${isEdit ? 'Simpan Perubahan ↗' : 'Simpan Catatan ↗'}
        </button>
      </form>
    </div>
  `;

  modal.classList.add('show');

  let currentType = initialType;
  const typeBtnDebt = modal.querySelector('#debtTypeBtnDebt');
  const typeBtnCredit = modal.querySelector('#debtTypeBtnCredit');
  const personLabel = modal.querySelector('#debtPersonLabel');

  const setType = (t) => {
    currentType = t;
    if (t === 'debt') {
      typeBtnDebt.classList.add('active');
      typeBtnCredit.classList.remove('active');
      if (personLabel) personLabel.firstChild.nodeValue = 'Pemberi Pinjaman (Nama Orang / Lembaga)\n';
    } else {
      typeBtnCredit.classList.add('active');
      typeBtnDebt.classList.remove('active');
      if (personLabel) personLabel.firstChild.nodeValue = 'Peminjam (Nama Orang)\n';
    }
  };

  typeBtnDebt.onclick = () => setType('debt');
  typeBtnCredit.onclick = () => setType('credit');

  modal.querySelector('#closeDebtModalBtn').onclick = () => modal.classList.remove('show');
  modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };

  const form = modal.querySelector('#debtForm');
  form.onsubmit = async (e) => {
    e.preventDefault();
    const personName = modal.querySelector('#debtPersonName')?.value.trim();
    const amountVal = parseAmount(modal.querySelector('#debtAmount')?.value || 0);
    const dueDate = modal.querySelector('#debtDueDate')?.value || null;
    const notes = modal.querySelector('#debtNotes')?.value.trim() || null;

    if (!personName) return toast('Masukkan nama orang / pihak terkait');
    if (!amountVal || amountVal <= 0) return toast('Masukkan nominal yang valid');

    const payload = {
      type: currentType,
      person_name: personName,
      amount: amountVal,
      due_date: dueDate,
      notes: notes,
    };

    try {
      if (isEdit) {
        await apiFetch(`/api/debts/${debt.id}`, {
          method: 'PUT',
          body: JSON.stringify(payload),
        });
        toast('Catatan berhasil diperbarui');
      } else {
        await apiFetch('/api/debts', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        toast(currentType === 'debt' ? 'Utang berhasil dicatat' : 'Piutang berhasil dicatat');
      }
      modal.classList.remove('show');
      if (typeof window.loadApp === 'function') await window.loadApp();
    } catch (err) {
      toast(err.message || 'Gagal menyimpan catatan');
    }
  };
}

// ============================================================
// Modal Form: Bayar / Cicil Utang & Piutang
// ============================================================
export function openPayDebtModal(debt) {
  let modal = document.getElementById('payDebtModalBackdrop');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'payDebtModalBackdrop';
    modal.className = 'modal-backdrop';
    document.body.appendChild(modal);
  }

  const state = window.state || { wallets: [] };
  const remaining = debt.remaining_amount ?? Math.max(0, debt.amount - debt.paid_amount);
  const isDebt = debt.type === 'debt';

  const walletOptions = (state.wallets || []).map(w =>
    `<option value="${w.id}">${escapeHtml(w.name)} (${money(w.balance)})</option>`
  ).join('');

  modal.innerHTML = `
    <div class="modal" role="dialog" aria-modal="true" style="max-width:420px;">
      <div class="modal-head">
        <div>
          <p class="eyebrow">${isDebt ? 'PEMBAYARAN UTANG' : 'PENERIMAAN PIUTANG'}</p>
          <h2>${isDebt ? 'Bayar / Cicil Utang' : 'Terima Cicilan Piutang'}</h2>
        </div>
        <button class="close-btn" id="closePayDebtModalBtn">×</button>
      </div>

      <div style="background:var(--panel-2);padding:12px 14px;border-radius:10px;margin-bottom:14px;border:1px solid var(--line);">
        <div style="font-size:13px;margin-bottom:4px;">Pihak Terkait: <strong>${escapeHtml(debt.person_name)}</strong></div>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:12px;color:var(--muted);">Sisa yang harus ${isDebt ? 'dibayar' : 'diterima'}:</span>
          <strong style="font-size:16px;color:${isDebt ? 'var(--red)' : 'var(--green)'};">${money(remaining)}</strong>
        </div>
      </div>

      <form id="payDebtForm">
        <label>Nominal Pembayaran
          <div class="amount-input">
            <span>Rp</span>
            <input type="text" id="payAmount" inputmode="numeric" value="${remaining}" placeholder="0" required>
          </div>
        </label>

        <label>${isDebt ? 'Potong dari Dompet' : 'Simpan ke Dompet'}
          <select id="payWalletSelect">
            <option value="">-- Tanpa Transaksi Dompet --</option>
            ${walletOptions}
          </select>
        </label>

        <label>Tanggal Pembayaran
          <input type="date" id="payDate" value="${new Date().toISOString().slice(0, 10)}" required>
        </label>

        <label>Catatan Pembayaran (Opsional)
          <input type="text" id="payNotes" placeholder="Contoh: Cicilan ke-1, Transfer BCA">
        </label>

        <button class="primary save-btn" type="submit" style="margin-top:14px;width:100%;min-height:46px;">
          ${isDebt ? 'Konfirmasi Pembayaran ↗' : 'Konfirmasi Penerimaan ↗'}
        </button>
      </form>
    </div>
  `;

  modal.classList.add('show');
  modal.querySelector('#closePayDebtModalBtn').onclick = () => modal.classList.remove('show');
  modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };

  const form = modal.querySelector('#payDebtForm');
  form.onsubmit = async (e) => {
    e.preventDefault();
    const amountVal = parseAmount(modal.querySelector('#payAmount')?.value || 0);
    const walletId = modal.querySelector('#payWalletSelect')?.value || null;
    const paymentDate = modal.querySelector('#payDate')?.value || new Date().toISOString().slice(0, 10);
    const notes = modal.querySelector('#payNotes')?.value.trim() || null;

    if (!amountVal || amountVal <= 0) return toast('Masukkan nominal yang valid');
    if (amountVal > remaining) {
      return toast(`Nominal melebihi sisa tagihan (${money(remaining)})`);
    }

    try {
      await apiFetch(`/api/debts/${debt.id}/pay`, {
        method: 'POST',
        body: JSON.stringify({
          amount: amountVal,
          wallet_id: walletId ? Number(walletId) : null,
          payment_date: paymentDate,
          notes: notes,
        }),
      });

      toast(isDebt ? 'Pembayaran utang berhasil dicatat!' : 'Penerimaan piutang berhasil dicatat!');
      modal.classList.remove('show');
      if (typeof window.loadApp === 'function') await window.loadApp();
    } catch (err) {
      toast(err.message || 'Gagal mencatat pembayaran');
    }
  };
}

// Global exposure
window.debts = debts;
window.initDebtHandlers = initDebtHandlers;
window.openDebtModal = openDebtModal;
window.openPayDebtModal = openPayDebtModal;
