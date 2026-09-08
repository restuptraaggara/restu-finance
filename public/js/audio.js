// public/js/audio.js - Web Audio API Synth & YouTube BGM Player Engine

// ============================================================
// Web Audio API Retro Chiptune Synthesizer
// ============================================================
export function playRetroBeep(type = 'coin') {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'square';
    osc.connect(gain);
    gain.connect(ctx.destination);

    const now = ctx.currentTime;
    if (type === 'coin') {
      osc.frequency.setValueAtTime(987.77, now);
      osc.frequency.setValueAtTime(1318.51, now + 0.08);
      gain.gain.setValueAtTime(0.1, now);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
      osc.start(now);
      osc.stop(now + 0.3);
    } else if (type === 'jump') {
      osc.frequency.setValueAtTime(180, now);
      osc.frequency.exponentialRampToValueAtTime(650, now + 0.16);
      gain.gain.setValueAtTime(0.1, now);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.2);
      osc.start(now);
      osc.stop(now + 0.2);
    } else if (type === 'warning') {
      osc.frequency.setValueAtTime(220, now);
      osc.frequency.setValueAtTime(165, now + 0.12);
      gain.gain.setValueAtTime(0.12, now);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
      osc.start(now);
      osc.stop(now + 0.35);
    } else if (type === 'victory') {
      // Chiptune 8-bit victory arpeggio (C5 -> E5 -> G5 -> C6)
      const notes = [523.25, 659.25, 783.99, 1046.50];
      const durations = [0.12, 0.12, 0.12, 0.4];
      let t = now;
      notes.forEach((freq, idx) => {
        osc.frequency.setValueAtTime(freq, t);
        t += durations[idx];
      });
      gain.gain.setValueAtTime(0.12, now);
      gain.gain.setValueAtTime(0.12, now + 0.36);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
      osc.start(now);
      osc.stop(now + 0.85);
    } else if (type === 'adventure') {
      const notes = [523.25, 783.99, 659.25, 1046.50];
      const durations = [0.1, 0.1, 0.1, 0.25];
      let t = now;
      notes.forEach((freq, idx) => {
        osc.frequency.setValueAtTime(freq, t);
        t += durations[idx];
      });
      gain.gain.setValueAtTime(0.12, now);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
      osc.start(now);
      osc.stop(now + 0.6);
    } else if (type === 'chill') {
      const notes = [440.00, 523.25, 659.25, 880.00];
      const durations = [0.12, 0.12, 0.12, 0.35];
      let t = now;
      notes.forEach((freq, idx) => {
        osc.frequency.setValueAtTime(freq, t);
        t += durations[idx];
      });
      gain.gain.setValueAtTime(0.1, now);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.7);
      osc.start(now);
      osc.stop(now + 0.75);
    }
  } catch (e) {}
}

// ============================================================
// RESTU FINANCE BGM — YouTube Background Music Engine
// ============================================================
export const BGM_PLAYLIST = [
  { id: 'uLF1lW3Ffrg', title: "Mia & Seb's Theme", artist: "La La Land (Jacob's Piano)" },
  { id: 'nNUVK7-qj3k', title: "City of Stars", artist: "La La Land (Pianella Piano)" },
  { id: 'Sdd_EvDJcqw', title: "golden hour", artist: "JVKE (Piano & Violin Cover)" },
  { id: 'NPBCbTZWnq0', title: "River Flows in You", artist: "Yiruma Piano" }
];

export let ytPlayer = null;
export let ytReady = false;
export let ytPlayerReady = false;
export let bgmMuted = localStorage.getItem('arus_bgm_muted') === 'true';
export let bgmTrackIndex = 0;
export let bgmActive = false;
export let userInteracted = false;
export let bgmProgressInterval = null;

export function isAuth() {
  return Boolean(window.isAuthenticated);
}

// Called automatically by YouTube IFrame API once script loads
window.onYouTubeIframeAPIReady = function () {
  ytReady = true;
  if (isAuth()) {
    _createPlayer(BGM_PLAYLIST[bgmTrackIndex].id);
  }
};

export function _createPlayer(videoId) {
  if (!isAuth()) return;
  const wrap = document.getElementById('ytPlayerWrap');
  if (!wrap) return;

  // Active in viewport so browser doesn't throttle audio
  wrap.style.cssText = 'position:fixed;bottom:8px;right:8px;width:200px;height:120px;opacity:0.001;pointer-events:none;z-index:-999;overflow:hidden;';

  if (ytPlayer && typeof ytPlayer.loadVideoById === 'function') {
    try {
      ytPlayer.loadVideoById(videoId);
      if (!bgmMuted) {
        ytPlayer.unMute();
        ytPlayer.setVolume(85);
        ytPlayer.playVideo();
      }
    } catch (e) {}
    return;
  }

  try {
    if (typeof YT === 'undefined' || !YT.Player) return;
    ytPlayer = new YT.Player('ytPlayer', {
      height: '120',
      width: '200',
      videoId: videoId,
      playerVars: {
        enablejsapi: 1,
        autoplay: 1,
        controls: 0,
        disablekb: 1,
        fs: 0,
        iv_load_policy: 3,
        loop: 0,
        modestbranding: 1,
        playsinline: 1,
        rel: 0
      },
      events: {
        onReady: (e) => {
          ytPlayerReady = true;
          e.target.setVolume(85);
          if (bgmMuted) {
            e.target.mute();
          } else {
            e.target.unMute();
            e.target.playVideo();
          }
          startProgressTracking();
          updateMusicPlayerUI();
          updateMusicBtn();
        },
        onStateChange: (e) => {
          if (e.data === YT.PlayerState.ENDED) {
            // Loop automatically back to the beginning track when playlist ends
            nextTrack();
          }
          updateMusicPlayerUI();
          updateMusicBtn();
        },
        onError: (err) => {
          console.warn('YouTube player warning:', err);
          // Automatically skip to next track on error
          nextTrack();
        }
      }
    });
  } catch (e) {
    console.warn('YT.Player init failed:', e);
  }
}

export function startProgressTracking() {
  if (bgmProgressInterval) clearInterval(bgmProgressInterval);
  bgmProgressInterval = setInterval(() => {
    if (!ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;
    try {
      const current = ytPlayer.getCurrentTime() || 0;
      const total = ytPlayer.getDuration() || 0;
      const progressFill = document.getElementById('playerProgressFill');
      const timeCur = document.getElementById('bgmCurrentTime') || document.getElementById('playerTimeCurrent');
      const timeTot = document.getElementById('bgmDuration') || document.getElementById('playerTimeTotal');
      const seekSlider = document.getElementById('bgmProgressBar') || document.getElementById('playerSeekSlider');

      if (progressFill && total > 0) {
        progressFill.style.width = `${(current / total) * 100}%`;
      }
      if (seekSlider && total > 0 && !seekSlider.matches(':active')) {
        seekSlider.value = (current / total) * 100;
      }
      if (timeCur) timeCur.textContent = formatAudioTime(current);
      if (timeTot && total > 0) timeTot.textContent = formatAudioTime(total);
    } catch (e) {}
  }, 500);
}

export function formatAudioTime(seconds) {
  const s = Math.floor(seconds || 0);
  const m = Math.floor(s / 60);
  const remSec = s % 60;
  return `${m}:${remSec < 10 ? '0' : ''}${remSec}`;
}

export function playTrack(index) {
  if (!isAuth()) return;
  bgmTrackIndex = (index + BGM_PLAYLIST.length) % BGM_PLAYLIST.length;
  const track = BGM_PLAYLIST[bgmTrackIndex];
  if (ytPlayer && typeof ytPlayer.loadVideoById === 'function') {
    try {
      ytPlayer.loadVideoById(track.id);
      if (!bgmMuted) {
        ytPlayer.unMute();
        ytPlayer.setVolume(85);
        ytPlayer.playVideo();
      }
    } catch (e) {}
  } else {
    _createPlayer(track.id);
  }
  bgmActive = true;
  updateMusicPlayerUI();
  updateMusicBtn();
  if (typeof window.toast === 'function') {
    window.toast(`🎵 Memutar: ${track.title}`);
  }
}

export function nextTrack() {
  if (!isAuth()) return;
  playTrack(bgmTrackIndex + 1);
}

export function prevTrack() {
  if (!isAuth()) return;
  playTrack(bgmTrackIndex - 1);
}

export function rewind10s() {
  if (!isAuth() || !ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;
  try {
    const cur = ytPlayer.getCurrentTime() || 0;
    ytPlayer.seekTo(Math.max(0, cur - 10), true);
  } catch (e) {}
}

export function forward10s() {
  if (!isAuth() || !ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;
  try {
    const cur = ytPlayer.getCurrentTime() || 0;
    const dur = ytPlayer.getDuration() || 0;
    ytPlayer.seekTo(Math.min(dur, cur + 10), true);
  } catch (e) {}
}

export function replayCurrentTrack() {
  if (!isAuth() || !ytPlayer || typeof ytPlayer.seekTo !== 'function') return;
  try {
    ytPlayer.seekTo(0, true);
    if (!bgmMuted) ytPlayer.playVideo();
  } catch (e) {}
}

export function togglePlayPause() {
  if (!isAuth()) return;
  unlockAudio();
  if (!ytPlayer || typeof ytPlayer.getPlayerState !== 'function') {
    playTrack(bgmTrackIndex);
    return;
  }
  try {
    const state = ytPlayer.getPlayerState();
    if (state === YT.PlayerState.PLAYING) {
      ytPlayer.pauseVideo();
      bgmActive = false;
    } else {
      ytPlayer.unMute();
      bgmMuted = false;
      localStorage.setItem('arus_bgm_muted', 'false');
      ytPlayer.playVideo();
      bgmActive = true;
    }
  } catch (e) {
    playTrack(bgmTrackIndex);
  }
  updateMusicPlayerUI();
  updateMusicBtn();
}

export function toggleMute() {
  if (!isAuth()) return;
  unlockAudio();
  bgmMuted = !bgmMuted;
  localStorage.setItem('arus_bgm_muted', String(bgmMuted));

  if (ytPlayer && ytPlayerReady) {
    try {
      if (bgmMuted) {
        ytPlayer.mute();
        bgmActive = false;
      } else {
        ytPlayer.unMute();
        ytPlayer.setVolume(85);
        ytPlayer.playVideo();
        bgmActive = true;
      }
    } catch (e) {}
  } else {
    _createPlayer(BGM_PLAYLIST[bgmTrackIndex].id);
  }

  updateMusicBtn();
  updateMusicPlayerUI();

  if (typeof window.toast === 'function') {
    const en = window.state?.user?.language === 'en';
    window.toast(bgmMuted ? (en ? '🔇 BGM Muted' : '🔇 BGM Dimatikan') : (en ? '🔊 BGM Playing' : '🔊 BGM Dinyalakan'));
  }
}

export function startBGM() {
  if (!isAuth() || bgmMuted) return;
  if (ytPlayer && ytPlayerReady) {
    try {
      ytPlayer.unMute();
      ytPlayer.setVolume(85);
      ytPlayer.playVideo();
      bgmActive = true;
    } catch (e) {}
  } else if (!ytPlayer) {
    _createPlayer(BGM_PLAYLIST[bgmTrackIndex].id);
  }
  updateMusicBtn();
  updateMusicPlayerUI();
}

export function updateMusicBtn() {
  const musicBtn = document.getElementById('musicBtn');
  const musicIcon = document.getElementById('musicBtnIcon');
  if (!musicBtn) return;

  if (bgmMuted) {
    musicBtn.classList.remove('active');
    if (musicIcon) musicIcon.textContent = '🔇';
    musicBtn.title = window.state?.user?.language === 'en' ? 'Unmute BGM (Music Muted)' : 'Nyalakan Musik BGM (Sedang Bisu)';
  } else {
    musicBtn.classList.add('active');
    if (musicIcon) musicIcon.textContent = '♪';
    musicBtn.title = window.state?.user?.language === 'en' ? 'Mute BGM (Music Playing)' : 'Matikan Musik BGM (Sedang Memutar)';
  }
}

// ============================================================
// Web Audio API Retro Chiptune Synthesizer Engine (v1.4.0)
// ============================================================
export const RETRO_SYNTH_TRACKS = [
  {
    id: 'adventure',
    title: 'Retro Adventure',
    badge: 'NES 8-Bit Arcade',
    notes: [
      { f: 523.25, d: 0.15 }, { f: 659.25, d: 0.15 }, { f: 783.99, d: 0.15 }, { f: 1046.50, d: 0.3 },
      { f: 783.99, d: 0.15 }, { f: 1046.50, d: 0.3 }, { f: 880.00, d: 0.15 }, { f: 659.25, d: 0.15 },
      { f: 587.33, d: 0.15 }, { f: 523.25, d: 0.3 }
    ]
  },
  {
    id: 'chill',
    title: 'Pixel Chill (Lofi Melodi)',
    badge: 'Game Boy Lofi',
    notes: [
      { f: 440.00, d: 0.25 }, { f: 523.25, d: 0.25 }, { f: 659.25, d: 0.25 }, { f: 587.33, d: 0.25 },
      { f: 523.25, d: 0.25 }, { f: 440.00, d: 0.25 }, { f: 392.00, d: 0.25 }, { f: 440.00, d: 0.5 }
    ]
  },
  {
    id: 'rush',
    title: 'Arcade Speed Rush',
    badge: 'Chiptune Fast Beats',
    notes: [
      { f: 659.25, d: 0.1 }, { f: 783.99, d: 0.1 }, { f: 987.77, d: 0.1 }, { f: 1318.51, d: 0.2 },
      { f: 1174.66, d: 0.1 }, { f: 987.77, d: 0.1 }, { f: 880.00, d: 0.1 }, { f: 783.99, d: 0.2 }
    ]
  }
];

export let isRetroSynthActive = false;
export let retroSynthIndex = 0;
let retroSynthTimer = null;
let retroAudioCtx = null;

export function playRetroSynthTrack(idx = 0) {
  if (!isAuth()) return;
  stopRetroSynthTrack();
  retroSynthIndex = idx % RETRO_SYNTH_TRACKS.length;
  isRetroSynthActive = true;

  if (ytPlayer && typeof ytPlayer.pauseVideo === 'function') {
    try { ytPlayer.pauseVideo(); } catch (e) {}
  }

  const track = RETRO_SYNTH_TRACKS[retroSynthIndex];
  const AudioCtx = window.AudioContext || window.webkitAudioContext;
  if (!AudioCtx) return;
  if (!retroAudioCtx) retroAudioCtx = new AudioCtx();
  if (retroAudioCtx.state === 'suspended') retroAudioCtx.resume();

  const playPhrase = () => {
    if (!isRetroSynthActive || !retroAudioCtx) return;
    let t = retroAudioCtx.currentTime + 0.05;
    track.notes.forEach(n => {
      const osc = retroAudioCtx.createOscillator();
      const gain = retroAudioCtx.createGain();
      osc.type = 'square';
      osc.frequency.setValueAtTime(n.f, t);
      gain.gain.setValueAtTime(0.08, t);
      gain.gain.exponentialRampToValueAtTime(0.001, t + n.d);
      osc.connect(gain);
      gain.connect(retroAudioCtx.destination);
      osc.start(t);
      osc.stop(t + n.d);
      t += n.d + 0.02;
    });
  };

  playPhrase();
  const durMs = track.notes.reduce((sum, n) => sum + n.d + 0.02, 0) * 1000;
  retroSynthTimer = setInterval(playPhrase, Math.max(durMs, 1400));

  updateMusicPlayerUI();
}

export function stopRetroSynthTrack() {
  isRetroSynthActive = false;
  if (retroSynthTimer) {
    clearInterval(retroSynthTimer);
    retroSynthTimer = null;
  }
}

export function updateMusicPlayerUI() {
  const current = BGM_PLAYLIST[bgmTrackIndex];
  const currentRetro = RETRO_SYNTH_TRACKS[retroSynthIndex];

  const trackTitle = document.getElementById('playerTrackTitle');
  const trackArtist = document.getElementById('playerTrackArtist');
  const trackBadge = document.getElementById('playerTrackBadge');
  const playPauseBtn = document.getElementById('playPauseBtn') || document.getElementById('playerPlayPauseBtn');
  const volumeBtn = document.getElementById('playerMuteBtn') || document.getElementById('playerVolumeBtn');
  const playlistItemsEl = document.getElementById('playerPlaylistList') || document.getElementById('playerPlaylistItems');

  if (isRetroSynthActive && currentRetro) {
    if (trackTitle) trackTitle.textContent = currentRetro.title;
    if (trackArtist) trackArtist.textContent = '👾 ' + currentRetro.badge;
    if (trackBadge) trackBadge.textContent = '👾 ' + currentRetro.badge;
  } else if (current) {
    if (trackTitle) trackTitle.textContent = current.title;
    if (trackArtist) trackArtist.textContent = current.artist;
    if (trackBadge) trackBadge.textContent = current.artist;
  }

  let isPlaying = false;
  if (isRetroSynthActive) {
    isPlaying = true;
  } else if (ytPlayer && typeof ytPlayer.getPlayerState === 'function') {
    try {
      isPlaying = ytPlayer.getPlayerState() === YT.PlayerState.PLAYING;
    } catch (e) {}
  }

  if (playPauseBtn) {
    playPauseBtn.textContent = isPlaying ? '⏸' : '▶';
  }
  if (volumeBtn) {
    volumeBtn.textContent = bgmMuted ? '🔇 Bisu' : '🔊 Suara Aktif';
  }

  if (playlistItemsEl) {
    const ytItems = BGM_PLAYLIST.map((item, idx) => `
      <div class="playlist-item ${(!isRetroSynthActive && idx === bgmTrackIndex) ? 'active' : ''}" onclick="window.stopRetroSynthTrack(); window.playTrack(${idx})">
        <div class="playlist-item-left">
          <span class="playlist-item-num">${(!isRetroSynthActive && idx === bgmTrackIndex) ? '▶' : (idx + 1)}</span>
          <div>
            <div class="playlist-item-title">${item.title}</div>
            <div class="playlist-item-artist">${item.artist}</div>
          </div>
        </div>
        ${(!isRetroSynthActive && idx === bgmTrackIndex) ? '<span class="playlist-playing-badge">PLAYING</span>' : ''}
      </div>
    `).join('');

    const retroItems = RETRO_SYNTH_TRACKS.map((t, idx) => `
      <div class="playlist-item ${(isRetroSynthActive && idx === retroSynthIndex) ? 'active' : ''}" onclick="window.playRetroSynthTrack(${idx})">
        <div class="playlist-item-left">
          <span class="playlist-item-num">👾</span>
          <div>
            <div class="playlist-item-title">${t.title}</div>
            <div class="playlist-item-artist">${t.badge}</div>
          </div>
        </div>
        ${(isRetroSynthActive && idx === retroSynthIndex) ? '<span class="playlist-playing-badge">PLAYING</span>' : '<button class="period" style="font-size:10px;padding:3px 8px;">Putar</button>'}
      </div>
    `).join('');

    playlistItemsEl.innerHTML = `
      <div class="playlist-section-label" style="font-size:10px;font-weight:700;color:var(--muted);margin:6px 0 4px;letter-spacing:1px;">🎹 PIANO & ACOUSTIC (YOUTUBE BGM)</div>
      ${ytItems}
      <div class="playlist-section-label" style="font-size:10px;font-weight:700;color:var(--green, #d7f56f);margin:12px 0 4px;letter-spacing:1px;">👾 8-BIT RETRO SYNTH (WEB AUDIO API)</div>
      ${retroItems}
    `;
  }
}

export function unlockAudio() {
  if (!isAuth()) return;
  userInteracted = true;
  if (!bgmMuted && (!ytPlayer || !bgmActive)) {
    startBGM();
  }
}

export function stopAudio() {
  bgmActive = false;
  stopRetroSynthTrack();
  if (bgmProgressInterval) {
    clearInterval(bgmProgressInterval);
    bgmProgressInterval = null;
  }
  if (ytPlayer && typeof ytPlayer.pauseVideo === 'function') {
    try {
      ytPlayer.pauseVideo();
    } catch (e) {}
  }
  const progressFill = document.getElementById('playerProgressFill');
  if (progressFill) progressFill.style.width = '0%';
  const seekSlider = document.getElementById('bgmProgressBar') || document.getElementById('playerSeekSlider');
  if (seekSlider) seekSlider.value = 0;
  const timeCur = document.getElementById('bgmCurrentTime') || document.getElementById('playerTimeCurrent');
  if (timeCur) timeCur.textContent = '0:00';
  const musicPlayerPanelEl = document.getElementById('musicPlayerPanel');
  if (musicPlayerPanelEl) musicPlayerPanelEl.style.display = 'none';
  updateMusicBtn();
  updateMusicPlayerUI();
}

export const stopBGM = stopAudio;

export function initAudioAfterLogin() {
  if (!isAuth()) return;
  if (ytReady && !ytPlayer) {
    _createPlayer(BGM_PLAYLIST[bgmTrackIndex].id);
  } else if (ytPlayer && ytPlayerReady && !bgmMuted) {
    try {
      ytPlayer.unMute();
      ytPlayer.setVolume(85);
      ytPlayer.playVideo();
      bgmActive = true;
    } catch (e) {}
  }
  updateMusicBtn();
  updateMusicPlayerUI();
}

// Bind music player panel UI triggers
export function initAudioUI() {
  const musicBtnEl = document.getElementById('musicBtn');
  const musicPlayerPanelEl = document.getElementById('musicPlayerPanel');
  const closeMusicPanelBtnEl = document.getElementById('closeMusicPanelBtn');

  if (musicBtnEl && musicPlayerPanelEl) {
    musicBtnEl.addEventListener('click', (e) => {
      e.stopPropagation();
      if (!isAuth()) return;
      const isHidden = musicPlayerPanelEl.style.display === 'none';
      if (isHidden) {
        updateMusicPlayerUI();
        musicPlayerPanelEl.style.display = 'block';
      } else {
        musicPlayerPanelEl.style.display = 'none';
      }
    });
  }

  if (closeMusicPanelBtnEl && musicPlayerPanelEl) {
    closeMusicPanelBtnEl.addEventListener('click', () => {
      musicPlayerPanelEl.style.display = 'none';
    });
  }

  const prevBtn = document.getElementById('prevTrackBtn') || document.getElementById('playerPrevBtn');
  if (prevBtn) prevBtn.onclick = prevTrack;

  const nextBtn = document.getElementById('nextTrackBtn') || document.getElementById('playerNextBtn');
  if (nextBtn) nextBtn.onclick = nextTrack;

  const playPauseBtn = document.getElementById('playPauseBtn') || document.getElementById('playerPlayPauseBtn');
  if (playPauseBtn) playPauseBtn.onclick = togglePlayPause;

  const rewindBtn = document.getElementById('rewind10Btn') || document.getElementById('playerRewindBtn');
  if (rewindBtn) rewindBtn.onclick = rewind10s;

  const forwardBtn = document.getElementById('forward10Btn') || document.getElementById('playerForwardBtn');
  if (forwardBtn) forwardBtn.onclick = forward10s;

  const replayBtn = document.getElementById('replayTrackBtn') || document.getElementById('playerReplayBtn');
  if (replayBtn) replayBtn.onclick = replayCurrentTrack;

  const volumeBtn = document.getElementById('playerMuteBtn') || document.getElementById('playerVolumeBtn');
  if (volumeBtn) volumeBtn.onclick = toggleMute;

  const seekSlider = document.getElementById('bgmProgressBar') || document.getElementById('playerSeekSlider');
  if (seekSlider) {
    seekSlider.oninput = () => {
      if (!isAuth() || !ytPlayer || typeof ytPlayer.seekTo !== 'function') return;
      try {
        const total = ytPlayer.getDuration() || 0;
        const targetSec = (seekSlider.value / 100) * total;
        ytPlayer.seekTo(targetSec, true);
      } catch (e) {}
    };
  }

  // Close music panel on click outside
  document.addEventListener('click', (e) => {
    if (musicPlayerPanelEl && musicPlayerPanelEl.style.display !== 'none' && !musicPlayerPanelEl.contains(e.target) && e.target !== musicBtnEl) {
      musicPlayerPanelEl.style.display = 'none';
    }
  });

  // Global user interaction listener to unlock audio (only if authenticated)
  ['click', 'touchstart', 'keydown'].forEach(evt => {
    document.addEventListener(evt, () => {
      if (!userInteracted && isAuth()) {
        unlockAudio();
      }
    }, { once: true });
  });
}

// Expose functions on window for inline HTML and backward compatibility
window.playRetroBeep = playRetroBeep;
window.playTrack = playTrack;
window.nextTrack = nextTrack;
window.prevTrack = prevTrack;
window.rewind10s = rewind10s;
window.forward10s = forward10s;
window.replayCurrentTrack = replayCurrentTrack;
window.togglePlayPause = togglePlayPause;
window.toggleMute = toggleMute;
window.startBGM = startBGM;
window.stopAudio = stopAudio;
window.stopBGM = stopBGM;
window.initAudioAfterLogin = initAudioAfterLogin;
window.isAuth = isAuth;
window.updateMusicBtn = updateMusicBtn;
window.updateMusicPlayerUI = updateMusicPlayerUI;
window.unlockAudio = unlockAudio;
window.initAudioUI = initAudioUI;
window.BGM_PLAYLIST = BGM_PLAYLIST;
window.RETRO_SYNTH_TRACKS = RETRO_SYNTH_TRACKS;
window.playRetroSynthTrack = playRetroSynthTrack;
window.stopRetroSynthTrack = stopRetroSynthTrack;


