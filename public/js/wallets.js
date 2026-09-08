// public/js/wallets.js - Wallets Management and Page Renderer

import { escapeHtml, money, toast, showConfirmModal } from './utils.js';
import { apiFetch } from './api.js';

export function wallets() {
  const state = window.state || { wallets: [] };
  const emptyHtml = `
    <div class="empty-state-card" style="grid-column: 1 / -1;padding:40px 20px;text-align:center;">
      <div class="empty-icon" style="font-size:36px;margin-bottom:8px;">◻</div>
      <h3 style="margin:0 0 6px;">Belum Ada Dompet</h3>
      <p class="muted" style="margin:0 0 16px;">Kelola berbagai rekening bank, e-wallet, atau uang tunai dalam satu tempat dengan saldo otomatis.</p>
      <button class="primary" id="addWalletEmpty" style="margin:auto;">＋ Buat Dompet Pertama</button>
    </div>
  `;

  return `
    <div class="page-heading">
      <div>
        <p class="eyebrow">WHERE YOUR MONEY LIVES</p>
        <h1 class="page-title">Wallets</h1>
        <p class="muted">Saldo berjalan dikalkulasi langsung dari seluruh riwayat transaksi.</p>
      </div>
      <button class="primary" id="addWallet">+ Add wallet</button>
    </div>
    <div class="metric-grid">
      ${(state.wallets || []).map((w, i) => `
        <div class="card wallet-item">
          <div class="section-head">
            <h2>${escapeHtml(w.name)}</h2>
            <span class="trend">Active</span>
          </div>
          <div class="stat-value">${money(w.balance)}</div>
          <div class="wallet-meta">
            <small>Opening balance</small>
            <small>${money(w.opening)}</small>
          </div>
          <div class="wallet-actions">
            <button class="period wallet-edit" data-wallet="${i}">Edit</button>
            <button class="period wallet-delete" data-wallet="${i}">Delete</button>
          </div>
        </div>
      `).join('') || emptyHtml}
    </div>
  `;
}

export function initWalletHandlers() {
  const state = window.state || { user: {}, wallets: [] };

  const handleAddWallet = async () => {
    const isId = state.user?.language === 'id';
    const name = prompt(isId ? 'Nama dompet baru (contoh: BCA, Mandiri, Cash, GoPay):' : 'Wallet name:');
    if (!name || !name.trim()) return;
    try {
      await apiFetch('/api/wallets', {
        method: 'POST',
        body: JSON.stringify({ name: name.trim(), opening_balance: 0 }),
      });
      toast(isId ? 'Dompet berhasil ditambahkan' : 'Wallet added');
      if (typeof window.loadApp === 'function') await window.loadApp();
    } catch (err) {
      toast(err.message || (isId ? 'Gagal menambah dompet' : 'Failed to add wallet'));
    }
  };

  // Tambah Wallet via API
  const addWallet = document.getElementById('addWallet');
  if (addWallet) addWallet.onclick = handleAddWallet;

  const addWalletEmpty = document.getElementById('addWalletEmpty');
  if (addWalletEmpty) addWalletEmpty.onclick = handleAddWallet;

  // Hapus Wallet via API dengan modal touch-friendly
  document.querySelectorAll('.wallet-delete').forEach(b => {
    b.onclick = async () => {
      const i = Number(b.dataset.wallet);
      const w = state.wallets[i];
      if (!w) return;
      const isId = state.user?.language === 'id';

      const confirmed = await showConfirmModal({
        title: isId ? 'Hapus Dompet' : 'Delete Wallet',
        message: `${w.name}\n${isId ? `Saldo saat ini: ${money(w.balance)}` : `Current balance: ${money(w.balance)}`}\n\n${isId ? 'Hapus dompet ini? Dompet hanya dapat dihapus jika tidak memiliki transaksi terkait.' : 'Delete this wallet? Wallets with recorded transactions cannot be deleted.'}`,
        confirmText: isId ? 'Ya, Hapus Dompet' : 'Delete Wallet',
        cancelText: isId ? 'Batal' : 'Cancel',
        isDanger: true,
      });

      if (confirmed) {
        try {
          await apiFetch(`/api/wallets/${w.id}`, { method: 'DELETE' });
          toast(isId ? 'Dompet berhasil dihapus' : 'Wallet deleted');
          if (typeof window.loadApp === 'function') await window.loadApp();
        } catch (err) {
          toast(err.message || (isId ? 'Gagal menghapus dompet' : 'Failed to delete wallet'));
        }
      }
    };
  });

  // Edit Wallet via API
  document.querySelectorAll('.wallet-edit').forEach(b => {
    b.onclick = async () => {
      const i = Number(b.dataset.wallet);
      const w = state.wallets[i];
      if (!w) return;
      const isId = state.user?.language === 'id';
      const name = prompt(isId ? 'Nama dompet baru:' : 'New wallet name:', w.name);
      if (name && name.trim()) {
        try {
          await apiFetch(`/api/wallets/${w.id}`, {
            method: 'PUT',
            body: JSON.stringify({ name: name.trim() }),
          });
          toast(isId ? 'Dompet diperbarui' : 'Wallet updated');
          if (typeof window.loadApp === 'function') await window.loadApp();
        } catch (err) {
          toast(err.message || (isId ? 'Gagal mengedit dompet' : 'Failed to edit wallet'));
        }
      }
    };
  });
}

// Expose on window
window.wallets = wallets;
window.initWalletHandlers = initWalletHandlers;
