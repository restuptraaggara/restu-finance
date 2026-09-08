// public/js/recurring.js - Recurring Transactions Management and Page Renderer

import { escapeHtml, money, parseAmount, dateText, toast } from './utils.js';
import { apiFetch } from './api.js';

export function recurring() {
  const state = window.state || { recurringTransactions: [] };
  const emptyHtml = '<div class="empty">Belum ada recurring transaction. Tambahkan jadwal transaksi rutin.</div>';

  return `
    <div class="page-heading">
      <div>
        <p class="eyebrow">AUTOMATED CASH FLOW</p>
        <h1 class="page-title">Recurring Transactions</h1>
        <p class="muted">Jadwal transaksi berulang yang otomatis dicatat sesuai jadwal.</p>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="period" id="processRecurringBtn">⚡ Process due</button>
        <button class="primary" id="addRecurringBtn">+ Add recurring</button>
      </div>
    </div>
    <div class="metric-grid">
      ${(state.recurringTransactions || []).map(r => `
        <div class="card recurring-item">
          <div class="section-head">
            <h2>${escapeHtml(r.description || r.category)}</h2>
            <span class="trend ${r.is_active ? '' : 'down'}">${r.is_active ? 'Active' : 'Inactive'}</span>
          </div>
          <div class="stat-value ${r.type === 'income' ? 'income-text' : 'expense-text'}">
            ${r.type === 'income' ? '+' : '−'} ${money(r.amount)}
          </div>
          <div class="wallet-meta">
            <small>Frequency: <b>${r.frequency}</b></small>
            <small>Next run: <b>${dateText(r.next_date)}</b></small>
          </div>
          <div class="wallet-meta">
            <small>Wallet: ${escapeHtml(r.wallet)}</small>
            <small>Category: ${escapeHtml(r.category)}</small>
          </div>
          ${r.end_date ? `<div class="wallet-meta"><small>End date: ${dateText(r.end_date)}</small></div>` : ''}
          <div class="wallet-actions">
            <button class="period recurring-toggle" data-id="${r.id}">${r.is_active ? 'Deactivate' : 'Activate'}</button>
            <button class="period recurring-delete" data-id="${r.id}">Delete</button>
          </div>
        </div>
      `).join('') || emptyHtml}
    </div>
  `;
}

export function initRecurringHandlers() {
  const state = window.state || { user: {}, wallets: [], categories: [], recurringTransactions: [] };

  // Tambah Recurring Transaction via API
  const addRecurringBtn = document.getElementById('addRecurringBtn');
  if (addRecurringBtn) {
    addRecurringBtn.onclick = async () => {
      const isId = state.user?.language === 'id';
      const desc = prompt(isId ? 'Deskripsi / nama transaksi rutin:' : 'Recurring description:');
      if (!desc || !desc.trim()) return;
      const amount = parseAmount(prompt(isId ? 'Nominal transaksi:' : 'Amount:'));
      if (!amount || amount <= 0) return;
      const type = prompt(isId ? 'Tipe (expense / income):' : 'Type (expense / income):', 'expense');
      if (type !== 'income' && type !== 'expense') return;
      const freq = prompt(isId ? 'Frekuensi (daily / weekly / monthly / yearly):' : 'Frequency (daily / weekly / monthly / yearly):', 'monthly');
      if (!['daily', 'weekly', 'monthly', 'yearly'].includes(freq)) return;
      const nextDate = prompt(isId ? 'Tanggal mulai / next run (YYYY-MM-DD):' : 'Next run date (YYYY-MM-DD):', new Date().toISOString().slice(0, 10));
      if (!nextDate) return;
      const walletName = state.wallets[0]?.name || 'Utama';
      const categoryName = type === 'income' ? 'Pemasukan' : (state.categories[0] || 'Lainnya');

      try {
        await apiFetch('/api/recurring-transactions', {
          method: 'POST',
          body: JSON.stringify({
            wallet: walletName,
            category: categoryName,
            type,
            amount,
            description: desc.trim(),
            frequency: freq,
            start_date: nextDate,
            next_date: nextDate,
            is_active: true,
          }),
        });
        toast(isId ? 'Recurring transaction berhasil dibuat' : 'Recurring transaction created');
        if (typeof window.loadApp === 'function') await window.loadApp();
      } catch (err) {
        toast(err.message || (isId ? 'Gagal membuat recurring transaction' : 'Failed to create recurring transaction'));
      }
    };
  }

  // Process Due Recurring Manual Button
  const processRecurringBtn = document.getElementById('processRecurringBtn');
  if (processRecurringBtn) {
    processRecurringBtn.onclick = async () => {
      const isId = state.user?.language === 'id';
      try {
        const res = await apiFetch('/api/recurring-transactions/process', { method: 'POST' });
        const count = res?.data?.processed || 0;
        toast(isId ? `Diproses: ${count} transaksi` : `Processed: ${count} transactions`);
        if (typeof window.loadApp === 'function') await window.loadApp();
      } catch (err) {
        toast(err.message || (isId ? 'Gagal memproses transaksi' : 'Failed to process transactions'));
      }
    };
  }

  // Toggle Active/Inactive Recurring
  document.querySelectorAll('.recurring-toggle').forEach(b => {
    b.onclick = async () => {
      const isId = state.user?.language === 'id';
      const id = Number(b.dataset.id);
      const r = (state.recurringTransactions || []).find(x => x.id === id);
      if (!r) return;
      try {
        await apiFetch(`/api/recurring-transactions/${id}`, {
          method: 'PUT',
          body: JSON.stringify({ is_active: !r.is_active }),
        });
        toast(isId ? 'Status diperbarui' : 'Status updated');
        if (typeof window.loadApp === 'function') await window.loadApp();
      } catch (err) {
        toast(err.message || (isId ? 'Gagal mengubah status' : 'Failed to change status'));
      }
    };
  });

  // Hapus Recurring Transaction
  document.querySelectorAll('.recurring-delete').forEach(b => {
    b.onclick = async () => {
      const isId = state.user?.language === 'id';
      const id = Number(b.dataset.id);
      const r = (state.recurringTransactions || []).find(x => x.id === id);
      if (!r) return;
      const confirmMsg = isId ? `Hapus recurring "${r.description || r.category}"?` : `Delete recurring "${r.description || r.category}"?`;
      if (confirm(confirmMsg)) {
        try {
          await apiFetch(`/api/recurring-transactions/${id}`, { method: 'DELETE' });
          toast(isId ? 'Recurring transaction dihapus' : 'Recurring transaction deleted');
          if (typeof window.loadApp === 'function') await window.loadApp();
        } catch (err) {
          toast(err.message || (isId ? 'Gagal menghapus recurring transaction' : 'Failed to delete recurring transaction'));
        }
      }
    };
  });
}

// Expose on window
window.recurring = recurring;
window.initRecurringHandlers = initRecurringHandlers;
