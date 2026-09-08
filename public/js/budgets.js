// public/js/budgets.js - Budget Management and Page Renderer

import { escapeHtml, money, parseAmount, total, monthTx, icon, toast, showConfirmModal } from './utils.js';
import { apiFetch } from './api.js';

export function budget() {
  const state = window.state || { budgets: [] };
  const budgetTotal = (state.budgets || []).reduce((sum, b) => sum + (b.limit || 0), 0);
  const spent = (state.budgets || []).reduce((sum, b) => sum + (b.used || 0), 0) || total(monthTx(), 'expense');

  const emptyHtml = `
    <div class="empty-state-card" style="grid-column: 1 / -1;padding:40px 20px;text-align:center;">
      <div class="empty-icon" style="font-size:36px;margin-bottom:8px;">◫</div>
      <h3 style="margin:0 0 6px;">Belum Ada Batas Anggaran</h3>
      <p class="muted" style="margin:0 0 16px;">Tentukan batas pengeluaran bulanan per kategori (misal: Makanan Rp 1.500.000) agar keuanganmu tidak over-budget.</p>
      <button class="primary" id="addBudgetEmpty" style="margin:auto;">＋ Atur Anggaran Pertama</button>
    </div>
  `;

  return `
    <div class="page-heading">
      <div>
        <p class="eyebrow">SPEND WITH INTENTION</p>
        <h1 class="page-title">Monthly budget</h1>
        <p class="muted">${money(spent)} sudah digunakan bulan ini</p>
      </div>
      <button class="primary" id="addBudget">+ Add budget</button>
    </div>
    <div class="budget-overview">
      <div>
        <small>Total budget bulan ini</small>
        <strong>${money(budgetTotal)}</strong>
      </div>
      <div>
        <small>Sudah digunakan</small>
        <strong>${money(spent)}</strong>
      </div>
      <div>
        <small>Sisa budget</small>
        <strong>${money(Math.max(0, budgetTotal - spent))}</strong>
      </div>
    </div>
    <div class="metric-grid">
      ${(state.budgets || []).map(b => {
        const used = b.used ?? total(monthTx().filter(t => t.category === b.category), 'expense');
        const pct = b.limit > 0 ? (used / b.limit * 100) : 0;
        return `
          <div class="card budget-item" data-id="${b.id}">
            <div class="budget-meta">
              <strong>${icon(b.category)} &nbsp;${escapeHtml(b.category)}</strong>
              <small>${money(used)} / ${money(b.limit)}</small>
            </div>
            <div class="wide-progress">
              <i class="${pct >= 100 ? 'over' : pct >= 75 ? 'warn' : ''}" style="width:${Math.min(pct, 100)}%"></i>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
              <small class="muted">${pct >= 100 ? '⚠️ Melebihi anggaran' : pct >= 75 ? 'Mendekati batas' : Math.round(pct) + '% terpakai'}</small>
              <button type="button" class="icon-btn delete-budget-btn" data-delete-id="${b.id}" title="Hapus anggaran" style="font-size:11px;color:var(--red);opacity:0.8;">🗑 Hapus</button>
            </div>
          </div>
        `;
      }).join('') || emptyHtml}
    </div>
  `;
}

export function initBudgetHandlers() {
  const state = window.state || { user: {}, budgets: [] };

  const handleAddBudget = async () => {
    const isId = state.user?.language === 'id';
    const category = prompt(isId ? 'Nama kategori anggaran (contoh: Makanan, Transportasi):' : 'Budget category:');
    if (!category || !category.trim()) return;
    const limit = parseAmount(prompt(isId ? 'Batas nominal anggaran (Rp):' : 'Budget limit:'));
    if (limit > 0) {
      try {
        await apiFetch('/api/budgets', {
          method: 'POST',
          body: JSON.stringify({ category: category.trim(), limit_amount: limit }),
        });
        toast(isId ? 'Anggaran berhasil disimpan' : 'Budget saved');
        if (typeof window.loadApp === 'function') await window.loadApp();
      } catch (err) {
        toast(err.message || (isId ? 'Gagal menyimpan anggaran' : 'Failed to save budget'));
      }
    }
  };

  const addBudget = document.getElementById('addBudget');
  if (addBudget) addBudget.onclick = handleAddBudget;

  const addBudgetEmpty = document.getElementById('addBudgetEmpty');
  if (addBudgetEmpty) addBudgetEmpty.onclick = handleAddBudget;

  // Hapus Budget via API dengan touch-friendly modal
  document.querySelectorAll('.delete-budget-btn').forEach(btn => {
    btn.onclick = async (e) => {
      e.stopPropagation();
      const budgetObj = (state.budgets || []).find(b => b.id == btn.dataset.deleteId);
      if (!budgetObj) return;

      const confirmed = await showConfirmModal({
        title: 'Hapus Anggaran',
        message: `Hapus target anggaran kategori "${budgetObj.category}" sebesar ${money(budgetObj.limit)}?\nRiwayat transaksi yang sudah ada tidak akan terpengaruh.`,
        confirmText: 'Ya, Hapus Anggaran',
        cancelText: 'Batal',
        isDanger: true,
      });

      if (confirmed) {
        try {
          await apiFetch(`/api/budgets/${budgetObj.id}`, { method: 'DELETE' });
          toast('Anggaran berhasil dihapus');
          if (typeof window.loadApp === 'function') await window.loadApp();
        } catch (err) {
          toast(err.message || 'Gagal menghapus anggaran');
        }
      }
    };
  });
}

// Expose on window
window.budget = budget;
window.initBudgetHandlers = initBudgetHandlers;
