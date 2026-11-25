/**
 * HWCall & HWComm - Unified WebSocket + WebRTC Video/Voice + Real-Time Chat Hub
 */
(function (global) {
    const ICE_SERVERS = [{ urls: 'stun:stun.l.google.com:19302'}];
    const WS_PORT = 8080;
    const INCOMING_POLL_MS = 3000;
    const SIGNAL_POLL_MS = 1400;
    const CHAT_POLL_MS = 2500;
    const RING_TIMEOUT_MS = 45000;

    let myRole = null; // 'owner'| 'vet'| 'admin'
    let myActor = null;
    let myWsToken = null;
    const watched = new Map(); // bookingId ->label
    let incomingTimer = null;
    let incomingIndex = 0;

    // WebSocket state
    let ws = null;
    let wsConnected = false;
    let wsReconnectTimer = null;

    // WebRTC state
    let pc = null;
    let localStream = null;
    let active = null; // { callId, bookingId, callType, isInitiator, label }
    let lastCandidateId = 0;
    let signalTimer = null;
    let ringTimeoutTimer = null;
    let pendingIncoming = null;

    // Chat drawer state
    let chatOpen = false;
    let channels = [];
    let currentChannelKey = null;
    let lastMessageIdByChannel = {};
    let chatPollTimer = null;
    let typingTimeout = null;

    function apiPath(relPath) {
        const isInAdmin = window.location.pathname.indexOf('/admin/') !== -1;
        if (isInAdmin && !relPath.startsWith('../') && !relPath.startsWith('/')) {
            return '../'+ relPath;
        }
        return relPath;
    }

    // ---------------------------------------------------------------
    // 1. WebSocket Connection & Hybrid Sync
    // ---------------------------------------------------------------
    function connectWebSocket() {
        if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) {
            return;
        }
        const proto = window.location.protocol === 'https:'? 'wss:': 'ws:';
        const host = window.location.hostname || 'localhost';
        const wsUrl = `${proto}//${host}:${WS_PORT}`;

        try {
            ws = new WebSocket(wsUrl);
        } catch (e) {
            updateWsBadge(false);
            return;
        }

        ws.onopen = function () {
            wsConnected = true;
            updateWsBadge(true);
            if (myWsToken) {
                wsSend({
                    type: 'auth',
                    token: myWsToken,
                });
            }
            if (currentChannelKey) {
                wsSend({ type: 'subscribe', channel_key: currentChannelKey });
            }
        };

        ws.onmessage = function (evt) {
            let packet = null;
            try {
                packet = JSON.parse(evt.data);
            } catch (e) {
                return;
            }
            if (!packet || !packet.type) return;

            if (packet.type === 'chat_message') {
                if (packet.channel_key === currentChannelKey && packet.message) {
                    appendChatMessageUI(packet.message);
                } else {
                    incrementUnreadBadge();
                }
            } else if (packet.type === 'typing') {
                if (packet.channel_key === currentChannelKey) {
                    showTypingIndicator(packet.sender_name || 'Someone');
                }
            } else if (packet.type === 'webrtc_signal') {
                // Trigger an immediate signal poll so DB state + WebRTC stay 100% synchronized
                if (active && packet.call_id && Number(packet.call_id) === Number(active.callId)) {
                    pollActiveCall();
                } else if (!active && packet.signal_type === 'offer') {
                    pollForIncoming();
                }
            } else if (packet.type === 'triage_broadcast') {
                if (typeof window.onVetTriageBroadcast === 'function') {
                    window.onVetTriageBroadcast(packet.summary);
                }
            }
        };

        ws.onclose = function () {
            wsConnected = false;
            updateWsBadge(false);
            clearTimeout(wsReconnectTimer);
            wsReconnectTimer = setTimeout(connectWebSocket, 6000);
        };

        ws.onerror = function () {
            wsConnected = false;
            updateWsBadge(false);
        };
    }

    function wsSend(payload) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            try {
                ws.send(JSON.stringify(payload));
            } catch (e) { /* ignore */ }
        }
    }

    function updateWsBadge(isLive) {
        const badge = document.getElementById('hwWsStatusBadge');
        if (!badge) return;
        if (isLive) {
            badge.textContent = 'WebSocket Live';
            badge.style.background = '#174E36';
            badge.style.color = '#6EE7B7';
        } else {
            badge.textContent = 'Hybrid Real-Time Sync';
            badge.style.background = '#1E3A4C';
            badge.style.color = '#93C5FD';
        }
    }

    // ---------------------------------------------------------------
    // 2. Unified Video/Audio Call Modal + Floating Chat Drawer UI
    // ---------------------------------------------------------------
    function ensureUI() {
        if (document.getElementById('hwCallModal')) return;

        // A. Call Modal (with side-by-side Video + Quick Case Chat)
        const wrap = document.createElement('div');
        wrap.id = 'hwCallModal';
        wrap.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;background:rgba(10,20,25,.88);align-items:center;justify-content:center;padding:16px;';
        wrap.innerHTML = `
          <div style="background:#16262A;color:#fff;border-radius:16px;padding:18px;width:min(95vw,520px);box-shadow:0 20px 60px rgba(0,0,0,.5);font-family:system-ui,-apple-system,sans-serif;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <div id="hwCallLabel" style="font-weight:700;font-size:17px;"></div>
              <button type="button" id="hwBtnOpenCaseChat" style="background:#2B4E52;color:#E4EDEC;border:none;padding:5px 11px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">Open Case Chat</button>
            </div>
            <div id="hwCallStatus" style="color:#9fb3b6;font-size:13px;margin-bottom:12px;"></div>
            <div style="position:relative;background:#091114;border-radius:12px;overflow:hidden;aspect-ratio:4/3;border:1px solid rgba(255,255,255,0.1);">
              <video id="hwRemoteVideo"autoplay playsinline style="width:100%;height:100%;object-fit:cover;"></video>
              <div id="hwAudioAvatar" style="display:none;position:absolute;inset:0;align-items:center;justify-content:center;font-size:56px;"></div>
              <video id="hwLocalVideo"autoplay playsinline muted style="position:absolute;bottom:10px;right:10px;width:96px;border-radius:8px;border:2px solid #4A9DBB;display:none;"></video>
            </div>
            <div id="hwCallControls" style="display:flex;gap:10px;justify-content:center;margin-top:16px;flex-wrap:wrap;">
              <button id="hwBtnAccept" style="display:none;background:#4CAF7D;color:#fff;border:none;padding:10px 18px;border-radius:999px;font-weight:600;cursor:pointer;">Accept Call</button>
              <button id="hwBtnDecline" style="display:none;background:#D8546F;color:#fff;border:none;padding:10px 18px;border-radius:999px;font-weight:600;cursor:pointer;">Decline</button>
              <button id="hwBtnMute" style="display:none;background:#2B4E52;color:#fff;border:none;padding:10px 18px;border-radius:999px;font-weight:600;cursor:pointer;">Mute Mic</button>
              <button id="hwBtnCam" style="display:none;background:#2B4E52;color:#fff;border:none;padding:10px 18px;border-radius:999px;font-weight:600;cursor:pointer;">Camera Off</button>
              <button id="hwBtnHangup" style="display:none;background:#D8546F;color:#fff;border:none;padding:10px 18px;border-radius:999px;font-weight:600;cursor:pointer;">End Call</button>
            </div>
          </div>`;
        document.body.appendChild(wrap);

        document.getElementById('hwBtnAccept').onclick = acceptIncoming;
        document.getElementById('hwBtnDecline').onclick = () => declineOrHangup('declined');
        document.getElementById('hwBtnHangup').onclick = () => declineOrHangup('ended');
        document.getElementById('hwBtnMute').onclick = toggleMute;
        document.getElementById('hwBtnCam').onclick = toggleCamera;
        document.getElementById('hwBtnOpenCaseChat').onclick = () =>{
            if (active && active.bookingId) {
                openChatDrawer('booking_'+ active.bookingId);
            } else {
                openChatDrawer();
            }
        };

        // B. Floating Real-Time Chat & Video Launcher + Drawer
        const launcher = document.createElement('button');
        launcher.id = 'hwChatLauncher';
        launcher.type = 'button';
        launcher.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99990;background:linear-gradient(135deg,#1F3A3D,#4A9DBB);color:#fff;border:none;border-radius:999px;padding:12px 20px;font-weight:700;font-size:13.5px;box-shadow:0 10px 28px rgba(15,35,42,0.32);cursor:pointer;display:flex;align-items:center;gap:8px;font-family:system-ui,-apple-system,sans-serif;';
        launcher.innerHTML = `
            <span>Live Chat & Video</span>
            <span id="hwUnreadBadge" style="display:none;background:#D8546F;color:#fff;font-size:11px;padding:2px 7px;border-radius:999px;">0</span>
        `;
        launcher.onclick = () => toggleChatDrawer();
        document.body.appendChild(launcher);

        const drawer = document.createElement('div');
        drawer.id = 'hwChatDrawer';
        drawer.style.cssText = 'display:none;position:fixed;bottom:76px;right:20px;z-index:99995;width:min(94vw,410px);height:min(78vh,560px);background:#FFFFFF;border:1px solid #D5E3E6;border-radius:16px;box-shadow:0 20px 50px rgba(15,35,42,0.28);flex-direction:column;overflow:hidden;font-family:system-ui,-apple-system,sans-serif;color:#16262A;';
        drawer.innerHTML = `
          <div style="background:#1F3A3D;color:#fff;padding:13px 16px;display:flex;justify-content:space-between;align-items:center;">
            <div>
              <div style="font-weight:800;font-size:14.5px;display:flex;align-items:center;gap:8px;">
                <span>HealthWing Comm Hub</span>
                <span id="hwWsStatusBadge" style="font-size:10.5px;padding:2px 8px;border-radius:999px;background:#1E3A4C;color:#93C5FD;font-weight:600;">Connecting</span>
              </div>
              <div style="font-size:11.5px;color:#9FBEC0;margin-top:2px;">Direct WebSocket Chat & WebRTC Video/Voice</div>
            </div>
            <button type="button" id="hwCloseDrawerBtn" style="background:transparent;border:none;color:#9FBEC0;font-size:20px;cursor:pointer;line-height:1;">&times;</button>
          </div>

          <div style="padding:10px 12px;background:#F1F7F8;border-bottom:1px solid #E3EBEC;display:flex;gap:8px;align-items:center;">
            <select id="hwChannelSelect" style="flex:1;padding:7px 10px;border:1px solid #C8D8DC;border-radius:8px;font-size:12.5px;background:#fff;color:#16262A;font-weight:600;"></select>
            <button type="button" id="hwDrawerVideoBtn" title="Start Video Call" style="background:#4A9DBB;color:#fff;border:none;padding:7px 10px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">Video</button>
            <button type="button" id="hwDrawerAudioBtn" title="Start Voice Call" style="background:#2B4E52;color:#fff;border:none;padding:7px 10px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">Audio</button>
          </div>

          <div id="hwChatMessages" style="flex:1;overflow-y:auto;padding:12px;background:#F8FBFB;display:flex;flex-direction:column;gap:8px;font-size:13px;">
            ${window.HWBone ? HWBone.chatBubbles() : "Loading conversation..."}
          </div>

          <div id="hwTypingBar" style="display:none;padding:4px 14px;font-size:11.5px;color:#4A9DBB;background:#F1F7F8;font-style:italic;"></div>

          <form id="hwChatForm" style="display:flex;gap:8px;padding:10px 12px;border-top:1px solid #E3EBEC;background:#fff;">
            <input type="text" id="hwChatInput" placeholder="Type a message to the clinic/client..." autocomplete="off"
                   style="flex:1;padding:9px 12px;border:1px solid #C8D8DC;border-radius:999px;font-size:13px;outline:none;"/>
            <button type="submit" style="background:#1F3A3D;color:#fff;border:none;padding:9px 16px;border-radius:999px;font-weight:700;font-size:12.5px;cursor:pointer;">Send</button>
          </form>
        `;
        document.body.appendChild(drawer);

        document.getElementById('hwCloseDrawerBtn').onclick = () => toggleChatDrawer(false);
        document.getElementById('hwChannelSelect').onchange = function () {
            switchChannel(this.value);
        };
        document.getElementById('hwChatInput').addEventListener('input', function () {
            if (currentChannelKey) {
                wsSend({ type: 'typing', channel_key: currentChannelKey });
            }
        });
        document.getElementById('hwChatForm').onsubmit = function (e) {
            e.preventDefault();
            sendCurrentChatMessage();
        };

        document.getElementById('hwDrawerVideoBtn').onclick = () => triggerCallFromCurrentChannel('video');
        document.getElementById('hwDrawerAudioBtn').onclick = () => triggerCallFromCurrentChannel('audio');
    }

    function triggerCallFromCurrentChannel(callType) {
        const ch = channels.find(c => c.channel_key === currentChannelKey);
        if (ch && ch.booking_id) {
            startCall(ch.booking_id, callType, ch.title);
            return;
        }
        // If owner is in general channel or schedule channel, create/reuse a direct contact call booking
        if (myRole === 'owner') {
            fetch(apiPath('start_contact_call.php'), { method: 'POST'})
                .then(r => r.json())
                .then(data =>{
                    if (data.success && data.bookingId) {
                        startCall(data.bookingId, callType, 'On-Call Veterinarian');
                    } else {
                        alert(data.message || 'Could not start call.');
                    }
                })
                .catch(() => alert('Network error starting call.'));
        } else {
            alert('Select an Emergency Case channel from the dropdown to ring that client directly.');
        }
    }

    // ---------------------------------------------------------------
    // 3. Chat Drawer Logic (Channels, History, Send)
    // ---------------------------------------------------------------
    function toggleChatDrawer(forceState) {
        ensureUI();
        const drawer = document.getElementById('hwChatDrawer');
        chatOpen = typeof forceState === 'boolean'? forceState : !chatOpen;
        drawer.style.display = chatOpen ? 'flex': 'none';
        if (chatOpen) {
            const badge = document.getElementById('hwUnreadBadge');
            if (badge) {
                badge.style.display = 'none';
                badge.textContent = '0';
            }
            loadChannelsAndHistory();
        }
    }

    function openChatDrawer(preferredChannelKey) {
        ensureUI();
        if (preferredChannelKey) {
            currentChannelKey = preferredChannelKey;
        }
        toggleChatDrawer(true);
    }

    function incrementUnreadBadge() {
        if (chatOpen) return;
        const badge = document.getElementById('hwUnreadBadge');
        if (!badge) return;
        const cur = parseInt(badge.textContent || '0', 10) || 0;
        badge.textContent = String(cur + 1);
        badge.style.display = 'inline-block';
    }

    async function loadChannelsAndHistory() {
        try {
            const res = await fetch(apiPath('api/chat_api.php?action=channels'));
            if (!res.ok) {
                document.getElementById('hwChatMessages').innerHTML =
                    `<div style="color:#62787B;text-align:center;margin-top:30px;padding:12px;">Sign in as a Pet Owner, Staff Veterinarian, or Head Veterinarian to use real-time chat & video consults.</div>`;
                return;
            }
            const data = await res.json();
            if (!data.success) return;

            myActor = data.actor;
            myWsToken = data.ws_token || null;
            channels = data.channels || [];
            const sel = document.getElementById('hwChannelSelect');
            if (channels.length === 0) {
                sel.innerHTML = `<option value="">No active channels yet</option>`;
                document.getElementById('hwChatMessages').innerHTML =
                    `<div style="color:#62787B;text-align:center;margin-top:30px;">No active conversation channels yet.</div>`;
                return;
            }

            if (!currentChannelKey || !channels.some(c => c.channel_key === currentChannelKey)) {
                currentChannelKey = channels[0].channel_key;
            }

            sel.innerHTML = channels.map(c =>
                `<option value="${c.channel_key}"${c.channel_key === currentChannelKey ? 'selected': ''}>${c.title}</option>`
            ).join('');

            connectWebSocket();
            await switchChannel(currentChannelKey);

            if (!chatPollTimer) {
                chatPollTimer = setInterval(pollChatMessages, CHAT_POLL_MS);
            }
        } catch (e) { /* ignore */ }
    }

    async function switchChannel(channelKey) {
        if (!channelKey) return;
        currentChannelKey = channelKey;
        lastMessageIdByChannel[channelKey] = 0;
        const box = document.getElementById('hwChatMessages');
        box.innerHTML = `${window.HWBone ? HWBone.chatBubbles() : "Loading messages..."}`;
        wsSend({ type: 'subscribe', channel_key: channelKey });
        await pollChatMessages(true);
    }

    async function pollChatMessages(isInitial) {
        if (!currentChannelKey) return;
        const sinceId = lastMessageIdByChannel[currentChannelKey] || 0;
        try {
            const res = await fetch(apiPath(`api/chat_api.php?action=history&channel_key=${encodeURIComponent(currentChannelKey)}&since_id=${sinceId}`));
            const data = await res.json();
            if (!data.success) return;
            const box = document.getElementById('hwChatMessages');
            if (isInitial) {
                box.innerHTML = '';
                if (!data.messages || data.messages.length === 0) {
                    box.innerHTML = `<div id="hwEmptyChatHint" style="color:#62787B;text-align:center;margin-top:28px;font-size:12.5px;">No messages in this channel yet. Say hello or start a video consult above!</div>`;
                }
            }
            (data.messages || []).forEach(m => appendChatMessageUI(m));
        } catch (e) { /* ignore */ }
    }

    function appendChatMessageUI(m) {
        const box = document.getElementById('hwChatMessages');
        if (!box) return;
        const emptyHint = document.getElementById('hwEmptyChatHint');
        if (emptyHint) emptyHint.remove();

        const msgId = Number(m.Message_ID || 0);
        if (msgId && msgId <= (lastMessageIdByChannel[currentChannelKey] || 0)) {
            return;
        }
        if (msgId) {
            lastMessageIdByChannel[currentChannelKey] = msgId;
        }

        const isMine = myActor && m.Sender_Role === myActor.role && Number(m.Sender_ID) === Number(myActor.id);
        const isSystem = m.Sender_Role === 'system'|| m.Message_Type === 'triage_notice';

        const item = document.createElement('div');
        if (isSystem) {
            item.style.cssText = 'background:#FFF8E7;border:1px solid #F5D487;color:#7A5200;padding:8px 11px;border-radius:10px;font-size:12px;line-height:1.4;';
            item.innerHTML = `<strong style="display:block;margin-bottom:2px;">${escapeHtml(m.Sender_Name || 'System')}</strong>${escapeHtml(m.Message_Text)}`;
        } else {
            item.style.cssText = `align-self:${isMine ? 'flex-end': 'flex-start'};max-width:82%;background:${isMine ? '#1F3A3D': '#E7F3F7'};color:${isMine ? '#FFFFFF': '#16262A'};padding:8px 12px;border-radius:12px;line-height:1.38;`;
            item.innerHTML = `
              <div style="font-size:10.5px;opacity:0.75;margin-bottom:2px;font-weight:700;">
                ${escapeHtml(m.Sender_Name || m.Sender_Role)} · ${escapeHtml((m.Created_At || '').slice(11, 16))}
              </div>
              <div>${escapeHtml(m.Message_Text)}</div>
            `;
        }
        box.appendChild(item);
        box.scrollTop = box.scrollHeight;
    }

    function showTypingIndicator(name) {
        const bar = document.getElementById('hwTypingBar');
        if (!bar) return;
        bar.textContent = `${name} is typing...`;
        bar.style.display = 'block';
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() =>{
            bar.style.display = 'none';
        }, 2200);
    }

    async function sendCurrentChatMessage() {
        const input = document.getElementById('hwChatInput');
        const text = (input.value || '').trim();
        if (!text || !currentChannelKey) return;
        input.value = '';

        try {
            const res = await fetch(apiPath('api/chat_api.php?action=send'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json'},
                body: JSON.stringify({
                    channel_key: currentChannelKey,
                    message_text: text,
                    message_type: 'text',
                }),
            });
            const data = await res.json();
            if (data.success && data.message) {
                appendChatMessageUI(data.message);
                wsSend({
                    type: 'chat_message',
                    channel_key: currentChannelKey,
                    message_text: text,
                    message: data.message,
                });
            }
        } catch (e) { /* ignore */ }
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ---------------------------------------------------------------
    // 4. WebRTC Call Signaling + Media Handling
    // ---------------------------------------------------------------
    function showCallModal({ label, status, callType, mode }) {
        ensureUI();
        document.getElementById('hwCallLabel').textContent = label;
        document.getElementById('hwCallStatus').textContent = status;
        document.getElementById('hwAudioAvatar').style.display = callType === 'audio'? 'flex': 'none';
        document.getElementById('hwLocalVideo').style.display = (callType === 'video'&& mode !== 'incoming') ? 'block': 'none';

        document.getElementById('hwBtnAccept').style.display = mode === 'incoming'? 'inline-block': 'none';
        document.getElementById('hwBtnDecline').style.display = mode === 'incoming'? 'inline-block': 'none';
        document.getElementById('hwBtnMute').style.display = mode === 'in-call'? 'inline-block': 'none';
        document.getElementById('hwBtnCam').style.display = (mode === 'in-call'&& callType === 'video') ? 'inline-block': 'none';
        document.getElementById('hwBtnHangup').style.display = mode !== 'incoming'? 'inline-block': 'none';
        document.getElementById('hwBtnMute').textContent = 'Mute Mic';
        document.getElementById('hwBtnCam').textContent = 'Camera Off';

        document.getElementById('hwCallModal').style.display = 'flex';
    }

    function hideCallModal() {
        const m = document.getElementById('hwCallModal');
        if (m) m.style.display = 'none';
    }

    function setCallStatus(text) {
        const el = document.getElementById('hwCallStatus');
        if (el) el.textContent = text;
    }

    async function acquireMedia(callType) {
        const constraints = callType === 'video'
            ? { audio: true, video: { width: { ideal: 640 }, height: { ideal: 480 } } }
            : { audio: true, video: false };
        localStream = await navigator.mediaDevices.getUserMedia(constraints);
        const lv = document.getElementById('hwLocalVideo');
        if (lv && callType === 'video') {
            lv.srcObject = localStream;
        }
    }

    function createPeerConnection(callId, bookingId) {
        pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });

        if (localStream) {
            localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
        }

        pc.ontrack = (event) =>{
            const rv = document.getElementById('hwRemoteVideo');
            if (rv && event.streams && event.streams[0]) {
                rv.srcObject = event.streams[0];
            }
        };

        pc.onicecandidate = (event) =>{
            if (!event.candidate || !active) return;
            const candJson = JSON.stringify(event.candidate);
            fetch(apiPath('call_signal.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'ice',
                    call_id: callId,
                    candidate: candJson,
                }),
            }).catch(() =>{});

            wsSend({
                type: 'webrtc_signal',
                signal_type: 'ice',
                booking_id: bookingId,
                call_id: callId,
                payload: candJson,
            });
        };

        pc.onconnectionstatechange = () =>{
            if (!pc) return;
            if (pc.connectionState === 'connected') {
                setCallStatus('Connected (Encrypted P2P Stream)');
            } else if (pc.connectionState === 'failed'|| pc.connectionState === 'disconnected') {
                setCallStatus('Connection lost');
            }
        };
    }

    function cleanupCall() {
        clearInterval(signalTimer);
        signalTimer = null;
        clearTimeout(ringTimeoutTimer);
        ringTimeoutTimer = null;

        if (pc) {
            try { pc.close(); } catch (e) {}
            pc = null;
        }
        if (localStream) {
            localStream.getTracks().forEach(t => t.stop());
            localStream = null;
        }
        const rv = document.getElementById('hwRemoteVideo');
        const lv = document.getElementById('hwLocalVideo');
        if (rv) rv.srcObject = null;
        if (lv) lv.srcObject = null;

        active = null;
        pendingIncoming = null;
        lastCandidateId = 0;
        hideCallModal();
    }

    function toggleMute() {
        if (!localStream) return;
        const tracks = localStream.getAudioTracks();
        if (!tracks.length) return;
        tracks[0].enabled = !tracks[0].enabled;
        document.getElementById('hwBtnMute').textContent = tracks[0].enabled ? 'Mute Mic': 'Unmute Mic';
    }

    function toggleCamera() {
        if (!localStream) return;
        const tracks = localStream.getVideoTracks();
        if (!tracks.length) return;
        tracks[0].enabled = !tracks[0].enabled;
        document.getElementById('hwBtnCam').textContent = tracks[0].enabled ? 'Camera Off': 'Camera On';
    }

    async function startCall(bookingId, callType, peerLabel) {
        if (active || pendingIncoming) {
            alert('A call is already in progress.');
            return;
        }

        const label = `${callType === 'video'? 'Video': 'Audio'} call with ${peerLabel}`;
        showCallModal({ label, status: 'Starting camera/microphone...', callType, mode: 'in-call'});

        try {
            await acquireMedia(callType);
        } catch (err) {
            alert('Could not access your microphone/camera. Please check browser permissions.');
            cleanupCall();
            return;
        }

        pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
        localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

        const pendingLocalCandidates = [];
        pc.ontrack = (event) =>{
            const rv = document.getElementById('hwRemoteVideo');
            if (rv && event.streams && event.streams[0]) rv.srcObject = event.streams[0];
        };
        pc.onicecandidate = (event) =>{
            if (!event.candidate) return;
            if (active && active.callId) {
                const candJson = JSON.stringify(event.candidate);
                fetch(apiPath('call_signal.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'ice',
                        call_id: active.callId,
                        candidate: candJson,
                    }),
                }).catch(() =>{});
                wsSend({
                    type: 'webrtc_signal',
                    signal_type: 'ice',
                    booking_id: bookingId,
                    call_id: active.callId,
                    payload: candJson,
                });
            } else {
                pendingLocalCandidates.push(event.candidate);
            }
        };

        try {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            setCallStatus('Ringing...');
            const res = await fetch(apiPath('call_start.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json'},
                body: JSON.stringify({
                    booking_id: bookingId,
                    call_type: callType,
                    sdp_offer: JSON.stringify(offer),
                }),
            });
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Could not start the call.');
                cleanupCall();
                return;
            }

            active = { callId: data.call_id, bookingId, callType, isInitiator: true, label, answered: false };

            wsSend({
                type: 'webrtc_signal',
                signal_type: 'offer',
                booking_id: bookingId,
                call_id: data.call_id,
                call_type: callType,
            });

            for (const c of pendingLocalCandidates) {
                fetch(apiPath('call_signal.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'ice',
                        call_id: active.callId,
                        candidate: JSON.stringify(c),
                    }),
                }).catch(() =>{});
            }

            signalTimer = setInterval(pollActiveCall, SIGNAL_POLL_MS);
            ringTimeoutTimer = setTimeout(() =>{
                if (active && !active.answered) {
                    declineOrHangup('missed');
                }
            }, RING_TIMEOUT_MS);
        } catch (err) {
            alert('Error starting call.');
            cleanupCall();
        }
    }

    function pollForIncoming() {
        if (active || pendingIncoming || watched.size === 0) return;

        const entries = Array.from(watched.entries());
        const [bookingId, peerLabel] = entries[incomingIndex % entries.length];
        incomingIndex++;

        fetch(apiPath(`call_poll.php?booking_id=${bookingId}`))
            .then(r => r.json())
            .then(data =>{
                if (!data.success || !data.call || active || pendingIncoming) return;
                if (data.call.Status !== 'ringing') return;

                pendingIncoming = {
                    call: data.call,
                    bookingId,
                    peerLabel,
                    initialCandidates: data.new_candidates || [],
                };

                const callType = data.call.Call_Type;
                showCallModal({
                    label: `Incoming ${callType === 'video'? 'video': 'voice'} call`,
                    status: `From ${peerLabel} (Case #${bookingId})`,
                    callType,
                    mode: 'incoming',
                });
            })
            .catch(() =>{});
    }

    async function acceptIncoming() {
        if (!pendingIncoming) return;
        const { call, bookingId, peerLabel, initialCandidates } = pendingIncoming;
        const callId = Number(call.Call_ID);
        const callType = call.Call_Type;
        pendingIncoming = null;

        const label = `${callType === 'video'? 'Video': 'Audio'} call with ${peerLabel}`;
        showCallModal({ label, status: 'Connecting...', callType, mode: 'in-call'});

        try {
            await acquireMedia(callType);
        } catch (err) {
            alert('Could not access your microphone/camera.');
            fetch(apiPath('call_end.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json'},
                body: JSON.stringify({ call_id: callId, reason: 'declined'}),
            }).catch(() =>{});
            cleanupCall();
            return;
        }

        active = { callId, bookingId, callType, isInitiator: false, label, answered: true };
        createPeerConnection(callId, bookingId);

        try {
            const offer = JSON.parse(call.Offer_SDP);
            await pc.setRemoteDescription(new RTCSessionDescription(offer));

            for (const c of initialCandidates) {
                try {
                    await pc.addIceCandidate(new RTCIceCandidate(JSON.parse(c.Candidate)));
                } catch (e) {}
                lastCandidateId = Math.max(lastCandidateId, Number(c.Candidate_ID));
            }

            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);

            const res = await fetch(apiPath('call_signal.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'answer',
                    call_id: callId,
                    sdp_answer: JSON.stringify(answer),
                }),
            });
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Call is no longer active.');
                cleanupCall();
                return;
            }

            wsSend({
                type: 'webrtc_signal',
                signal_type: 'answer',
                booking_id: bookingId,
                call_id: callId,
            });

            setCallStatus('Connected');
            signalTimer = setInterval(pollActiveCall, SIGNAL_POLL_MS);
        } catch (err) {
            alert('Failed to connect call.');
            cleanupCall();
        }
    }

    function declineOrHangup(reason) {
        const callId = active ? active.callId : (pendingIncoming ? Number(pendingIncoming.call.Call_ID) : 0);
        const bookingId = active ? active.bookingId : (pendingIncoming ? pendingIncoming.bookingId : 0);
        cleanupCall();
        if (callId) {
            fetch(apiPath('call_end.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json'},
                body: JSON.stringify({ call_id: callId, reason }),
            }).catch(() =>{});
            wsSend({
                type: 'webrtc_signal',
                signal_type: 'hangup',
                booking_id: bookingId,
                call_id: callId,
            });
        }
    }

    async function pollActiveCall() {
        if (!active) return;
        const { callId, bookingId, isInitiator } = active;

        try {
            const res = await fetch(
                apiPath(`call_poll.php?booking_id=${bookingId}&call_id=${callId}&since_candidate_id=${lastCandidateId}`)
            );
            const data = await res.json();
            if (!data.success || !data.call) return;

            const call = data.call;

            if (['declined', 'ended', 'missed'].includes(call.Status)) {
                const msg = call.Status === 'declined'
                    ? 'The other side declined the call.'
                    : call.Status === 'missed'
                    ? 'No answer.'
                    : 'Call ended.';
                cleanupCall();
                alert(msg);
                return;
            }

            if (isInitiator && !active.answered && call.Answer_SDP) {
                active.answered = true;
                clearTimeout(ringTimeoutTimer);
                await pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(call.Answer_SDP)));
                setCallStatus('Connected');
            }

            if (pc && pc.remoteDescription && Array.isArray(data.new_candidates)) {
                for (const c of data.new_candidates) {
                    try {
                        await pc.addIceCandidate(new RTCIceCandidate(JSON.parse(c.Candidate)));
                    } catch (e) {}
                    lastCandidateId = Math.max(lastCandidateId, Number(c.Candidate_ID));
                }
            }
        } catch (e) {}
    }

    // ---------------------------------------------------------------
    // 5. Public API (HWCall & HWComm)
    // ---------------------------------------------------------------
    const api = {
        init(role) {
            myRole = role;
            ensureUI();
            loadChannelsAndHistory();
            if (!incomingTimer) {
                incomingTimer = setInterval(pollForIncoming, INCOMING_POLL_MS);
            }
        },
        watch(bookingId, peerLabel) {
            watched.set(Number(bookingId), peerLabel || 'Client');
        },
        startCall,
        openChat: openChatDrawer,
        toggleChat: toggleChatDrawer,
        broadcastTriageUpdate(summary) {
            wsSend({ type: 'triage_broadcast', summary });
        },
    };

    global.HWCall = api;
    global.HWComm = api;

    // Auto-initialize floating comm hub on DOMContentLoaded if not explicitly initialized
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(() =>{
            if (!myRole) {
                ensureUI();
                loadChannelsAndHistory();
            }
        }, 400);
    });
})(window);
