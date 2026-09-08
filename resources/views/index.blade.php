<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name', 'Restu Finance') }}</title>
  <meta name="description" content="Restu Finance - Aplikasi pengatur keuangan pribadi modern, otomatis, dan terjadwal.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <link rel="stylesheet" href="{{ asset('style.css?v=14') }}">
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <meta name="theme-color" content="#0d1417">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Restu Finance">
  <link rel="icon" id="appFavicon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
  <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand"><span class="brand-mark" aria-hidden="true"></span><span>pengatur uang</span></div>
      <div class="profile" style="position: relative;">
        <div class="avatar" id="profileAvatar">RP</div>
        <div><strong id="profileName">Restu Putra Anggara</strong><small>Personal account</small></div>
        <button class="icon-btn" id="profileMoreBtn" aria-label="More" title="Menu Akun">•••</button>
        <!-- Profile Dropdown Menu -->
        <div class="profile-dropdown" id="profileDropdown" style="display: none;">
          <button type="button" class="dropdown-item" id="profileMenuSettings">
            <span class="dropdown-icon">⚙</span> Profil & Pengaturan
          </button>
          <button type="button" class="dropdown-item" id="profileMenuCopyId">
            <span class="dropdown-icon">📋</span> Salin ID / Info Sesi
          </button>
          <div class="dropdown-divider"></div>
          <button type="button" class="dropdown-item dropdown-item-danger" id="profileMenuLogout">
            <span class="dropdown-icon">🚪</span> Keluar (Logout)
          </button>
        </div>
      </div>
      <nav class="nav" aria-label="Navigasi utama">
        <button class="nav-item active" data-page="dashboard"><span class="icon">⊞</span>Dasbor</button>
        <button class="nav-item" data-page="transactions"><span class="icon">⇄</span>Transaksi</button>
        <button class="nav-item" data-page="statistics"><span class="icon">◰</span>Statistik</button>
        <button class="nav-item" data-page="budget"><span class="icon">◫</span>Anggaran</button>
        <button class="nav-item" data-page="goals"><span class="icon">◎</span>Tujuan</button>
        <button class="nav-item" data-page="wallets"><span class="icon">◻</span>Dompet</button>
        <button class="nav-item" data-page="debts"><span class="icon">⚖</span>Utang & Piutang</button>
        <button class="nav-item" data-page="recurring"><span class="icon">↻</span>Berulang</button>
        <button class="nav-item" data-page="settings"><span class="icon">⚙</span>Pengaturan</button>
        <button class="nav-item admin-only-nav" id="adminChatNavItem" data-page="adminChat" style="display: none;"><span class="icon">💬</span>Admin Inbox <span class="badge admin-chat-badge" id="adminNavUnreadBadge" style="display: none; margin-left: auto; font-size: 10px; padding: 2px 6px; border-radius: 10px; background: #ef4444; color: #fff;">0</span></button>
        <button class="nav-item sidebar-logout-btn" id="sidebarLogoutBtn" title="Keluar dari akun"><span class="icon">🚪</span>Keluar</button>
      </nav>
      <div class="upgrade">
        <strong>Kelola uangmu<br>lebih cerdas.</strong>
        <p class="muted">Saldo aman & terkontrol</p>
        <small class="badge">V1.4.0</small>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="mobile-brand"><span class="brand-mark" aria-hidden="true"></span>pengatur uang</div>
        <div class="top-actions">
          <!-- Theme Switcher: Mode Biasa (Terang/Gelap) & Special Mode side-by-side beside the card button -->
          <div class="theme-switch-group">
            <button class="icon-btn theme-mode-btn" id="standardThemeBtn" title="Ganti Mode (Terang / Gelap)">
              <span id="standardThemeIcon">🌙</span>
              <span class="theme-btn-label" id="standardThemeLabel">Gelap</span>
            </button>
            <button class="icon-btn theme-mode-btn" id="specialThemeBtn" title="Special Mode (Retro Pixel Art)">
              <span class="special-badge-icon">👾</span>
              <span class="theme-btn-label">Special</span>
            </button>
          </div>
          <button class="icon-btn music-btn" id="musicBtn" aria-label="Toggle music" title="Musik BGM (Mute / Unmute)">
            <span id="musicBtnIcon">♪</span>
            <span class="music-btn-label">BGM</span>
          </button>
          <button class="icon-btn notification" id="notifBtn" aria-label="Notifications" title="Notifikasi Keuangan & Status">
            <span class="card-suit-icon">♧</span><i id="notifBadge"></i>
          </button>
          <button class="icon-btn chat-top-btn" id="chatFabBtn" title="Hubungi Admin / Live Support" aria-label="Buka Live Chat">
            <span class="chat-fab-icon" id="chatFabIcon">💬</span>
            <span class="chat-btn-label">Chat</span>
            <span class="chat-fab-badge" id="chatFabBadge" style="display: none;">0</span>
          </button>
          <button class="avatar top-avatar" id="topAvatar" title="Buka Pengaturan Akun" aria-label="Profil & Pengaturan">RP</button>
          <button class="icon-btn logout-top-btn" id="topbarLogoutBtn" title="Keluar dari akun (Logout)" aria-label="Keluar">🚪</button>
        </div>
      </header>

      <!-- Notification Dropdown Panel -->
      <div class="notif-dropdown" id="notifDropdown" style="display: none;">
        <div class="notif-header">
          <div class="notif-title">
            <span class="notif-icon-symbol">♧</span>
            <strong>Pemberitahuan</strong>
          </div>
          <button class="notif-close-btn" id="closeNotifBtn">✕</button>
        </div>
        <div class="notif-body" id="notifBody">
          <!-- Populated by JavaScript -->
        </div>
      </div>

      <!-- Music Player Dropdown Panel -->
      <div class="music-player-panel" id="musicPlayerPanel" style="display: none;">
        <div class="notif-header">
          <div class="notif-title">
            <span class="music-icon-symbol">🎵</span>
            <strong>Restu BGM Player</strong>
          </div>
          <button class="notif-close-btn" id="closeMusicPanelBtn">✕</button>
        </div>
        <div class="music-player-body">
          <!-- Current Track Display -->
          <div class="player-track-info">
            <div class="player-track-label">NOW PLAYING</div>
            <div class="player-track-title" id="playerTrackTitle">Mia & Seb's Theme</div>
            <div class="player-track-badge" id="playerTrackBadge">La La Land</div>
          </div>

          <!-- Timeline & Progress Bar (Seekbar) -->
          <div class="player-progress-wrap">
            <input type="range" id="bgmProgressBar" min="0" max="100" value="0" step="0.2" class="player-slider" title="Geser untuk memindahkan posisi menit/detik lagu">
            <div class="player-time-display">
              <span id="bgmCurrentTime">0:00</span>
              <span id="bgmDuration">0:00</span>
            </div>
          </div>

          <!-- Control Buttons: Prev, Rewind 10s, Play/Pause, Forward 10s, Next -->
          <div class="player-controls">
            <button class="player-btn" id="prevTrackBtn" title="Lagu Sebelumnya">⏮</button>
            <button class="player-btn seek-btn" id="rewind10Btn" title="Mundur 10 Detik">⏪ 10s</button>
            <button class="player-btn play-btn" id="playPauseBtn" title="Putar / Jeda">▶</button>
            <button class="player-btn seek-btn" id="forward10Btn" title="Maju 10 Detik">10s ⏩</button>
            <button class="player-btn next-btn" id="nextTrackBtn" title="Lagu Berikutnya">⏭</button>
          </div>

          <div class="player-subactions">
            <button class="player-sub-btn" id="replayTrackBtn" title="Ulang lagu dari awal (0:00)">🔁 Ulang Lagu</button>
            <button class="player-sub-btn" id="playerMuteBtn" title="Mute / Unmute Suara">🔊 Suara Aktif</button>
          </div>

          <!-- Playlist List -->
          <div class="player-playlist-wrap">
            <div class="player-playlist-header">DAFTAR LAGU (LOOP OTOMATIS)</div>
            <div class="player-playlist-list" id="playerPlaylistList">
              <!-- Rendered via JS -->
            </div>
          </div>
        </div>
      </div>

      <!-- Live Chat Dropdown Panel -->
      <div class="chat-popup" id="chatPopup" style="display: none;">
        <div class="chat-header">
          <div class="chat-header-info">
            <span class="chat-status-dot online"></span>
            <div>
              <strong>Live Support & Keluhan</strong>
              <small>Admin Restu Finance</small>
            </div>
          </div>
          <button class="chat-header-close" id="chatCloseBtn" title="Tutup Chat">✕</button>
        </div>
        <div class="chat-messages" id="chatMessagesList">
          <div class="chat-loading-hint">Memuat percakapan...</div>
        </div>
        <form class="chat-input-form" id="chatInputForm">
          <input type="text" id="chatInputField" placeholder="Tulis pesan atau keluhan..." maxlength="2000" autocomplete="off" required>
          <button type="submit" class="chat-send-btn" id="chatSendBtn" title="Kirim Pesan">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
          </button>
        </form>
      </div>

      <section class="content" id="app">
        <div class="page-heading">
          <div><p class="eyebrow" id="greeting"></p><h1>Good morning, <span id="headingName">Restu Putra Anggara</span> <span>✦</span></h1><p class="muted">Berikut ringkasan keuangan Anda hari ini.</p></div>
          <button class="primary add-trigger">＋ Tambah transaksi</button>
        </div>

        <div id="pageContent"></div>
      </section>
    </main>
  </div>

  <!-- Modal Tambah Transaksi -->
  <div class="modal-backdrop" id="modalBackdrop">
    <div class="modal" role="dialog" aria-modal="true">
      <div class="modal-head"><div><p class="eyebrow">AKTIVITAS BARU</p><h2 id="modalTitle">Tambah transaksi</h2></div><button class="close-btn" id="closeModal">×</button></div>
      <form id="transactionForm">
        <div class="type-toggle"><button type="button" class="type active" data-type="expense">Pengeluaran</button><button type="button" class="type income" data-type="income">Pemasukan</button></div>
        <label>Nominal<div class="amount-input"><span>Rp</span><input id="amount" inputmode="numeric" placeholder="0" required></div></label>
        <div id="insufficientWarning" class="insufficient-warning" style="display: none;"></div>
        <div class="split-header"><strong>Alokasi kebutuhan</strong><button type="button" class="period" id="addSplit">+ Tambah kebutuhan</button></div>
        <div id="splitRows" class="split-rows">
          <div class="split-row"><select class="split-category"></select><input class="split-amount" inputmode="numeric" placeholder="Nominal"></div>
        </div>
        <div class="form-grid">
          <label id="categoryField">Kategori<select id="category"></select></label>
          <label>Wallet<select id="wallet"></select></label>
          <label>Payment method<select id="method"><option>Bank transfer</option><option>Cash</option><option>Debit card</option><option>Credit card</option><option>E-wallet</option></select></label>
          <input id="date" type="hidden"><input id="time" type="hidden">
        </div>
        <label>Goal tujuan <span class="optional">optional</span><select id="goal"><option value="">Tidak ada goal</option></select></label>
        <label>Note <textarea id="note" rows="2" placeholder="Tambahkan catatan transaksi"></textarea></label>
        <button class="primary save-btn" type="submit">Simpan transaksi <span>↗</span></button>
      </form>
    </div>
  </div>

  <!-- Dedicated Authentication Screen (Login & Register) -->
  <div class="auth-screen" id="authScreen" style="display: none;">
    <div class="auth-card">
      <div class="auth-header">
        <div class="brand auth-brand">
          <span class="brand-mark" aria-hidden="true"></span>
          <span>pengatur uang</span>
        </div>
        <h1 class="auth-title" id="authTitle">Selamat Datang</h1>
        <p class="auth-subtitle" id="authSubtitle">Kelola keuangan pribadi dengan rapi, aman, dan terencana.</p>
      </div>

      <!-- Auth Tabs: Login vs Register -->
      <div class="auth-tabs" role="tablist">
        <button type="button" class="auth-tab active" id="authTabLogin" role="tab" aria-selected="true">Masuk</button>
        <button type="button" class="auth-tab" id="authTabRegister" role="tab" aria-selected="false">Daftar Akun Baru</button>
      </div>

      <!-- Form Login -->
      <form id="loginForm" class="auth-form">
        <div id="loginErrorBanner" class="auth-error-banner" style="display: none;"></div>
        <label>Email Akun
          <input type="email" id="loginEmail" placeholder="nama@email.com" required autocomplete="username">
        </label>
        <label>Kata Sandi (Password)
          <div class="password-input-wrap">
            <input type="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="password-toggle-btn" data-target="loginPassword" aria-label="Lihat kata sandi" title="Lihat/Sembunyikan kata sandi">
              <span class="eye-icon">👁</span>
            </button>
          </div>
        </label>
        <div class="auth-forgot-wrap">
          <button type="button" class="auth-forgot-link" id="forgotPasswordTriggerBtn">Lupa Kata Sandi?</button>
        </div>
        <button class="primary auth-submit-btn" id="loginSubmitBtn" type="submit">
          Masuk ke Aplikasi <span>↗</span>
        </button>
        <div class="auth-switch-hint">
          Belum punya akun? <button type="button" class="link-btn" id="switchToRegisterBtn">Daftar sekarang</button>
        </div>
      </form>

      <!-- Form Register -->
      <form id="registerForm" class="auth-form" style="display: none;">
        <div id="registerErrorBanner" class="auth-error-banner" style="display: none;"></div>
        <label>Nama Lengkap
          <input type="text" id="registerName" placeholder="Contoh: Restu Putra" required autocomplete="name">
        </label>
        <label>Email Akun
          <input type="email" id="registerEmail" placeholder="nama@email.com" required autocomplete="email">
        </label>
        <label>Kode Akses / Registration Key (Private Beta)
          <div class="password-input-wrap">
            <input type="password" id="registerRegistrationKey" placeholder="Masukkan kode akses (misal: RESTU-BETA-2026)" required autocomplete="off">
            <button type="button" class="password-toggle-btn" data-target="registerRegistrationKey" aria-label="Lihat kode akses" title="Lihat/Sembunyikan kode akses">
              <span class="eye-icon">👁</span>
            </button>
          </div>
        </label>
        <label>Kata Sandi (Minimal 8 karakter)
          <div class="password-input-wrap">
            <input type="password" id="registerPassword" placeholder="Minimal 8 karakter" required minlength="8" autocomplete="new-password">
            <button type="button" class="password-toggle-btn" data-target="registerPassword" aria-label="Lihat kata sandi" title="Lihat/Sembunyikan kata sandi">
              <span class="eye-icon">👁</span>
            </button>
          </div>
        </label>
        <label>Konfirmasi Kata Sandi
          <div class="password-input-wrap">
            <input type="password" id="registerPasswordConfirmation" placeholder="Ulangi kata sandi" required minlength="8" autocomplete="new-password">
            <button type="button" class="password-toggle-btn" data-target="registerPasswordConfirmation" aria-label="Lihat kata sandi" title="Lihat/Sembunyikan kata sandi">
              <span class="eye-icon">👁</span>
            </button>
          </div>
        </label>
        <button class="primary auth-submit-btn" id="registerSubmitBtn" type="submit">
          Buat Akun Baru <span>↗</span>
        </button>
        <div class="auth-switch-hint">
          Sudah punya akun? <button type="button" class="link-btn" id="switchToLoginBtn">Masuk di sini</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Lupa Kata Sandi (OTP 2 Langkah via Gmail) -->
  <div class="modal-backdrop" id="forgotModalBackdrop" style="display: none; z-index: 100000;">
    <div class="modal auth-card" role="dialog" aria-modal="true" style="max-width: 440px;">
      <div class="modal-head" style="margin-bottom: 16px;">
        <div>
          <span class="forgot-step-badge" id="forgotStepBadge">LANGKAH 1 DARI 2</span>
          <h2 id="forgotModalTitle" style="font-size: 20px; margin: 4px 0 0;">Lupa Kata Sandi</h2>
        </div>
        <button type="button" class="close-btn" id="closeForgotModalBtn">×</button>
      </div>

      <!-- Step 1: Input Email -->
      <form id="forgotStep1Form" class="auth-form">
        <p class="muted" style="font-size: 13px; margin: 0 0 8px; line-height: 1.5;">
          Masukkan alamat email terdaftar akun Anda. Kami akan mengirimkan 6-digit kode OTP ke email Anda untuk mereset kata sandi.
        </p>
        <div id="forgotStep1Error" class="auth-error-banner" style="display: none;"></div>
        <label>Email Terdaftar
          <input type="email" id="forgotEmail" placeholder="nama@email.com" required autocomplete="email">
        </label>
        <button class="primary auth-submit-btn" id="forgotStep1SubmitBtn" type="submit">
          Kirim Kode OTP <span>✉</span>
        </button>
      </form>

      <!-- Step 2: Input OTP & Password Baru -->
      <form id="forgotStep2Form" class="auth-form" style="display: none;">
        <div class="auth-info-banner" id="forgotStep2Notice">
          <span class="banner-icon">✉</span>
          <span>Kode OTP 6-digit telah dikirim ke <strong id="forgotNoticeEmail"></strong>. Berlaku selama 10 menit.</span>
        </div>
        <div id="forgotStep2Error" class="auth-error-banner" style="display: none;"></div>
        <label>Kode OTP (6 Digit)
          <input type="text" id="forgotOtp" placeholder="Contoh: 123456" maxlength="6" inputmode="numeric" required autocomplete="one-time-code" style="letter-spacing: 4px; font-weight: 700; text-align: center; font-size: 18px;">
        </label>
        <label>Kata Sandi Baru (Minimal 8 karakter)
          <div class="password-input-wrap">
            <input type="password" id="forgotNewPassword" placeholder="Minimal 8 karakter" required minlength="8" autocomplete="new-password">
            <button type="button" class="password-toggle-btn" data-target="forgotNewPassword" aria-label="Lihat kata sandi" title="Lihat/Sembunyikan kata sandi">
              <span class="eye-icon">👁</span>
            </button>
          </div>
        </label>
        <label>Konfirmasi Kata Sandi Baru
          <div class="password-input-wrap">
            <input type="password" id="forgotNewPasswordConfirmation" placeholder="Ulangi kata sandi baru" required minlength="8" autocomplete="new-password">
            <button type="button" class="password-toggle-btn" data-target="forgotNewPasswordConfirmation" aria-label="Lihat kata sandi" title="Lihat/Sembunyikan kata sandi">
              <span class="eye-icon">👁</span>
            </button>
          </div>
        </label>
        <button class="primary auth-submit-btn" id="forgotStep2SubmitBtn" type="submit">
          Perbarui Kata Sandi <span>✓</span>
        </button>
        <div class="auth-switch-hint">
          Tidak menerima kode? <button type="button" class="link-btn" id="resendOtpBtn">Kirim ulang OTP</button>
        </div>
      </form>
    </div>
  </div>

  <!-- YouTube Player for Special Mode BGM -->
  <div id="ytPlayerWrap" class="yt-player-wrap">
    <div id="ytPlayer"></div>
  </div>

  <div class="toast" id="toast">Transaksi tersimpan</div>
  <script src="{{ asset('qrcode.min.js') }}"></script>
  <!-- YouTube IFrame API -->
  <script src="https://www.youtube.com/iframe_api"></script>
  <script type="module" src="{{ asset('js/app.js') }}"></script>
</body>
</html>
