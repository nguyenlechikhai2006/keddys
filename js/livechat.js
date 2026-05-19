(function () {
    'use strict';

    const BOT_NAME  = 'Keddy Support';
    const BOT_EMOJI = '🐾';

    let isOpen       = false;
    let sessionId    = null;
    let lastId       = 0;
    let pollInterval = null;
    let isFetching   = false;   // tránh request chồng nhau
    let userId       = null;
    let username     = 'B';

    function initUser(callback) {
        fetch('api/get_session_user.php')
            .then(res => res.json())
            .then(data => {
                if (data.id) {
                    userId   = data.id;
                    username = data.username || 'B';
                }
                callback();
            })
            .catch(() => callback());
    }

    function createWidget() {
        initUser(function() {

            // Nút mở chat
            const btn = document.createElement('button');
            btn.id = 'keddy-chat-btn';
            btn.setAttribute('aria-label', 'Mở chat tư vấn');
            btn.innerHTML = `${BOT_EMOJI}<span class="kc-badge">Live</span>`;
            btn.addEventListener('click', toggleChat);

            // Cửa sổ chat
            const win = document.createElement('div');
            win.id = 'keddy-chat-window';
            win.innerHTML = `
                <div class="kc-header">
                    <div class="kc-avatar">${BOT_EMOJI}</div>
                    <div class="kc-header-info">
                        <div class="kc-header-name">${BOT_NAME}</div>
                        <div class="kc-header-status" id="kc-status">Đang hoạt động</div>
                    </div>
                    <button class="kc-close-btn" aria-label="Đóng chat">✕</button>
                </div>
                <div class="kc-messages" id="kc-messages"></div>
                <div class="kc-input-area" id="kc-input-area">
                    <textarea class="kc-input" id="kc-input" placeholder="Nhập tin nhắn..." rows="1" maxlength="500"></textarea>
                    <button class="kc-send-btn" id="kc-send-btn" aria-label="Gửi">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
                <div class="kc-footer-note">Chat trực tiếp với nhân viên Keddy</div>
            `;

            document.body.appendChild(btn);
            document.body.appendChild(win);

            win.querySelector('.kc-close-btn').addEventListener('click', closeChat);

            const inputEl = document.getElementById('kc-input');
            const sendEl  = document.getElementById('kc-send-btn');

            inputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
            });
            inputEl.addEventListener('input', () => {
                inputEl.style.height = 'auto';
                inputEl.style.height = Math.min(inputEl.scrollHeight, 90) + 'px';
            });
            sendEl.addEventListener('click', sendMessage);
        });
    }

    function toggleChat() { isOpen ? closeChat() : openChat(); }

    async function openChat() {
        isOpen = true;
        document.getElementById('keddy-chat-window').classList.add('kc-open');
        document.getElementById('keddy-chat-btn').style.animation = 'none';

        // Re-check session mỗi lần mở (phòng user đã logout)
        await new Promise(resolve => initUser(resolve));

        if (!userId) {
            document.getElementById('kc-messages').innerHTML = '';
            sessionId = null;
            lastId    = 0;
            stopPolling();
            document.getElementById('kc-input-area').style.display = 'none';
            appendSystemMessage('Bạn cần đăng nhập để chat với nhân viên nhé! 🔐');
            return;
        }

        // Có user: hiện lại input area phòng trước đó bị ẩn
        document.getElementById('kc-input-area').style.display = '';

        if (!sessionId) {
            await initSession();
        }

        startPolling();
        setTimeout(() => document.getElementById('kc-input')?.focus(), 350);
    }

    function closeChat() {
        isOpen = false;
        document.getElementById('keddy-chat-window').classList.remove('kc-open');
        document.getElementById('keddy-chat-btn').style.animation = 'kc-pulse 2.5s infinite';
        stopPolling();
    }

    async function initSession() {
        try {
            const res  = await fetch('api/chat_live.php?action=get_session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            });
            const data = await res.json();
            sessionId  = data.session_id;
            appendSystemMessage('Xin chào ' + username + '! 👋 Nhân viên Keddy sẽ hỗ trợ bạn ngay.');
            // FIX: load toàn bộ lịch sử tin nhắn (cả user lẫn admin) từ DB
            await fetchMessages();
        } catch (e) {
            appendSystemMessage('Không kết nối được server. Thử lại sau nhé!');
        }
    }

    async function sendMessage() {
        const input = document.getElementById('kc-input');
        const text  = input.value.trim();
        if (!text || !sessionId) return;
        input.value = '';
        input.style.height = 'auto';

        // FIX: Bỏ appendMessage('user', text) ở đây để tránh duplicate.
        // Tin nhắn sẽ được hiển thị qua fetchMessages() ngay sau khi gửi lên server.
        await fetch('api/chat_live.php?action=send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: sessionId, sender: 'user', message: text })
        });

        // FIX: Fetch ngay để hiện tin vừa gửi (không cần chờ polling 2s)
        await fetchMessages();
    }

    async function fetchMessages() {
        if (!sessionId || isFetching) return;
        isFetching = true;
        try {
            const res  = await fetch(`api/chat_live.php?action=get_messages&session_id=${sessionId}&last_id=${lastId}`);
            const data = await res.json();
            data.messages?.forEach(msg => {
                if (msg.sender === 'user' || msg.sender === 'admin') {
                    appendMessage(msg.sender, msg.message);
                }
                lastId = Math.max(lastId, parseInt(msg.id));
            });
        } catch (e) {}
        finally { isFetching = false; }
    }

    function startPolling() {
        stopPolling();
        // Chỉ poll khi chat đang mở
        pollInterval = setInterval(() => { if (isOpen) fetchMessages(); }, 3000);
    }

    function stopPolling() {
        if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
    }

    function appendMessage(sender, text) {
        const isUser = sender === 'user';
        const el = document.createElement('div');
        el.className = `kc-msg ${isUser ? 'kc-user' : 'kc-bot'}`;
        el.innerHTML = `
            <div class="kc-msg-avatar">${isUser ? username[0].toUpperCase() : BOT_EMOJI}</div>
            <div class="kc-bubble">${escapeHtml(text)}</div>
        `;
        appendToMessages(el);
    }

    function appendSystemMessage(text) {
        const el = document.createElement('div');
        el.style.cssText = 'text-align:center;color:#888;font-size:12px;padding:8px 14px;';
        el.textContent = text;
        appendToMessages(el);
    }

    function appendToMessages(el) {
        const c = document.getElementById('kc-messages');
        c.appendChild(el);
        c.scrollTop = c.scrollHeight;
    }

    function escapeHtml(t) {
        return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    }

    // Ẩn/hiện widget khi trạng thái đăng nhập thay đổi
    function resetWidget() {
        stopPolling();
        sessionId  = null;
        lastId     = 0;
        userId     = null;
        username   = 'B';
        isOpen     = false;
        isFetching = false;

        const win = document.getElementById('keddy-chat-window');
        const btn = document.getElementById('keddy-chat-btn');
        if (win) win.classList.remove('kc-open');
        if (btn) btn.style.display = 'none';

        // Re-check user mới
        initUser(function() {
            if (btn) btn.style.display = userId ? 'flex' : 'none';
        });
    }

    // Lắng nghe khi localStorage thay đổi (user đăng xuất từ tab khác hoặc profile.html)
    window.addEventListener('storage', function(e) {
        if (e.key === 'user' && !e.newValue) {
            resetWidget();
        }
        if (e.key === 'user' && e.newValue) {
            resetWidget(); // re-init với user mới
        }
    });

    // Cho phép gọi từ bên ngoài: window.keddyChatReset()
    window.keddyChatReset = resetWidget;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => createWidget());
    } else {
        createWidget();
    }
})();