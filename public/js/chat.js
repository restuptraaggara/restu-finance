// public/js/chat.js - Live Chat & Admin Support Center

import * as utils from './utils.js';
import * as api from './api.js';

// ============================================================
// State Sisi Klien (Floating Chat Widget)
// ============================================================
let clientChatOpen = false;
let clientPollingTimer = null;
let clientUnreadPollingTimer = null;
let clientMessages = [];

function formatChatTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '';
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  const today = new Date();
  const isToday = d.toDateString() === today.toDateString();
  if (isToday) {
    return `${hours}:${minutes}`;
  }
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  return `${day}/${month} ${hours}:${minutes}`;
}

export function renderClientMessageList() {
  const container = document.getElementById('chatMessagesList');
  if (!container) return;

  if (clientMessages.length === 0) {
    const isEn = window.state?.user?.language === 'en';
    container.innerHTML = `
      <div class="chat-empty-state">
        <div class="chat-empty-icon">👋</div>
        <strong>${isEn ? 'Hello!' : 'Halo! Ada yang bisa kami bantu?'}</strong>
        <p>${isEn ? 'Send a message or complaint to Restu Finance Admin here.' : 'Kirim pertanyaan, keluhan, atau saran langsung ke Admin Restu Finance di sini.'}</p>
      </div>
    `;
    return;
  }

  const html = clientMessages.map(msg => {
    const isAdmin = Boolean(msg.is_admin_reply);
    const isMe = !isAdmin;
    const senderName = isAdmin ? 'Admin Support' : (window.state?.user?.name || 'Anda');
    const time = formatChatTime(msg.created_at);
    const text = utils.escapeHtml(msg.message);

    return `
      <div class="chat-msg ${isMe ? 'msg-client' : 'msg-admin'}">
        <div class="chat-msg-sender">${utils.escapeHtml(senderName)}</div>
        <div class="chat-msg-bubble">${text}</div>
        <div class="chat-msg-time">${time}</div>
      </div>
    `;
  }).join('');

  container.innerHTML = html;
  container.scrollTop = container.scrollHeight;
}

export async function fetchClientMessages(silently = false) {
  try {
    const res = await api.apiFetch('/api/chat/messages');
    if (res && res.success) {
      clientMessages = res.data || [];
      renderClientMessageList();

      const badge = document.getElementById('chatFabBadge');
      const unreadCount = res.unread_count || 0;
      if (badge) {
        if (unreadCount > 0 && !clientChatOpen) {
          badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
          badge.style.display = 'inline-flex';
        } else {
          badge.style.display = 'none';
        }
      }

      if (clientChatOpen && unreadCount > 0) {
        markClientRead();
      }
    }
  } catch (err) {
    if (!silently) {
      console.warn('Error fetching client chat messages:', err);
    }
  }
}

export async function markClientRead() {
  try {
    await api.apiFetch('/api/chat/read', { method: 'POST' });
    const badge = document.getElementById('chatFabBadge');
    if (badge) badge.style.display = 'none';
  } catch (e) {
    console.warn('Failed marking chat as read:', e);
  }
}

export async function sendClientMessage(text) {
  if (!text || !text.trim()) return;
  const trimmed = text.trim();

  try {
    const res = await api.apiFetch('/api/chat/messages', {
      method: 'POST',
      body: JSON.stringify({ message: trimmed }),
    });

    if (res && res.success && res.data) {
      clientMessages.push(res.data);
      renderClientMessageList();
      const input = document.getElementById('chatInputField');
      if (input) {
        input.value = '';
        input.focus();
      }
    }
  } catch (err) {
    utils.toast(err.message || 'Gagal mengirim pesan.');
  }
}

export function openClientChat() {
  const popup = document.getElementById('chatPopup');
  const fab = document.getElementById('chatFabBtn');
  if (!popup) return;

  clientChatOpen = true;
  popup.style.display = 'flex';
  if (fab) fab.classList.add('active');

  const badge = document.getElementById('chatFabBadge');
  if (badge) badge.style.display = 'none';

  fetchClientMessages();
  markClientRead();

  // Jalankan polling setiap 5 detik saat popup terbuka
  if (clientPollingTimer) clearInterval(clientPollingTimer);
  clientPollingTimer = setInterval(() => {
    if (clientChatOpen) {
      fetchClientMessages(true);
    }
  }, 5000);

  const input = document.getElementById('chatInputField');
  if (input) setTimeout(() => input.focus(), 150);
}

export function closeClientChat() {
  const popup = document.getElementById('chatPopup');
  const fab = document.getElementById('chatFabBtn');
  if (!popup) return;

  clientChatOpen = false;
  popup.style.display = 'none';
  if (fab) fab.classList.remove('active');

  if (clientPollingTimer) {
    clearInterval(clientPollingTimer);
    clientPollingTimer = null;
  }
}

export function toggleClientChat() {
  if (clientChatOpen) {
    closeClientChat();
  } else {
    openClientChat();
  }
}

export function initClientChat() {
  const fabBtn = document.getElementById('chatFabBtn');
  const closeBtn = document.getElementById('chatCloseBtn');
  const form = document.getElementById('chatInputForm');
  const input = document.getElementById('chatInputField');

  if (fabBtn) {
    fabBtn.onclick = (e) => {
      e.stopPropagation();
      toggleClientChat();
    };
  }

  if (closeBtn) {
    closeBtn.onclick = (e) => {
      e.stopPropagation();
      closeClientChat();
    };
  }

  if (form) {
    form.onsubmit = async (e) => {
      e.preventDefault();
      if (!input) return;
      const val = input.value;
      await sendClientMessage(val);
    };
  }

  // Tutup popup chat saat klik di luar area popup
  document.addEventListener('click', (e) => {
    const popup = document.getElementById('chatPopup');
    if (clientChatOpen && popup && !popup.contains(e.target) && e.target !== fabBtn && !fabBtn?.contains(e.target)) {
      closeClientChat();
    }
  });

  // Polling unread badge setiap 25 detik di latar belakang
  if (clientUnreadPollingTimer) clearInterval(clientUnreadPollingTimer);
  clientUnreadPollingTimer = setInterval(() => {
    if (window.isAuthenticated && !clientChatOpen) {
      fetchClientMessages(true);
    }
  }, 25000);

  // Initial check
  if (window.isAuthenticated) {
    fetchClientMessages(true);
  }
}

// ============================================================
// Halaman & Logika Administrator (Admin Inbox & Live Support)
// ============================================================
let activeAdminUserId = null;
let adminConversations = [];
let adminActiveMessages = [];
let adminPollingTimer = null;

export function adminChat() {
  const isEn = window.state?.user?.language === 'en';

  return `
    <div class="admin-chat-layout">
      <!-- Sidebar Daftar Percakapan -->
      <div class="admin-chat-sidebar">
        <div class="admin-chat-sidebar-header">
          <div class="admin-sidebar-title">
            <span class="icon">💬</span>
            <strong>${isEn ? 'Support Inbox' : 'Inbox Percakapan'}</strong>
          </div>
          <button type="button" class="period" id="adminRefreshConvBtn" title="${isEn ? 'Refresh' : 'Segarkan'}">↻</button>
        </div>
        <div class="admin-chat-conv-list" id="adminConvList">
          <div class="admin-chat-empty">${isEn ? 'Loading conversations...' : 'Memuat daftar percakapan...'}</div>
        </div>
      </div>

      <!-- Area Pesan Aktif & Form Balas -->
      <div class="admin-chat-main">
        <div class="admin-chat-header" id="adminActiveChatHeader">
          <div class="admin-client-info" id="adminClientInfo">
            <div class="admin-client-avatar">?</div>
            <div>
              <strong id="adminClientName">${isEn ? 'Select a conversation' : 'Pilih percakapan'}</strong>
              <small id="adminClientEmail" class="muted">${isEn ? 'Click user on the left to start chatting' : 'Klik percakapan di kolom kiri untuk melihat pesan'}</small>
            </div>
          </div>
        </div>

        <div class="admin-chat-body" id="adminMessagesContainer">
          <div class="admin-chat-placeholder">
            <span style="font-size: 38px;">💬</span>
            <p>${isEn ? 'Select a user to read and reply to support messages.' : 'Pilih salah satu percakapan pengguna untuk membaca dan membalas pesan bantuan.'}</p>
          </div>
        </div>

        <form class="admin-chat-reply-form" id="adminReplyForm" style="display: none;">
          <input type="text" id="adminReplyInput" placeholder="${isEn ? 'Type reply to user...' : 'Ketik balasan untuk pengguna ini...'}" maxlength="2000" autocomplete="off" required>
          <button type="submit" class="primary admin-send-btn" id="adminSendReplyBtn">
            <span>${isEn ? 'Reply' : 'Kirim Balasan'}</span> <span>↗</span>
          </button>
        </form>
      </div>
    </div>
  `;
}

export function renderAdminConversations() {
  const listEl = document.getElementById('adminConvList');
  if (!listEl) return;

  const isEn = window.state?.user?.language === 'en';

  if (adminConversations.length === 0) {
    listEl.innerHTML = `<div class="admin-chat-empty">${isEn ? 'No messages received yet.' : 'Belum ada percakapan masuk.'}</div>`;
    return;
  }

  let totalUnread = 0;
  const itemsHtml = adminConversations.map(conv => {
    const isActive = activeAdminUserId === conv.user_id;
    const unread = conv.unread_count || 0;
    totalUnread += unread;
    const initials = (conv.user_name || 'U').split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase();
    const lastTime = formatChatTime(conv.last_message_at);
    const lastPreview = utils.escapeHtml(conv.last_message || '');

    return `
      <div class="admin-conv-item ${isActive ? 'active' : ''} ${unread > 0 ? 'unread' : ''}" data-user-id="${conv.user_id}">
        <div class="admin-conv-avatar">${initials}</div>
        <div class="admin-conv-details">
          <div class="admin-conv-top">
            <strong class="admin-conv-name">${utils.escapeHtml(conv.user_name)}</strong>
            <span class="admin-conv-time">${lastTime}</span>
          </div>
          <div class="admin-conv-bottom">
            <span class="admin-conv-snippet">${conv.last_is_admin_reply ? '<i style="opacity:0.75;">(Anda): </i>' : ''}${lastPreview}</span>
            ${unread > 0 ? `<span class="badge admin-conv-unread">${unread}</span>` : ''}
          </div>
        </div>
      </div>
    `;
  }).join('');

  listEl.innerHTML = itemsHtml;

  // Update badge di nav sidebar jika ada
  const navBadge = document.getElementById('adminNavUnreadBadge');
  if (navBadge) {
    if (totalUnread > 0) {
      navBadge.textContent = totalUnread > 99 ? '99+' : totalUnread;
      navBadge.style.display = 'inline-block';
    } else {
      navBadge.style.display = 'none';
    }
  }

  // Bind click pada masing-masing percakapan
  listEl.querySelectorAll('.admin-conv-item').forEach(item => {
    item.onclick = () => {
      const uId = Number(item.dataset.userId);
      const targetConv = adminConversations.find(c => c.user_id === uId);
      if (targetConv) {
        selectAdminConversation(targetConv.user_id, targetConv.user_name, targetConv.user_email);
      }
    };
  });
}

export function renderAdminActiveMessages() {
  const container = document.getElementById('adminMessagesContainer');
  if (!container) return;

  const isEn = window.state?.user?.language === 'en';

  if (!activeAdminUserId) {
    container.innerHTML = `
      <div class="admin-chat-placeholder">
        <span style="font-size: 38px;">💬</span>
        <p>${isEn ? 'Select a user to read and reply to support messages.' : 'Pilih salah satu percakapan pengguna untuk membaca dan membalas pesan bantuan.'}</p>
      </div>
    `;
    return;
  }

  if (adminActiveMessages.length === 0) {
    container.innerHTML = `
      <div class="admin-chat-placeholder">
        <p>${isEn ? 'No messages in this conversation yet.' : 'Belum ada pesan dalam percakapan ini.'}</p>
      </div>
    `;
    return;
  }

  const html = adminActiveMessages.map(msg => {
    const isAdmin = Boolean(msg.is_admin_reply);
    const senderName = isAdmin ? 'Admin' : (msg.sender?.name || 'Klien');
    const time = formatChatTime(msg.created_at);
    const text = utils.escapeHtml(msg.message);

    return `
      <div class="chat-msg ${isAdmin ? 'msg-admin' : 'msg-client'}">
        <div class="chat-msg-sender">${utils.escapeHtml(senderName)}</div>
        <div class="chat-msg-bubble">${text}</div>
        <div class="chat-msg-time">${time}</div>
      </div>
    `;
  }).join('');

  container.innerHTML = html;
  container.scrollTop = container.scrollHeight;
}

export async function fetchAdminConversations(silently = false) {
  try {
    const res = await api.apiFetch('/api/admin/chat/conversations');
    if (res && res.success) {
      adminConversations = res.data || [];
      renderAdminConversations();
    }
  } catch (err) {
    if (!silently) {
      console.warn('Error fetching admin conversations:', err);
    }
  }
}

export async function selectAdminConversation(userId, userName, userEmail) {
  activeAdminUserId = userId;

  // Perbarui header
  const nameEl = document.getElementById('adminClientName');
  const emailEl = document.getElementById('adminClientEmail');
  const avatarEl = document.querySelector('.admin-client-avatar');
  const formEl = document.getElementById('adminReplyForm');

  if (nameEl) nameEl.textContent = userName;
  if (emailEl) emailEl.textContent = userEmail || '';
  if (avatarEl) {
    avatarEl.textContent = (userName || 'U').split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase();
  }
  if (formEl) formEl.style.display = 'flex';

  renderAdminConversations();

  // Ambil pesan percakapan
  try {
    const res = await api.apiFetch(`/api/admin/chat/${userId}/messages`);
    if (res && res.success) {
      adminActiveMessages = res.data || [];
      renderAdminActiveMessages();

      // Tandai telah dibaca oleh admin
      await api.apiFetch(`/api/admin/chat/${userId}/read`, { method: 'POST' });
      // Perbarui status unread lokal
      const conv = adminConversations.find(c => c.user_id === userId);
      if (conv) conv.unread_count = 0;
      renderAdminConversations();
    }
  } catch (err) {
    utils.toast(err.message || 'Gagal memuat pesan klien.');
  }

  const input = document.getElementById('adminReplyInput');
  if (input) setTimeout(() => input.focus(), 100);
}

export async function sendAdminReply(text) {
  if (!activeAdminUserId || !text || !text.trim()) return;
  const trimmed = text.trim();

  try {
    const res = await api.apiFetch(`/api/admin/chat/${activeAdminUserId}/reply`, {
      method: 'POST',
      body: JSON.stringify({ message: trimmed }),
    });

    if (res && res.success && res.data) {
      adminActiveMessages.push(res.data);
      renderAdminActiveMessages();

      // Update conversation last message
      const conv = adminConversations.find(c => c.user_id === activeAdminUserId);
      if (conv) {
        conv.last_message = trimmed;
        conv.last_message_at = res.data.created_at;
        conv.last_is_admin_reply = true;
        renderAdminConversations();
      }

      const input = document.getElementById('adminReplyInput');
      if (input) {
        input.value = '';
        input.focus();
      }
    }
  } catch (err) {
    utils.toast(err.message || 'Gagal mengirim balasan.');
  }
}

export function initAdminChat() {
  const refreshBtn = document.getElementById('adminRefreshConvBtn');
  if (refreshBtn) {
    refreshBtn.onclick = () => fetchAdminConversations();
  }

  const replyForm = document.getElementById('adminReplyForm');
  const replyInput = document.getElementById('adminReplyInput');
  if (replyForm) {
    replyForm.onsubmit = async (e) => {
      e.preventDefault();
      if (!replyInput) return;
      await sendAdminReply(replyInput.value);
    };
  }

  fetchAdminConversations();

  // Polling admin setiap 6 detik ketika berada di halaman adminChat
  if (adminPollingTimer) clearInterval(adminPollingTimer);
  adminPollingTimer = setInterval(() => {
    if (window.currentPage === 'adminChat') {
      fetchAdminConversations(true);
      if (activeAdminUserId) {
        api.apiFetch(`/api/admin/chat/${activeAdminUserId}/messages`)
          .then(res => {
            if (res && res.success) {
              adminActiveMessages = res.data || [];
              renderAdminActiveMessages();
            }
          })
          .catch(() => {});
      }
    }
  }, 6000);
}

