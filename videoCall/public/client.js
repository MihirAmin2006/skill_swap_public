// ================= VARIABLES =================
const socket = io("https://nfr7183k-3000.inc1.devtunnels.ms/");
let peerConnection;
let localStream;
let screenStream = null;
let roomId;
let callActive = false;
let isTeacher = false;
let myName = '';
let currentTeacherId = 0;

// Whiteboard — lazy init
let wbCanvas = null, wbCtx = null;
let drawing = false, lastX = null, lastY = null;
let brushColor = '#1d4ed8', brushSize = 6;

const servers = {
  iceServers: [
    { urls: "stun:stun.l.google.com:19302" },
    { urls: "stun:stun1.l.google.com:19302" }
  ]
};

// ================= JOIN ROOM =================
async function joinRoom(id, teacher, name, teacherId) {
  if (callActive) { alert("Meeting already running"); return; }
  roomId = id;
  isTeacher = teacher === true;
  myName = name || (isTeacher ? 'Teacher' : 'Student');
  currentTeacherId = teacherId || 0;
  if (!roomId) return alert("Invalid room");

  try {
    await startVideo();
  } catch (err) {
    alert("Camera/mic access denied: " + err.message);
    return;
  }

  document.getElementById('meetingsList').classList.add('hidden');
  document.getElementById('videoCallContainer').classList.remove('hidden');

  document.querySelectorAll('.teacher-only').forEach(el => {
    el.style.display = isTeacher ? 'flex' : 'none';
  });

  // Clear chat from any previous call
  document.getElementById('chatMessages').innerHTML = '';

  socket.emit("join-room", roomId);
  callActive = true;
}

// ================= START CAMERA =================
async function startVideo() {
  localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
  document.getElementById("localVideo").srcObject = localStream;
}

// ================= PEER CONNECTION =================
function createPeerConnection() {
  if (peerConnection) peerConnection.close();
  peerConnection = new RTCPeerConnection(servers);

  localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));

  peerConnection.ontrack = (e) => {
    if (e.streams && e.streams[0]) {
      const rv = document.getElementById("remoteVideo");
      if (rv.srcObject !== e.streams[0]) rv.srcObject = e.streams[0];
    }
  };

  peerConnection.onicecandidate = (e) => {
    if (e.candidate) socket.emit("ice-candidate", { room: roomId, candidate: e.candidate });
  };

  peerConnection.oniceconnectionstatechange = () =>
    console.log("[ICE]", peerConnection.iceConnectionState);

  return peerConnection;
}

// ================= SOCKET — WebRTC SIGNALING =================
socket.on("initiate-call", async () => {
  createPeerConnection();
  const offer = await peerConnection.createOffer();
  await peerConnection.setLocalDescription(offer);
  socket.emit("offer", { room: roomId, offer });
});

socket.on("offer", async (offer) => {
  createPeerConnection();
  await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
  const answer = await peerConnection.createAnswer();
  await peerConnection.setLocalDescription(answer);
  socket.emit("answer", { room: roomId, answer });
});

socket.on("answer", async (answer) => {
  if (!peerConnection) return;
  if (peerConnection.signalingState === "have-local-offer") {
    await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
  }
});

socket.on("ice-candidate", async (candidate) => {
  try {
    if (peerConnection && candidate)
      await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
  } catch (err) { console.error("[ICE error]", err); }
});

socket.on("room-full", () => alert("Room is full (max 2 users)"));

// FIX: blank remote video when the other user disconnects
socket.on("user-disconnected", () => {
  // Set remote video to null so it goes black, not frozen
  const rv = document.getElementById("remoteVideo");
  rv.srcObject = null;
  // Close the peer connection too
  if (peerConnection) { peerConnection.close(); peerConnection = null; }
  // Show a system message in chat
  appendChatMessage({ name: 'System', text: 'The other participant has left the call.', time: now(), system: true });
  alert("Other user disconnected");
});

// ================= IN-CALL CHAT =================
socket.on("chat-message", (data) => {
  appendChatMessage({ ...data, self: false });
});

function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const text = input.value.trim();
  if (!text || !roomId) return;
  const time = now();
  // Show locally immediately
  appendChatMessage({ name: myName, text, time, self: true });
  // Send to remote
  socket.emit("chat-message", { room: roomId, name: myName, text, time });
  input.value = '';
  input.focus();
}

function appendChatMessage({ name, text, time, self, system }) {
  const box = document.getElementById('chatMessages');
  const wrap = document.createElement('div');
  wrap.style.cssText = `
    display:flex; flex-direction:column;
    align-items:${system ? 'center' : self ? 'flex-end' : 'flex-start'};
    margin-bottom:0.5rem;
  `;

  if (system) {
    wrap.innerHTML = `<span style="
      font-size:0.68rem; color:#64748b; font-style:italic;
      background:rgba(255,255,255,0.04); padding:0.2rem 0.75rem;
      border-radius:999px; border:1px solid rgba(255,255,255,0.07);
    ">${escHtml(text)}</span>`;
  } else {
    wrap.innerHTML = `
      <div style="
        max-width:85%; padding:0.45rem 0.75rem;
        border-radius:${self ? '1rem 1rem 0.2rem 1rem' : '1rem 1rem 1rem 0.2rem'};
        background:${self ? 'rgba(29,78,216,0.75)' : 'rgba(255,255,255,0.09)'};
        border:1px solid ${self ? 'rgba(99,102,241,0.4)' : 'rgba(255,255,255,0.1)'};
        color:#f1f5f9; font-size:0.82rem; line-height:1.4; word-break:break-word;
      ">${escHtml(text)}</div>
      <span style="font-size:0.62rem; color:#475569; margin-top:0.2rem; padding:0 0.25rem;">
        ${self ? '' : escHtml(name) + ' · '}${time}
      </span>
    `;
  }

  box.appendChild(wrap);
  box.scrollTop = box.scrollHeight;

  // Show unread badge if chat is closed
  if (document.getElementById('chatPanel').classList.contains('hidden') && !system) {
    const badge = document.getElementById('chatUnread');
    badge.classList.remove('hidden');
    badge.textContent = (parseInt(badge.textContent) || 0) + 1;
  }
}

function toggleChat() {
  const panel = document.getElementById('chatPanel');
  panel.classList.toggle('hidden');
  if (!panel.classList.contains('hidden')) {
    document.getElementById('chatUnread').classList.add('hidden');
    document.getElementById('chatUnread').textContent = '0';
    document.getElementById('chatInput').focus();
    // Scroll to bottom
    const box = document.getElementById('chatMessages');
    box.scrollTop = box.scrollHeight;
  }
}

function chatKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMessage(); }
}

function now() {
  return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ================= MIC / VIDEO CONTROLS =================
function toggleMic() {
  if (!localStream) return;
  const track = localStream.getAudioTracks()[0];
  if (!track) return;
  track.enabled = !track.enabled;
  const btn = document.getElementById('micBtn');
  btn.classList.toggle('btn-off', !track.enabled);
  btn.innerHTML = track.enabled ? svgMicOn() : svgMicOff();
}

function toggleVideo() {
  if (!localStream) return;
  const track = localStream.getVideoTracks()[0];
  if (!track) return;
  track.enabled = !track.enabled;
  const btn = document.getElementById('camBtn');
  btn.classList.toggle('btn-off', !track.enabled);
  btn.innerHTML = track.enabled ? svgCamOn() : svgCamOff();
}

// ================= SVG ICONS =================
function svgMicOn() {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <rect x="9" y="2" width="6" height="12" rx="3"/>
    <path d="M5 10a7 7 0 0014 0"/>
    <line x1="12" y1="19" x2="12" y2="22"/><line x1="8" y1="22" x2="16" y2="22"/>
  </svg>`;
}
function svgMicOff() {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <line x1="2" y1="2" x2="22" y2="22"/>
    <rect x="9" y="2" width="6" height="12" rx="3"/>
    <path d="M5 10a7 7 0 0012.9 2.9"/>
    <line x1="12" y1="19" x2="12" y2="22"/><line x1="8" y1="22" x2="16" y2="22"/>
  </svg>`;
}
function svgCamOn() {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>
  </svg>`;
}
function svgCamOff() {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <line x1="2" y1="2" x2="22" y2="22"/>
    <polygon points="23 7 16 12 23 17 23 7"/>
    <path d="M16 11V7a2 2 0 00-2-2H5L16 16"/>
    <path d="M4 4H3a2 2 0 00-2 2v10a2 2 0 002 2h13"/>
  </svg>`;
}

// ================= SCREEN SHARE =================
async function shareScreen() {
  if (!peerConnection) return;

  if (screenStream) {
    screenStream.getTracks().forEach(t => t.stop());
    screenStream = null;
    const sender = peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
    if (sender && localStream.getVideoTracks()[0]) sender.replaceTrack(localStream.getVideoTracks()[0]);
    document.getElementById('screenShareContainer').classList.add('hidden');
    document.getElementById('screenVideo').srcObject = null;
    document.getElementById('shareScreenBtn').classList.remove('btn-active');
    socket.emit("screen-share-stop", { room: roomId });
    return;
  }

  try {
    screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
  } catch (err) { console.error("Screen share cancelled:", err); return; }

  const screenTrack = screenStream.getVideoTracks()[0];
  const sender = peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
  if (sender) sender.replaceTrack(screenTrack);

  document.getElementById('screenVideo').srcObject = screenStream;
  document.getElementById('screenShareContainer').classList.remove('hidden');
  document.getElementById('shareScreenBtn').classList.add('btn-active');
  socket.emit("screen-share-start", { room: roomId });
  screenTrack.onended = () => shareScreen();
}

socket.on("screen-share-start", () => {
  setTimeout(() => {
    const remoteVid = document.getElementById('remoteVideo');
    document.getElementById('screenVideo').srcObject = remoteVid.srcObject;
    document.getElementById('screenShareContainer').classList.remove('hidden');
  }, 500);
});
socket.on("screen-share-stop", () => {
  document.getElementById('screenShareContainer').classList.add('hidden');
  document.getElementById('screenVideo').srcObject = null;
});

// ================= WHITEBOARD — lazy init =================
function initWhiteboard() {
  if (wbCanvas) return;
  wbCanvas = document.getElementById('whiteboardCanvas');
  wbCanvas.width  = wbCanvas.offsetWidth  || 900;
  wbCanvas.height = wbCanvas.offsetHeight || 480;
  wbCtx = wbCanvas.getContext('2d');

  wbCanvas.addEventListener('mousedown', e => {
    if (!isTeacher) return;
    drawing = true;
    const p = wbPos(e); lastX = p.x; lastY = p.y; wbDot(p.x, p.y, true);
  });
  wbCanvas.addEventListener('mouseup',    wbStop);
  wbCanvas.addEventListener('mouseleave', wbStop);
  wbCanvas.addEventListener('mousemove', e => {
    if (!isTeacher || !drawing) return;
    const p = wbPos(e);
    if (lastX !== null) wbLine(lastX, lastY, p.x, p.y, true);
    lastX = p.x; lastY = p.y;
  });
  wbCanvas.addEventListener('touchstart', e => {
    if (!isTeacher) return;
    e.preventDefault(); drawing = true;
    const p = wbPos(e); lastX = p.x; lastY = p.y; wbDot(p.x, p.y, true);
  }, { passive: false });
  wbCanvas.addEventListener('touchend', wbStop, { passive: false });
  wbCanvas.addEventListener('touchmove', e => {
    if (!isTeacher) return;
    e.preventDefault(); if (!drawing) return;
    const p = wbPos(e);
    if (lastX !== null) wbLine(lastX, lastY, p.x, p.y, true);
    lastX = p.x; lastY = p.y;
  }, { passive: false });

  // Show read-only cursor for students
  if (!isTeacher) wbCanvas.style.cursor = 'default';
}

function wbStop() { drawing = false; lastX = null; lastY = null; }

function wbPos(e) {
  const r = wbCanvas.getBoundingClientRect();
  const sx = wbCanvas.width / r.width, sy = wbCanvas.height / r.height;
  if (e.touches && e.touches[0])
    return { x: (e.touches[0].clientX - r.left) * sx, y: (e.touches[0].clientY - r.top) * sy };
  return { x: (e.clientX - r.left) * sx, y: (e.clientY - r.top) * sy };
}

function wbDot(x, y, emit) {
  if (!wbCtx) return;
  wbCtx.fillStyle = brushColor;
  wbCtx.beginPath();
  wbCtx.arc(x, y, brushSize / 2, 0, Math.PI * 2);
  wbCtx.fill();
  if (emit) socket.emit("whiteboard-draw", { room: roomId, x, y, color: brushColor, size: brushSize });
}

function wbLine(x1, y1, x2, y2, emit) {
  if (!wbCtx) return;
  wbCtx.strokeStyle = brushColor;
  wbCtx.lineWidth = brushSize;
  wbCtx.lineCap = 'round'; wbCtx.lineJoin = 'round';
  wbCtx.beginPath(); wbCtx.moveTo(x1, y1); wbCtx.lineTo(x2, y2); wbCtx.stroke();
  if (emit) socket.emit("whiteboard-line", { room: roomId, x1, y1, x2, y2, color: brushColor, size: brushSize });
}

socket.on("whiteboard-open",  () => {
  document.getElementById('whiteboardContainer').classList.remove('hidden');
  // Hide toolbar for students — they can only view
  if (!isTeacher) document.getElementById('whiteboardToolbar').style.display = 'none';
  setTimeout(initWhiteboard, 50);
});
socket.on("whiteboard-close", () => { document.getElementById('whiteboardContainer').classList.add('hidden'); });
socket.on("whiteboard-draw",  (d) => { if (!wbCtx) return; wbCtx.fillStyle = d.color; wbCtx.beginPath(); wbCtx.arc(d.x, d.y, d.size / 2, 0, Math.PI * 2); wbCtx.fill(); });
socket.on("whiteboard-line",  (d) => {
  if (!wbCtx) return;
  wbCtx.strokeStyle = d.color; wbCtx.lineWidth = d.size; wbCtx.lineCap = 'round'; wbCtx.lineJoin = 'round';
  wbCtx.beginPath(); wbCtx.moveTo(d.x1, d.y1); wbCtx.lineTo(d.x2, d.y2); wbCtx.stroke();
});
socket.on("whiteboard-clear", () => { if (wbCtx) wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height); });

function toggleWhiteboard() {
  const wb = document.getElementById('whiteboardContainer');
  const nowHidden = wb.classList.toggle('hidden');
  if (!nowHidden) { setTimeout(initWhiteboard, 50); socket.emit("whiteboard-open", { room: roomId }); }
  else socket.emit("whiteboard-close", { room: roomId });
}

function clearWhiteboard() {
  if (!wbCtx) return;
  wbCtx.clearRect(0, 0, wbCanvas.width, wbCanvas.height);
  socket.emit("whiteboard-clear", { room: roomId });
}

function setBrushColor(color, el) {
  brushColor = color;
  document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('ring-2', 'ring-white'));
  if (el) el.classList.add('ring-2', 'ring-white');
}

function setBrushSize(size) {
  brushSize = parseInt(size);
  document.getElementById('brushSizeLabel').textContent = size + 'px';
}

// ================= END CALL =================
async function endCall() {
  // 1. Mark meeting as completed in DB
  if (roomId) {
    const fd = new FormData();
    fd.append('action',     'end_meeting');
    fd.append('meeting_id', roomId);
    fetch('../user/room', { method: 'POST', body: fd, credentials: 'include' }).catch(() => {});
  }

  const endedRoomId    = roomId;
  const endedTeacherId = currentTeacherId;
  const wasStudent     = !isTeacher;

  console.log('[endCall] wasStudent=', wasStudent, '| endedTeacherId=', endedTeacherId, '| endedRoomId=', endedRoomId);

  if (localStream)  localStream.getTracks().forEach(t => t.stop());
  if (screenStream) screenStream.getTracks().forEach(t => t.stop());
  if (peerConnection) peerConnection.close();
  peerConnection = null; screenStream = null; localStream = null;
  wbCanvas = null; wbCtx = null;
  callActive = false;

  ['localVideo', 'remoteVideo', 'screenVideo'].forEach(id => {
    document.getElementById(id).srcObject = null;
  });

  document.getElementById('videoCallContainer').classList.add('hidden');
  document.getElementById('meetingsList').classList.remove('hidden');
  document.getElementById('screenShareContainer').classList.add('hidden');
  document.getElementById('whiteboardContainer').classList.add('hidden');
  document.getElementById('chatPanel').classList.add('hidden');
  document.getElementById('chatMessages').innerHTML = '';

  // 2. Show feedback modal for students (regardless of teacherId value)
  if (wasStudent) {
    console.log('[endCall] showing feedback modal');
    showFeedbackModal(endedTeacherId, endedRoomId);
  }
}

// ================= FEEDBACK MODAL =================
let _fbTeacherId = 0;
let _fbMeetingId = '';
let _fbRating    = 0;

function showFeedbackModal(teacherId, meetingId) {
  _fbTeacherId = teacherId;
  _fbMeetingId = meetingId;
  _fbRating    = 0;
  console.log('[feedback] teacherId=', teacherId, '| meetingId=', meetingId);
  document.querySelectorAll('.star-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('fbComments').value = '';
  document.getElementById('feedbackOverlay').classList.remove('hidden');
}

function closeFeedbackModal() {
  document.getElementById('feedbackOverlay').classList.add('hidden');
  if (_fbMeetingId) {
    window.location.href = '../user/assignment.php?meeting_id=' + encodeURIComponent(_fbMeetingId);
  }
}

function setRating(val) {
  _fbRating = val;
  document.querySelectorAll('.star-btn').forEach(b => {
    b.classList.toggle('active', parseInt(b.dataset.val) <= val);
  });
}

async function submitFeedback() {
  if (_fbRating === 0) { alert('Please select a star rating before submitting.'); return; }
  const comments = document.getElementById('fbComments').value.trim();
  const fd = new FormData();
  fd.append('action',     'save_feedback');
  fd.append('teacher_id', _fbTeacherId);
  fd.append('rating',     _fbRating);
  fd.append('comments',   comments);
  fd.append('meeting_id', _fbMeetingId);
  await fetch('../user/room', { method: 'POST', body: fd, credentials: 'include' });
  closeFeedbackModal();
}