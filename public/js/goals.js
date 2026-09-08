// public/js/goals.js - Savings Goals Management, Celebration Modal, and Page Renderer

import { escapeHtml, money, parseAmount, dateText, toast, showConfirmModal } from './utils.js';
import { apiFetch } from './api.js';
import { playRetroBeep } from './audio.js';

export function showGoalCelebrationModal(goal) {
  let modal = document.getElementById('goalCelebrationModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'goalCelebrationModal';
    modal.className = 'modal-backdrop';
    document.body.appendChild(modal);
  }

  // Generate CSS confetti particles
  const colors = ['#00ff41', '#ff0055', '#ffe600', '#00e5ff', '#ff00d4', '#ffffff'];
  let confettiHtml = '';
  for (let i = 0; i < 35; i++) {
    const color = colors[i % colors.length];
    const left = Math.random() * 100;
    const delay = (Math.random() * 1.5).toFixed(2);
    const duration = (2 + Math.random() * 2).toFixed(2);
    const size = Math.floor(6 + Math.random() * 8);
    confettiHtml += `<div class="confetti-piece" style="left:${left}%;background:${color};width:${size}px;height:${size * 1.6}px;animation-delay:${delay}s;animation-duration:${duration}s;"></div>`;
  }

  modal.innerHTML = `
    <div class="modal celebration-modal" role="dialog" aria-modal="true" style="max-width:440px;text-align:center;overflow:hidden;position:relative;">
      <div class="confetti-container" aria-hidden="true">${confettiHtml}</div>
      <div style="font-size:52px;margin:10px 0 6px;">🏆</div>
      <p class="eyebrow" style="color:var(--green, #00ff41);font-weight:700;letter-spacing:1.5px;">TARGET TERCAPAI 100%</p>
      <h2 style="font-size:24px;margin:8px 0 12px;color:var(--text, #fff);">Selamat!</h2>
      <p style="font-size:14px;line-height:1.6;color:var(--text);margin-bottom:18px;">
        Target tabungan <strong>"${escapeHtml(goal.name)}"</strong> telah berhasil terkumpul!
        <br>
        <span style="font-size:18px;font-weight:700;color:var(--green, #00ff41);display:inline-block;margin-top:6px;">
          ${money(goal.saved || goal.saved_amount || 0)} / ${money(goal.target || goal.target_amount || 0)}
        </span>
      </p>
      <button class="primary" id="closeCelebrationBtn" style="padding:12px 28px;font-size:14px;border-radius:8px;cursor:pointer;width:100%;min-height:46px;">
        Luar Biasa! Lanjutkan Menabung 🚀
      </button>
    </div>
  `;

  modal.classList.add('show');

  // Play retro chiptune victory jingle if Special Mode is active
  if (document.body.classList.contains('theme-special') || document.body.classList.contains('special-mode') || window.state?.user?.theme === 'special' || document.body.dataset.theme === 'special') {
    playRetroBeep('victory');
  }

  const closeBtn = modal.querySelector('#closeCelebrationBtn');
  if (closeBtn) {
    closeBtn.onclick = () => modal.classList.remove('show');
  }
  modal.onclick = (e) => {
    if (e.target === modal) modal.classList.remove('show');
  };
}

export function goals() {
  const state = window.state || { goals: [] };
  const emptyHtml = `
    <div class="empty-state-card" style="grid-column: 1 / -1;">
      <div class="empty-icon">◎</div>
      <h3>Belum Ada Target Tabungan</h3>
      <p class="muted">Tetapkan target tabungan impianmu (misal: Liburan, Gadget Baru, Dana Darurat) dan pantau progresnya hingga 100%.</p>
      <button class="primary" id="addGoalEmpty" style="margin-top:12px;">+ Buat Target Tabungan Pertama</button>
    </div>
  `;

  // Check for auto-celebration
  setTimeout(() => {
    (state.goals || []).forEach(g => {
      const target = g.target || g.target_amount || 0;
      const saved = g.saved || g.saved_amount || 0;
      const pct = target > 0 ? (saved / target * 100) : 0;
      const sessionKey = 'celebrated_goal_' + g.id;
      if (pct >= 100 && !sessionStorage.getItem(sessionKey)) {
        sessionStorage.setItem(sessionKey, 'true');
        showGoalCelebrationModal(g);
      }
    });
  }, 400);

  return `
    <div class="page-heading">
      <div>
        <p class="eyebrow">YOUR NEXT MILESTONE</p>
        <h1 class="page-title">Savings goals</h1>
        <p class="muted">Langkah teratur, target tabungan tercapai tepat waktu.</p>
      </div>
      <button class="primary" id="addGoal">+ New goal</button>
    </div>
    <div class="metric-grid">
      ${(state.goals || []).map(g => {
        const target = g.target || g.target_amount || 0;
        const saved = g.saved || g.saved_amount || 0;
        const pct = Math.round(target > 0 ? (saved / target * 100) : 0);
        const isAchieved = pct >= 100;

        return `
          <div class="card goal-item ${isAchieved ? 'goal-achieved-card' : ''}" data-id="${g.id}">
            <div class="section-head">
              <h2>${escapeHtml(g.name)}</h2>
              <span class="trend ${isAchieved ? 'trend-up' : ''}">${pct}%</span>
            </div>
            <div class="stat-value">${money(saved)} <small class="muted">/ ${money(target)}</small></div>
            <div class="wide-progress">
              <i style="width:${Math.min(100, pct)}%;${isAchieved ? 'background:var(--green, #00ff41);box-shadow:0 0 10px rgba(0,255,65,0.4);' : ''}"></i>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
              <p class="muted" style="margin:0;font-size:12px;">Deadline ${dateText(g.deadline)}</p>
              ${isAchieved ? `
                <button type="button" class="celebrate-trigger-badge" data-goal-id="${g.id}" title="Buka perayaan target tercapai">
                  🎉 TERCAPAI 100%
                </button>
              ` : ''}
            </div>
            <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end;">
              <button type="button" class="icon-btn delete-goal-btn" data-delete-id="${g.id}" title="Hapus target tabungan" style="font-size:11px;color:var(--red);opacity:0.8;">🗑 Hapus</button>
            </div>
          </div>
        `;
      }).join('') || emptyHtml}
    </div>
  `;
}

export function initGoalHandlers() {
  const state = window.state || { user: {}, goals: [] };

  const handleCreate = async () => {
    const isId = state.user?.language === 'id';
    const name = prompt(isId ? 'Nama target tabungan:' : 'Goal name:');
    if (!name || !name.trim()) return;
    const target = parseAmount(prompt(isId ? 'Nominal target (Rp):' : 'Target amount:'));
    const deadline = prompt(isId ? 'Tenggat waktu (YYYY-MM-DD):' : 'Deadline (YYYY-MM-DD):', new Date().toISOString().slice(0, 10));
    if (target > 0 && deadline) {
      try {
        await apiFetch('/api/goals', {
          method: 'POST',
          body: JSON.stringify({ name: name.trim(), target_amount: target, saved_amount: 0, deadline }),
        });
        toast(isId ? 'Target tabungan berhasil dibuat' : 'Goal created');
        if (typeof window.loadApp === 'function') await window.loadApp();
      } catch (err) {
        toast(err.message || (isId ? 'Gagal membuat target tabungan' : 'Failed to create goal'));
      }
    }
  };

  const addGoal = document.getElementById('addGoal');
  if (addGoal) addGoal.onclick = handleCreate;

  const addGoalEmpty = document.getElementById('addGoalEmpty');
  if (addGoalEmpty) addGoalEmpty.onclick = handleCreate;

  // Celebrate trigger click
  document.querySelectorAll('.celebrate-trigger-badge').forEach(badge => {
    badge.onclick = (e) => {
      e.stopPropagation();
      const goal = (state.goals || []).find(g => g.id == badge.dataset.goalId);
      if (goal) showGoalCelebrationModal(goal);
    };
  });

  // Delete goal buttons
  document.querySelectorAll('.delete-goal-btn').forEach(btn => {
    btn.onclick = async (e) => {
      e.stopPropagation();
      const goal = (state.goals || []).find(g => g.id == btn.dataset.deleteId);
      if (!goal) return;

      const confirmed = await showConfirmModal({
        title: 'Hapus Target Tabungan',
        message: `Hapus target tabungan "${goal.name}"?\nRiwayat transaksi Anda yang telah tercatat tetap aman.`,
        confirmText: 'Ya, Hapus Target',
        cancelText: 'Batal',
        isDanger: true,
      });

      if (confirmed) {
        try {
          await apiFetch(`/api/goals/${goal.id}`, { method: 'DELETE' });
          toast('Target tabungan berhasil dihapus');
          if (typeof window.loadApp === 'function') await window.loadApp();
        } catch (err) {
          toast(err.message || 'Gagal menghapus target tabungan');
        }
      }
    };
  });
}

// Expose on window
window.goals = goals;
window.initGoalHandlers = initGoalHandlers;
window.showGoalCelebrationModal = showGoalCelebrationModal;
