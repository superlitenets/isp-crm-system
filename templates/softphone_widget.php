<?php
$showSoftphone = false;
$softphoneConfig = [];
try {
    $db = Database::getConnection();
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT e.extension, e.name, e.type FROM call_center_extensions e WHERE e.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $ext = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ext) {
            $showSoftphone = true;
            $ucmHost = '';
            $pbxHost = '';
            $wsPort = '8089';
            try {
                $s1 = $db->prepare("SELECT setting_value FROM call_center_settings WHERE setting_key = ?");
                $s1->execute(['ucm_host']); $r = $s1->fetch(PDO::FETCH_ASSOC); $ucmHost = $r['setting_value'] ?? '';
                $s1->execute(['pbx_host']); $r = $s1->fetch(PDO::FETCH_ASSOC); $pbxHost = $r['setting_value'] ?? '';
                $s1->execute(['ws_port']); $r = $s1->fetch(PDO::FETCH_ASSOC); $wsPort = $r['setting_value'] ?? '8089';
            } catch (Exception $e) {}
            $softphoneConfig = [
                'extension' => $ext['extension'],
                'name' => $ext['name'],
                'pbxHost' => $ucmHost ?: $pbxHost,
                'wsPort' => $wsPort
            ];
        }
    }
} catch (Exception $e) {
    $showSoftphone = false;
}
if (!$showSoftphone) return;
?>

<div id="softphoneWidget" class="sp-widget sp-minimized">
    <div class="sp-fab" onclick="toggleSoftphone()" title="Softphone">
        <i class="bi bi-telephone-fill"></i>
        <span class="sp-fab-badge" id="spCallBadge" style="display:none"></span>
    </div>

    <div class="sp-panel" id="spPanel">
        <div class="sp-header">
            <div class="sp-header-info">
                <span class="sp-ext-name"><?= htmlspecialchars($softphoneConfig['name'] ?? '') ?></span>
                <span class="sp-ext-num">Ext. <?= htmlspecialchars($softphoneConfig['extension'] ?? '') ?></span>
            </div>
            <div class="sp-header-actions">
                <span class="sp-status" id="spStatus">
                    <i class="bi bi-circle-fill" style="font-size:0.5rem"></i> Offline
                </span>
                <button class="sp-btn-close" onclick="toggleSoftphone()"><i class="bi bi-chevron-down"></i></button>
            </div>
        </div>

        <div class="sp-body" id="spIdleView">
            <div class="sp-display" id="spDisplay"></div>
            <div class="sp-dialpad">
                <div class="sp-dialpad-row">
                    <button class="sp-key" onclick="dialKey('1')">1<small></small></button>
                    <button class="sp-key" onclick="dialKey('2')">2<small>ABC</small></button>
                    <button class="sp-key" onclick="dialKey('3')">3<small>DEF</small></button>
                </div>
                <div class="sp-dialpad-row">
                    <button class="sp-key" onclick="dialKey('4')">4<small>GHI</small></button>
                    <button class="sp-key" onclick="dialKey('5')">5<small>JKL</small></button>
                    <button class="sp-key" onclick="dialKey('6')">6<small>MNO</small></button>
                </div>
                <div class="sp-dialpad-row">
                    <button class="sp-key" onclick="dialKey('7')">7<small>PQRS</small></button>
                    <button class="sp-key" onclick="dialKey('8')">8<small>TUV</small></button>
                    <button class="sp-key" onclick="dialKey('9')">9<small>WXYZ</small></button>
                </div>
                <div class="sp-dialpad-row">
                    <button class="sp-key" onclick="dialKey('*')">*</button>
                    <button class="sp-key" onclick="dialKey('0')">0<small>+</small></button>
                    <button class="sp-key" onclick="dialKey('#')">#</button>
                </div>
            </div>
            <div class="sp-actions">
                <button class="sp-btn-delete" onclick="deleteDigit()" title="Backspace">
                    <i class="bi bi-backspace"></i>
                </button>
                <button class="sp-btn-call" onclick="makeCall()" id="spCallBtn">
                    <i class="bi bi-telephone-fill"></i>
                </button>
                <button class="sp-btn-transfer" onclick="showTransfer()" title="Transfer" style="display:none" id="spTransferBtn">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
            </div>
        </div>

        <div class="sp-body sp-call-view" id="spCallView" style="display:none">
            <div class="sp-call-info">
                <div class="sp-call-avatar" id="spCallAvatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="sp-call-name" id="spCallName">Unknown</div>
                <div class="sp-call-number" id="spCallNumber"></div>
                <div class="sp-call-timer" id="spCallTimer">00:00</div>
                <div class="sp-call-status" id="spCallStatus">Calling...</div>
            </div>
            <div class="sp-call-actions">
                <button class="sp-call-action-btn" onclick="toggleMute()" id="spMuteBtn" title="Mute">
                    <i class="bi bi-mic-fill"></i>
                    <span>Mute</span>
                </button>
                <button class="sp-call-action-btn" onclick="toggleHold()" id="spHoldBtn" title="Hold">
                    <i class="bi bi-pause-fill"></i>
                    <span>Hold</span>
                </button>
                <button class="sp-call-action-btn" onclick="showTransferDialog()" id="spXferBtn" title="Transfer">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Transfer</span>
                </button>
                <button class="sp-call-action-btn sp-hangup" onclick="hangupCall()" title="Hangup">
                    <i class="bi bi-telephone-x-fill"></i>
                    <span>Hangup</span>
                </button>
            </div>
        </div>

        <div class="sp-body sp-incoming-view" id="spIncomingView" style="display:none">
            <div class="sp-call-info">
                <div class="sp-call-avatar sp-incoming-pulse">
                    <i class="bi bi-telephone-inbound-fill"></i>
                </div>
                <div class="sp-call-name" id="spIncomingName">Incoming Call</div>
                <div class="sp-call-number" id="spIncomingNumber"></div>
                <div class="sp-call-status">Incoming call...</div>
            </div>
            <div class="sp-incoming-actions">
                <button class="sp-answer-btn" onclick="answerCall()">
                    <i class="bi bi-telephone-fill"></i> Answer
                </button>
                <button class="sp-reject-btn" onclick="rejectCall()">
                    <i class="bi bi-telephone-x-fill"></i> Decline
                </button>
            </div>
        </div>
    </div>
</div>

<audio id="spRingtone" loop preload="none">
    <source src="data:audio/wav;base64,UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ4AAAB/" type="audio/wav">
</audio>
<audio id="spRemoteAudio" autoplay></audio>

<style>
.sp-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 10000;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.sp-fab {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00a884, #075e54);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    font-size: 1.4rem;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
}
.sp-fab:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 20px rgba(0,0,0,0.35);
}
.sp-fab-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}
.sp-widget.sp-minimized .sp-panel { display: none; }
.sp-widget.sp-expanded .sp-fab { display: none; }
.sp-panel {
    width: 320px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    overflow: hidden;
}
.sp-header {
    background: linear-gradient(135deg, #075e54, #128c7e);
    color: #fff;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sp-header-info { display: flex; flex-direction: column; }
.sp-ext-name { font-weight: 600; font-size: 0.9rem; }
.sp-ext-num { font-size: 0.75rem; opacity: 0.8; }
.sp-header-actions { display: flex; align-items: center; gap: 8px; }
.sp-status {
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: rgba(255,255,255,0.15);
    border-radius: 10px;
}
.sp-status.online i { color: #25d366; }
.sp-status.offline i { color: #dc3545; }
.sp-status.oncall i { color: #ffc107; }
.sp-btn-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 4px;
}
.sp-body { padding: 16px; }
.sp-display {
    background: #f0f2f5;
    border-radius: 8px;
    padding: 12px 16px;
    text-align: right;
    font-size: 1.5rem;
    font-weight: 500;
    min-height: 48px;
    margin-bottom: 12px;
    letter-spacing: 2px;
    color: #111b21;
    overflow: hidden;
    word-break: break-all;
}
.sp-dialpad { display: flex; flex-direction: column; gap: 8px; }
.sp-dialpad-row { display: flex; gap: 8px; justify-content: center; }
.sp-key {
    width: 72px;
    height: 52px;
    border: none;
    border-radius: 50%;
    background: #f0f2f5;
    font-size: 1.3rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
    color: #111b21;
    line-height: 1;
}
.sp-key small {
    font-size: 0.5rem;
    font-weight: 600;
    color: #667781;
    letter-spacing: 2px;
    margin-top: 1px;
}
.sp-key:hover { background: #e1e4e8; }
.sp-key:active { background: #d1d5db; }
.sp-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 24px;
    margin-top: 16px;
}
.sp-btn-call {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    background: #00a884;
    color: #fff;
    font-size: 1.4rem;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sp-btn-call:hover { background: #008f72; }
.sp-btn-delete, .sp-btn-transfer {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: #667781;
    font-size: 1.2rem;
    cursor: pointer;
}
.sp-btn-delete:hover, .sp-btn-transfer:hover { background: #f0f2f5; }
.sp-call-view, .sp-incoming-view { text-align: center; }
.sp-call-info { padding: 20px 0; }
.sp-call-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00a884, #075e54);
    color: #fff;
    font-size: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}
.sp-incoming-pulse {
    animation: sp-pulse 1.5s ease-in-out infinite;
    background: linear-gradient(135deg, #25d366, #128c7e);
}
@keyframes sp-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(37,211,102,0.4); }
    50% { box-shadow: 0 0 0 20px rgba(37,211,102,0); }
}
.sp-call-name { font-size: 1.2rem; font-weight: 600; color: #111b21; }
.sp-call-number { font-size: 0.85rem; color: #667781; margin-top: 4px; }
.sp-call-timer { font-size: 1.8rem; font-weight: 300; color: #00a884; margin-top: 8px; font-variant-numeric: tabular-nums; }
.sp-call-status { font-size: 0.8rem; color: #667781; margin-top: 4px; }
.sp-call-actions {
    display: flex;
    justify-content: center;
    gap: 16px;
    padding: 16px 0;
    flex-wrap: wrap;
}
.sp-call-action-btn {
    width: 60px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    border: none;
    background: none;
    cursor: pointer;
    color: #667781;
    font-size: 0.7rem;
}
.sp-call-action-btn i {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #f0f2f5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all 0.2s;
}
.sp-call-action-btn:hover i { background: #e1e4e8; }
.sp-call-action-btn.active i { background: #00a884; color: #fff; }
.sp-call-action-btn.sp-hangup i { background: #dc3545; color: #fff; }
.sp-call-action-btn.sp-hangup:hover i { background: #bb2d3b; }
.sp-incoming-actions {
    display: flex;
    justify-content: center;
    gap: 24px;
    padding: 16px 0;
}
.sp-answer-btn, .sp-reject-btn {
    padding: 12px 28px;
    border: none;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sp-answer-btn { background: #00a884; color: #fff; }
.sp-answer-btn:hover { background: #008f72; }
.sp-reject-btn { background: #dc3545; color: #fff; }
.sp-reject-btn:hover { background: #bb2d3b; }
.sp-widget.sp-ringing .sp-fab {
    animation: sp-ring-shake 0.5s ease-in-out infinite;
    background: linear-gradient(135deg, #25d366, #128c7e);
}
@keyframes sp-ring-shake {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(15deg); }
    75% { transform: rotate(-15deg); }
}
</style>

<script>
const spConfig = <?= json_encode($softphoneConfig) ?>;
let spDialString = '';
let spCallState = 'idle';
let spCallTimer = null;
let spCallSeconds = 0;
let spMuted = false;
let spOnHold = false;
let spCurrentCallId = null;

function toggleSoftphone() {
    const w = document.getElementById('softphoneWidget');
    w.classList.toggle('sp-minimized');
    w.classList.toggle('sp-expanded');
}

function dialKey(key) {
    spDialString += key;
    document.getElementById('spDisplay').textContent = spDialString;
}

function deleteDigit() {
    spDialString = spDialString.slice(0, -1);
    document.getElementById('spDisplay').textContent = spDialString;
}

function makeCall() {
    if (!spDialString || spCallState !== 'idle') return;

    const number = spDialString;
    spCallState = 'calling';
    showCallView(number, 'Calling...');

    fetch('?page=call_center&action=originate_call', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `phone=${encodeURIComponent(number)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            spCallState = 'connected';
            document.getElementById('spCallStatus').textContent = 'Connected' + (data.via === 'ucm' ? ' (UCM)' : '');
            startCallTimer();
            lookupCustomer(number);
        } else {
            document.getElementById('spCallStatus').textContent = data.error || 'Call failed';
            setTimeout(() => endCallView(), 3000);
        }
    })
    .catch(err => {
        document.getElementById('spCallStatus').textContent = 'Connection error';
        setTimeout(() => endCallView(), 3000);
    });
}

function showCallView(number, status) {
    document.getElementById('spIdleView').style.display = 'none';
    document.getElementById('spCallView').style.display = 'block';
    document.getElementById('spIncomingView').style.display = 'none';
    document.getElementById('spCallNumber').textContent = number;
    document.getElementById('spCallName').textContent = number;
    document.getElementById('spCallStatus').textContent = status;
    document.getElementById('spCallTimer').textContent = '00:00';
    spCallSeconds = 0;

    const w = document.getElementById('softphoneWidget');
    if (w.classList.contains('sp-minimized')) toggleSoftphone();
}

function showIncomingCall(number, name) {
    document.getElementById('spIdleView').style.display = 'none';
    document.getElementById('spCallView').style.display = 'none';
    document.getElementById('spIncomingView').style.display = 'block';
    document.getElementById('spIncomingNumber').textContent = number;
    document.getElementById('spIncomingName').textContent = name || number;

    const w = document.getElementById('softphoneWidget');
    w.classList.add('sp-ringing');
    if (w.classList.contains('sp-minimized')) toggleSoftphone();

    document.getElementById('spCallBadge').style.display = 'flex';
    document.getElementById('spCallBadge').textContent = '!';
}

function answerCall() {
    spCallState = 'connected';
    document.getElementById('softphoneWidget').classList.remove('sp-ringing');
    document.getElementById('spCallBadge').style.display = 'none';
    showCallView(
        document.getElementById('spIncomingNumber').textContent,
        'Connected'
    );
    document.getElementById('spCallName').textContent =
        document.getElementById('spIncomingName').textContent;
    startCallTimer();
}

function rejectCall() {
    spCallState = 'idle';
    document.getElementById('softphoneWidget').classList.remove('sp-ringing');
    document.getElementById('spCallBadge').style.display = 'none';
    endCallView();
}

function hangupCall() {
    if (spCurrentCallId) {
        fetch('?page=call_center&action=hangup_call', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `channel=${encodeURIComponent(spCurrentCallId)}`
        }).catch(() => {});
    }
    endCallView();
}

function endCallView() {
    spCallState = 'idle';
    spMuted = false;
    spOnHold = false;
    spCurrentCallId = null;
    if (spCallTimer) { clearInterval(spCallTimer); spCallTimer = null; }
    document.getElementById('spIdleView').style.display = 'block';
    document.getElementById('spCallView').style.display = 'none';
    document.getElementById('spIncomingView').style.display = 'none';
    document.getElementById('softphoneWidget').classList.remove('sp-ringing');
    document.getElementById('spCallBadge').style.display = 'none';
    document.getElementById('spMuteBtn').classList.remove('active');
    document.getElementById('spHoldBtn').classList.remove('active');
    spDialString = '';
    document.getElementById('spDisplay').textContent = '';
}

function startCallTimer() {
    if (spCallTimer) clearInterval(spCallTimer);
    spCallSeconds = 0;
    spCallTimer = setInterval(() => {
        spCallSeconds++;
        const m = String(Math.floor(spCallSeconds / 60)).padStart(2, '0');
        const s = String(spCallSeconds % 60).padStart(2, '0');
        document.getElementById('spCallTimer').textContent = `${m}:${s}`;
    }, 1000);
}

function toggleMute() {
    spMuted = !spMuted;
    document.getElementById('spMuteBtn').classList.toggle('active', spMuted);
    document.getElementById('spMuteBtn').querySelector('i').className =
        spMuted ? 'bi bi-mic-mute-fill' : 'bi bi-mic-fill';
}

function toggleHold() {
    spOnHold = !spOnHold;
    document.getElementById('spHoldBtn').classList.toggle('active', spOnHold);
    document.getElementById('spCallStatus').textContent = spOnHold ? 'On Hold' : 'Connected';
}

function showTransferDialog() {
    const dest = prompt('Transfer to extension or number:');
    if (dest) {
        fetch('?page=call_center&action=transfer_call', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `channel=${encodeURIComponent(spCurrentCallId || '')}&destination=${encodeURIComponent(dest)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('spCallStatus').textContent = 'Transferred';
                setTimeout(() => endCallView(), 2000);
            } else {
                alert(data.error || 'Transfer failed');
            }
        })
        .catch(() => alert('Transfer error'));
    }
}

function lookupCustomer(phone) {
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    fetch(`/api/whatsapp-chat.php?action=search-customers&q=${encodeURIComponent(cleanPhone)}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.customers && data.customers.length > 0) {
                const c = data.customers[0];
                document.getElementById('spCallName').textContent = c.name;
                document.getElementById('spCallNumber').textContent = phone + (c.account_number ? ` (${c.account_number})` : '');
            }
        })
        .catch(() => {});
}

function updateSpStatus(status) {
    const el = document.getElementById('spStatus');
    el.className = 'sp-status ' + status;
    const labels = { online: 'Online', offline: 'Offline', oncall: 'On Call' };
    el.innerHTML = `<i class="bi bi-circle-fill" style="font-size:0.5rem"></i> ${labels[status] || status}`;
}

document.getElementById('spDisplay').addEventListener('click', function() {
    this.focus();
});

document.addEventListener('keydown', function(e) {
    const w = document.getElementById('softphoneWidget');
    if (!w.classList.contains('sp-expanded')) return;
    if (spCallState !== 'idle') return;

    if (/^[0-9*#]$/.test(e.key)) {
        dialKey(e.key);
    } else if (e.key === 'Backspace') {
        deleteDigit();
    } else if (e.key === 'Enter' && spDialString) {
        makeCall();
    }
});

updateSpStatus('online');

setInterval(() => {
    if (spCallState !== 'idle') return;
    fetch('?page=call_center&action=ucm_active_calls')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.calls) {
                const myExt = spConfig.extension;
                const myCall = data.calls.find(c =>
                    (c.caller === myExt || c.callee === myExt) &&
                    spCallState === 'idle'
                );
                if (myCall && !document.getElementById('spIncomingView').style.display !== 'none') {
                    const incomingNum = myCall.caller === myExt ? myCall.callee : myCall.caller;
                    showIncomingCall(incomingNum, myCall.callername || incomingNum);
                }
            }
        })
        .catch(() => {});
}, 10000);
</script>
