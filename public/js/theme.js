// public/js/theme.js - Theme Engine (Light, Dark, Special Mode), Dynamic Favicon, and Mascot

import { playRetroBeep, unlockAudio, updateMusicBtn } from './audio.js';
import { money, total, monthTx, saveLocalPreferences } from './utils.js';
import { apiFetch } from './api.js';

let specialModeCanvas = null;
let specialModeAnimId = null;

// ============================================================
// RESTU PIXEL MASCOT COMPANION (Special Mode Interactive Character)
// ============================================================
export const PIXEL_CHARACTERS = [
  {
    id: 'knight',
    name: 'HERO RESTU (LV.99)',
    title: 'Ksatria Dompet',
    icon: '⚔️',
    svg: `<svg viewBox="0 0 16 16" width="64" height="64" style="image-rendering:pixelated;shape-rendering:crispEdges;">
      <rect x="3" y="0" width="5" height="1" fill="#e63946"/>
      <rect x="2" y="1" width="7" height="1" fill="#e63946"/>
      <rect x="3" y="2" width="5" height="1" fill="#000000"/>
      <rect x="2" y="3" width="1" height="6" fill="#000000"/>
      <rect x="3" y="3" width="5" height="1" fill="#b8c5d6"/>
      <rect x="8" y="3" width="1" height="6" fill="#000000"/>
      <rect x="3" y="4" width="5" height="1" fill="#ffffff"/>
      <rect x="10" y="4" width="3" height="1" fill="#000000"/>
      <rect x="11" y="4" width="1" height="6" fill="#ffea00"/>
      <rect x="3" y="5" width="5" height="1" fill="#ffcc99"/>
      <rect x="4" y="5" width="1" height="1" fill="#000000"/>
      <rect x="6" y="5" width="1" height="1" fill="#000000"/>
      <rect x="3" y="6" width="5" height="2" fill="#ffcc99"/>
      <rect x="3" y="8" width="5" height="1" fill="#b8c5d6"/>
      <rect x="2" y="9" width="7" height="4" fill="#3a78c4"/>
      <rect x="1" y="9" width="1" height="4" fill="#000000"/>
      <rect x="9" y="9" width="1" height="4" fill="#000000"/>
      <rect x="4" y="11" width="3" height="2" fill="#ffcc00"/>
      <rect x="10" y="10" width="3" height="3" fill="#ffcc00"/>
      <rect x="3" y="13" width="2" height="2" fill="#b8c5d6"/>
      <rect x="6" y="13" width="2" height="2" fill="#b8c5d6"/>
      <rect x="2" y="15" width="3" height="1" fill="#000000"/>
      <rect x="6" y="15" width="3" height="1" fill="#000000"/>
    </svg>`
  },
  {
    id: 'mario',
    name: 'SUPER RESTU (LV.88)',
    title: 'Pixel Plumber',
    icon: '🍄',
    svg: `<svg viewBox="0 0 16 16" width="64" height="64" style="image-rendering:pixelated;shape-rendering:crispEdges;">
      <rect x="5" y="0" width="5" height="1" fill="#e52521"/>
      <rect x="4" y="1" width="9" height="1" fill="#e52521"/>
      <rect x="4" y="2" width="3" height="1" fill="#6b4724"/>
      <rect x="7" y="2" width="3" height="1" fill="#fccc98"/>
      <rect x="10" y="2" width="1" height="1" fill="#6b4724"/>
      <rect x="3" y="3" width="1" height="3" fill="#6b4724"/>
      <rect x="4" y="3" width="1" height="1" fill="#000000"/>
      <rect x="5" y="3" width="1" height="1" fill="#fccc98"/>
      <rect x="6" y="3" width="1" height="1" fill="#000000"/>
      <rect x="7" y="3" width="3" height="1" fill="#fccc98"/>
      <rect x="10" y="3" width="1" height="1" fill="#6b4724"/>
      <rect x="11" y="3" width="1" height="1" fill="#fccc98"/>
      <rect x="4" y="4" width="1" height="1" fill="#000000"/>
      <rect x="5" y="4" width="1" height="1" fill="#6b4724"/>
      <rect x="6" y="4" width="4" height="1" fill="#fccc98"/>
      <rect x="10" y="4" width="1" height="1" fill="#6b4724"/>
      <rect x="4" y="5" width="2" height="1" fill="#6b4724"/>
      <rect x="6" y="5" width="4" height="1" fill="#fccc98"/>
      <rect x="10" y="5" width="4" height="1" fill="#6b4724"/>
      <rect x="4" y="6" width="8" height="1" fill="#fccc98"/>
      <rect x="3" y="7" width="3" height="1" fill="#e52521"/>
      <rect x="6" y="7" width="1" height="1" fill="#6b4724"/>
      <rect x="7" y="7" width="3" height="1" fill="#e52521"/>
      <rect x="2" y="8" width="5" height="1" fill="#e52521"/>
      <rect x="7" y="8" width="1" height="1" fill="#6b4724"/>
      <rect x="8" y="8" width="5" height="1" fill="#e52521"/>
      <rect x="1" y="9" width="6" height="1" fill="#e52521"/>
      <rect x="7" y="9" width="1" height="1" fill="#6b4724"/>
      <rect x="8" y="9" width="6" height="1" fill="#e52521"/>
      <rect x="1" y="10" width="2" height="3" fill="#fccc98"/>
      <rect x="3" y="10" width="1" height="1" fill="#e52521"/>
      <rect x="4" y="10" width="3" height="3" fill="#0026a3"/>
      <rect x="7" y="10" width="2" height="1" fill="#fbd000"/>
      <rect x="9" y="10" width="1" height="1" fill="#6b4724"/>
      <rect x="10" y="10" width="2" height="3" fill="#0026a3"/>
      <rect x="12" y="10" width="1" height="1" fill="#e52521"/>
      <rect x="13" y="10" width="2" height="3" fill="#fccc98"/>
      <rect x="4" y="13" width="3" height="1" fill="#0026a3"/>
      <rect x="9" y="13" width="3" height="1" fill="#0026a3"/>
      <rect x="3" y="14" width="3" height="2" fill="#6b4724"/>
      <rect x="10" y="14" width="3" height="2" fill="#6b4724"/>
    </svg>`
  },
  {
    id: 'ghost',
    name: 'GHOST INKY (LV.77)',
    title: 'Hantu Penjaga Saldo',
    icon: '👻',
    svg: `<svg viewBox="0 0 16 16" width="64" height="64" style="image-rendering:pixelated;shape-rendering:crispEdges;">
      <rect x="5" y="0" width="6" height="1" fill="#000000"/>
      <rect x="3" y="1" width="10" height="1" fill="#00ffff"/>
      <rect x="2" y="2" width="12" height="1" fill="#00ffff"/>
      <rect x="1" y="3" width="14" height="1" fill="#00ffff"/>
      <rect x="1" y="4" width="14" height="8" fill="#00ffff"/>
      <rect x="3" y="4" width="3" height="4" fill="#ffffff"/>
      <rect x="10" y="4" width="3" height="4" fill="#ffffff"/>
      <rect x="4" y="5" width="2" height="2" fill="#0000cc"/>
      <rect x="11" y="5" width="2" height="2" fill="#0000cc"/>
      <rect x="1" y="12" width="3" height="3" fill="#00ffff"/>
      <rect x="5" y="12" width="2" height="2" fill="#00ffff"/>
      <rect x="9" y="12" width="2" height="2" fill="#00ffff"/>
      <rect x="12" y="12" width="3" height="3" fill="#00ffff"/>
    </svg>`
  },
  {
    id: 'money',
    name: 'FLYING CASH (LV.99)',
    title: 'Uang Bersayap',
    icon: '💸',
    svg: `<svg viewBox="0 0 16 16" width="64" height="64" style="image-rendering:pixelated;shape-rendering:crispEdges;">
      <rect x="4" y="0" width="2" height="4" fill="#ffffff"/>
      <rect x="10" y="0" width="2" height="4" fill="#ffffff"/>
      <rect x="2" y="2" width="4" height="3" fill="#c8b6ff"/>
      <rect x="10" y="2" width="4" height="3" fill="#c8b6ff"/>
      <rect x="1" y="4" width="14" height="8" fill="#2dc653"/>
      <rect x="0" y="4" width="16" height="8" fill="none" stroke="#140028" stroke-width="1"/>
      <rect x="6" y="6" width="4" height="4" fill="#ffea00"/>
      <rect x="7" y="7" width="2" height="2" fill="#140028"/>
    </svg>`
  },
  {
    id: 'alien',
    name: 'INVADER (LV.50)',
    title: 'Pixel Arcade Alien',
    icon: '👾',
    svg: `<svg viewBox="0 0 16 16" width="64" height="64" style="image-rendering:pixelated;shape-rendering:crispEdges;">
      <rect x="3" y="1" width="1" height="3" fill="#00ff41"/>
      <rect x="12" y="1" width="1" height="3" fill="#00ff41"/>
      <rect x="4" y="2" width="1" height="2" fill="#00ff41"/>
      <rect x="11" y="2" width="1" height="2" fill="#00ff41"/>
      <rect x="3" y="4" width="10" height="4" fill="#00ff41"/>
      <rect x="2" y="5" width="12" height="3" fill="#00ff41"/>
      <rect x="1" y="6" width="14" height="3" fill="#00ff41"/>
      <rect x="4" y="5" width="2" height="2" fill="#0d001f"/>
      <rect x="10" y="5" width="2" height="2" fill="#0d001f"/>
      <rect x="1" y="9" width="2" height="2" fill="#00ff41"/>
      <rect x="13" y="9" width="2" height="2" fill="#00ff41"/>
      <rect x="4" y="9" width="8" height="2" fill="#00ff41"/>
      <rect x="5" y="11" width="2" height="2" fill="#00ff41"/>
      <rect x="9" y="11" width="2" height="2" fill="#00ff41"/>
      <rect x="3" y="13" width="2" height="2" fill="#00ff41"/>
      <rect x="11" y="13" width="2" height="2" fill="#00ff41"/>
    </svg>`
  }
];

export let mascotCharIndex = 0;
export let mascotDialogueIndex = 0;
export let mascotTypeInterval = null;

export function spawnPixelParticle(text) {
  const mascotWrap = document.getElementById('pixelMascotCharWrap');
  if (!mascotWrap) return;
  const particle = document.createElement('div');
  particle.className = 'pixel-floating-particle';
  particle.textContent = text;
  mascotWrap.appendChild(particle);
  setTimeout(() => particle.remove(), 1200);
}

export function getMascotDialogues() {
  const state = window.state || {};
  const tx = monthTx();
  const income = state.summary ? state.summary.month_income : total(tx, 'income');
  const expense = state.summary ? state.summary.month_expense : total(tx, 'expense');
  const balance = state.summary ? state.summary.total_balance : ((state.wallets || []).reduce((s, w) => s + (w.balance || 0), 0));
  const en = state.user?.language === 'en';

  if (en) {
    return [
      `Greetings Boss! Current Balance: ${money(balance)}. Your money is safe! 🛡️`,
      `EXP +100! Recording every transaction keeps your financial level at MAX! 🎮`,
      income >= expense 
        ? `✦ COMBO SAVER! Monthly cashflow is SURPLUS (+${money(income - expense)})! Awesome! 🚀`
        : `⚠️ WARNING! Expenses exceed income by ${money(expense - income)}. Time to save up! 🛡️`,
      `BGM is active! Tap [♪ BGM] on top right to change song or seek timeline! 🎵`,
      `Hero Wisdom: Always fund your Goals before buying impulse loot! 🪙`,
      `Tap me again to switch my retro pixel skin! 🎭`
    ];
  } else {
    return [
      `Halo Boss! Saldo saat ini ${money(balance)}. Keuanganmu aman terjaga! 🛡️`,
      `EXP +100! Rutin mencatat transaksi bikin level keuanganmu naik terus! 🎮`,
      income >= expense 
        ? `✦ COMBO SAVER! Arus kas bulan ini SURPLUS (+${money(income - expense)})! Mantap! 🚀`
        : `⚠️ PERINGATAN! Pengeluaran melebihi pemasukan sebesar ${money(expense - income)}. Waktunya berhemat! 🛡️`,
      `BGM aktif! Klik tombol [♪ BGM] di kanan atas buat ganti lagu atau geser timeline! 🎵`,
      `Petuah Ksatria: Utamakan kebutuhan sebelum belanja barang diskon! 🪙`,
      `Klik aku lagi untuk ganti kostum & karakter pixel-ku! 🎭`
    ];
  }
}

export function showMascotSpeech(customMsg = null) {
  const bubble = document.getElementById('pixelMascotBubble');
  const textEl = document.getElementById('pixelMascotText');
  const nameEl = document.getElementById('pixelMascotName');
  if (!bubble || !textEl) return;

  const char = PIXEL_CHARACTERS[mascotCharIndex];
  if (nameEl) nameEl.innerHTML = `${char.icon} ${char.name}`;

  const dialogues = getMascotDialogues();
  const msg = customMsg || dialogues[mascotDialogueIndex % dialogues.length];
  mascotDialogueIndex++;

  bubble.style.display = 'flex';
  textEl.textContent = '';
  
  if (mascotTypeInterval) clearInterval(mascotTypeInterval);
  let cIdx = 0;
  mascotTypeInterval = setInterval(() => {
    if (cIdx < msg.length) {
      textEl.textContent += msg[cIdx];
      cIdx++;
    } else {
      clearInterval(mascotTypeInterval);
      mascotTypeInterval = null;
    }
  }, 30);
}

export function switchMascotCharacter() {
  mascotCharIndex = (mascotCharIndex + 1) % PIXEL_CHARACTERS.length;
  const char = PIXEL_CHARACTERS[mascotCharIndex];
  const spriteEl = document.getElementById('pixelMascotSprite');
  if (spriteEl) {
    spriteEl.innerHTML = char.svg;
    spriteEl.classList.remove('jumping');
    void spriteEl.offsetWidth;
    spriteEl.classList.add('jumping');
    setTimeout(() => spriteEl.classList.remove('jumping'), 500);
  }
  playRetroBeep('coin');
  spawnPixelParticle(`★ ${char.title}!`);
  showMascotSpeech(`✦ Ganti Karakter: ${char.name} (${char.title}) siap membantumu!`);
}

export function initPixelMascot() {
  let mascotEl = document.getElementById('pixelMascot');
  if (!mascotEl) {
    mascotEl = document.createElement('div');
    mascotEl.id = 'pixelMascot';
    mascotEl.innerHTML = `
      <div class="pixel-mascot-bubble" id="pixelMascotBubble" style="display:none;">
        <div class="pixel-mascot-bubble-head">
          <span class="pixel-mascot-name" id="pixelMascotName">👾 HERO RESTU (LV.99)</span>
          <button class="pixel-mascot-close" id="closeMascotBubbleBtn" title="Tutup">✕</button>
        </div>
        <div class="pixel-mascot-body">
          <span id="pixelMascotText"></span><span class="pixel-mascot-cursor">█</span>
        </div>
        <div class="pixel-mascot-actions">
          <button class="pixel-mascot-btn" id="mascotTalkBtn">💬 Bicara</button>
          <button class="pixel-mascot-btn" id="mascotSkinBtn">🎭 Ganti Skin</button>
        </div>
      </div>
      <div class="pixel-mascot-char-wrap" id="pixelMascotCharWrap" title="Klik aku untuk interaksi!">
        <div class="pixel-mascot-tap-badge">TAP ME!</div>
        <div class="pixel-mascot-sprite" id="pixelMascotSprite">
          ${PIXEL_CHARACTERS[mascotCharIndex].svg}
        </div>
        <div class="pixel-mascot-shadow"></div>
      </div>
    `;
    document.body.appendChild(mascotEl);

    // Bindings
    const wrap = document.getElementById('pixelMascotCharWrap');
    if (wrap) {
      wrap.onclick = (e) => {
        e.stopPropagation();
        unlockAudio();
        const sprite = document.getElementById('pixelMascotSprite');
        if (sprite) {
          sprite.classList.remove('jumping');
          void sprite.offsetWidth;
          sprite.classList.add('jumping');
          setTimeout(() => sprite.classList.remove('jumping'), 500);
        }
        playRetroBeep('jump');
        const particles = ['+100 EXP!', '🪙 KA-CHING!', '⭐ SAVER!', '✦ CRITICAL!', '⚔️ COMBO!'];
        const pText = particles[Math.floor(Math.random() * particles.length)];
        spawnPixelParticle(pText);
        showMascotSpeech();
      };
    }

    document.getElementById('closeMascotBubbleBtn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      const b = document.getElementById('pixelMascotBubble');
      if (b) b.style.display = 'none';
    });

    document.getElementById('mascotTalkBtn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      playRetroBeep('coin');
      showMascotSpeech();
    });

    document.getElementById('mascotSkinBtn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      switchMascotCharacter();
    });
  }
}

// ============================================================
// Retro Starfield Background Engine
// ============================================================
export function startStarfield() {
  if (specialModeAnimId) return;
  const canvas = specialModeCanvas;
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let stars = [];
  const STAR_COUNT = 80;

  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  for (let i = 0; i < STAR_COUNT; i++) {
    stars.push({
      x: Math.random() * canvas.width,
      y: Math.random() * canvas.height,
      size: Math.random() * 2.5 + 1,
      speed: Math.random() * 0.02 + 0.005,
      phase: Math.random() * Math.PI * 2,
      color: ['#ffffff', '#c8b6ff', '#e0aaff', '#7b61ff', '#b8c0ff'][Math.floor(Math.random() * 5)]
    });
  }

  function draw(time) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    stars.forEach(s => {
      const alpha = 0.3 + 0.7 * Math.abs(Math.sin(time * s.speed + s.phase));
      ctx.fillStyle = s.color;
      ctx.globalAlpha = alpha;
      const sz = Math.round(s.size);
      ctx.fillRect(Math.round(s.x), Math.round(s.y), sz, sz);
    });
    ctx.globalAlpha = 1;
    specialModeAnimId = requestAnimationFrame(draw);
  }
  specialModeAnimId = requestAnimationFrame(draw);
}

export function stopStarfield() {
  if (specialModeAnimId) {
    cancelAnimationFrame(specialModeAnimId);
    specialModeAnimId = null;
  }
}

// ============================================================
// Special Mode Activator
// ============================================================
export function applySpecialMode() {
  const isSpecial = window.state?.user?.theme === 'special';

  if (isSpecial) {
    if (!specialModeCanvas) {
      specialModeCanvas = document.createElement('canvas');
      specialModeCanvas.id = 'specialStarfield';
      specialModeCanvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;';
      document.body.prepend(specialModeCanvas);
    }
    startStarfield();
    initPixelMascot();
    const mascotEl = document.getElementById('pixelMascot');
    if (mascotEl) mascotEl.style.display = 'flex';
  } else {
    stopStarfield();
    if (specialModeCanvas) {
      specialModeCanvas.remove();
      specialModeCanvas = null;
    }
    const mascotEl = document.getElementById('pixelMascot');
    if (mascotEl) mascotEl.style.display = 'none';
  }
  updateThemeButtons();
}

// ============================================================
// Retro Pixel Dialog for Transactions Feedback
// ============================================================
export function showPixelDialog(message, type) {
  if (window.state?.user?.theme !== 'special') return;

  const existing = document.getElementById('pixelDialog');
  if (existing) existing.remove();

  const isIncome = type === 'income';
  const borderColor = isIncome ? '#00ff41' : '#ff4444';
  const glowColor = isIncome ? '#00ff4140' : '#ff444440';
  const icon = isIncome ? '💰' : '⚠️';

  const dialog = document.createElement('div');
  dialog.id = 'pixelDialog';
  dialog.className = 'pixel-dialog';
  dialog.innerHTML = `
    <div class="pixel-dialog-inner" style="border-color:${borderColor};box-shadow:4px 4px 0 ${borderColor}, 8px 8px 0 rgba(0,0,0,0.5), 0 0 20px ${glowColor};">
      <div class="pixel-dialog-header">
        <span class="pixel-dialog-icon">${icon}</span>
        <span class="pixel-dialog-title">${isIncome ? '✦ REWARD ✦' : '✦ WARNING ✦'}</span>
        <button class="pixel-dialog-close" onclick="this.closest('.pixel-dialog').remove()">✕</button>
      </div>
      <div class="pixel-dialog-body">
        <span class="pixel-dialog-arrow">▶</span>
        <span class="pixel-dialog-text"></span>
        <span class="pixel-dialog-cursor">█</span>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);

  const textEl = dialog.querySelector('.pixel-dialog-text');
  let charIndex = 0;
  const typeInterval = setInterval(() => {
    if (charIndex < message.length) {
      textEl.textContent += message[charIndex];
      charIndex++;
    } else {
      clearInterval(typeInterval);
    }
  }, 50);

  requestAnimationFrame(() => dialog.classList.add('show'));

  setTimeout(() => {
    dialog.classList.remove('show');
    setTimeout(() => dialog.remove(), 400);
  }, 4000);
}

// ============================================================
// Dynamic Favicon Switcher
// ============================================================
export function updateFavicon() {
  const faviconEl = document.getElementById('appFavicon');
  if (!faviconEl) return;
  const theme = window.state?.user?.theme || 'dark';
  if (theme === 'special') {
    faviconEl.href = 'icons/icon-pixel.svg';
  } else if (theme === 'light') {
    faviconEl.href = 'icons/icon-light.svg';
  } else {
    faviconEl.href = 'icons/icon-dark.svg';
  }
}

// ============================================================
// Theme Buttons & Labels Sync
// ============================================================
export function updateThemeButtons() {
  const isSpecial = window.state?.user?.theme === 'special';
  const isLight = window.state?.user?.theme === 'light';
  const en = window.state?.user?.language === 'en';

  const stdBtn = document.getElementById('standardThemeBtn');
  if (stdBtn) {
    stdBtn.classList.toggle('active', !isSpecial);
    const icon = document.getElementById('standardThemeIcon');
    if (icon) icon.textContent = isLight ? '☀️' : '🌙';
    const label = document.getElementById('standardThemeLabel');
    if (label) {
      label.textContent = isLight ? (en ? 'Light' : 'Terang') : (en ? 'Dark' : 'Gelap');
    }
    stdBtn.title = isLight
      ? (en ? 'Light Mode (Click to switch to Dark)' : 'Mode Terang (Klik untuk ganti ke Gelap)')
      : (en ? 'Dark Mode (Click to switch to Light)' : 'Mode Gelap (Klik untuk ganti ke Terang)');
  }

  const specBtn = document.getElementById('specialThemeBtn');
  if (specBtn) {
    specBtn.classList.toggle('active', isSpecial);
    specBtn.title = 'Special Mode (Retro Pixel Art)';
  }

  updateFavicon();
  updateMusicBtn();
}

// ============================================================
// Centralized Theme Switcher
// ============================================================
export async function setTheme(theme) {
  if (!window.state) return;
  window.state.user.theme = theme;
  saveLocalPreferences();
  document.body.dataset.theme = theme;
  document.body.classList.toggle('special-mode', theme === 'special');
  document.body.classList.toggle('theme-light', theme === 'light');
  applySpecialMode();
  updateThemeButtons();
  if (typeof window.render === 'function') {
    window.render();
  }
  try {
    await apiFetch('/api/profile', { method: 'PUT', body: JSON.stringify({ theme }) });
  } catch (e) {}
}

// Expose on window for global access
window.setTheme = setTheme;
window.applySpecialMode = applySpecialMode;
window.updateFavicon = updateFavicon;
window.updateThemeButtons = updateThemeButtons;
window.showPixelDialog = showPixelDialog;
window.initPixelMascot = initPixelMascot;
window.showMascotSpeech = showMascotSpeech;
window.switchMascotCharacter = switchMascotCharacter;
window.PIXEL_CHARACTERS = PIXEL_CHARACTERS;
