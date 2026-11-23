<?php
/**
 * HealthWing / VetSystem — Protected Clinic Receipts & Blockchain Proof Viewer (receipts.php)
 */
require_once __DIR__ . '/includes/db.php';
hw_start_secure_session();

$isAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin';
$isVet = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Veterinarian';
$isOwner = isset($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'owner';

if (!$isAdmin && !$isVet && !$isOwner) {
    header('Location: login.php');
    exit;
}

$viewerRole = $isAdmin ? 'admin' : ($isVet ? 'vet' : 'owner');
$viewerUser = $isAdmin ? ($_SESSION['admin_username'] ?? 'admin') : ($_SESSION['username'] ?? 'user');
$csrfToken = hw_csrf_token();

$owners = [];
if ($viewerRole !== 'owner') {
    try {
        $conn = hw_get_db();
        $oRes = $conn->query("SELECT Owner_ID, First_Name, Last_Name FROM owner ORDER BY First_Name ASC");
        if ($oRes) {
            $owners = $oRes->fetch_all(MYSQLI_ASSOC);
        }
        $conn->close();
    } catch (Throwable $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Protected Receipts & Blockchain Proof | HealthWing</title>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/loader.css"/>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: #F4F9FA; color: #16262A; }
        .rcpt-wrap { max-width: 1180px; margin: 24px auto; padding: 0 18px 48px; }
        .rcpt-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 18px; }
        .rcpt-head h1 { margin: 0; font-size: 24px; font-weight: 800; color: #1F3A3D; }
        .rcpt-sub { font-size: 13px; color: #546E73; margin-top: 4px; }
        .card { background: #FFFFFF; border: 1px solid #D5E3E6; border-radius: 14px; padding: 18px 20px; margin-bottom: 18px; box-shadow: 0 6px 20px rgba(15,35,42,0.05); }
        .btn { background: #1F3A3D; color: #fff; border: none; padding: 9px 15px; border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .btn-accent { background: #4A9DBB; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
        .badge-ok { background: #E6F4EA; color: #137333; }
        .badge-warn { background: #FEF3C7; color: #92400E; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #E5EEF0; }
        th { font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.04em; color: #546E73; background: #F8FCFD; }
        code.hash { font-size: 11px; background: #EEF5F7; padding: 2px 6px; border-radius: 6px; color: #1F3A3D; word-break: break-all; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
        .form-grid input, .form-grid select { width: 100%; padding: 8px 10px; border: 1px solid #C8D8DC; border-radius: 8px; font-size: 13px; box-sizing: border-box; }
        .form-grid label { display: block; font-size: 11.5px; font-weight: 700; color: #374F54; margin-bottom: 4px; }

        /* Anti-Screenshot & Print Protection Surface */
        #protectedReceiptModal {
            display: none; position: fixed; inset: 0; z-index: 99999;
            background: rgba(15, 29, 32, 0.85); align-items: center; justify-content: center; padding: 16px;
        }
        #protectedReceiptSurface {
            position: relative; background: #FFFFFF; width: min(96vw, 620px); border-radius: 16px;
            padding: 24px; box-shadow: 0 24px 60px rgba(0,0,0,0.45); overflow: hidden;
            -webkit-user-select: none; -moz-user-select: none; user-select: none;
            -webkit-touch-callout: none;
        }
        #protectedReceiptBody { transition: filter 0.15s ease; }
        #protectedReceiptBody.hw-privacy-blur { filter: blur(18px) contrast(0.5); pointer-events: none; }
        #protectedWatermark {
            position: absolute; inset: 0; pointer-events: none; z-index: 5;
            display: flex; align-items: center; justify-content: center; text-align: center;
            font-size: 18px; font-weight: 800; color: rgba(31, 58, 61, 0.09);
            transform: rotate(-22deg); letter-spacing: 0.08em; white-space: pre-wrap;
        }
        #privacyVeil {
            display: none; position: absolute; inset: 0; z-index: 10;
            background: rgba(22, 38, 42, 0.92); color: #FFFFFF;
            flex-direction: column; align-items: center; justify-content: center;
            text-align: center; padding: 24px; gap: 12px;
        }
        #privacyVeil.active { display: flex; }

        @media print {
            body * { display: none !important; }
            body::after {
                content: "Printing and screen capture of protected clinic receipts are disabled by clinic policy.";
                display: block !important;
                padding: 40px;
                font-family: system-ui, sans-serif;
                font-size: 18px;
                color: #16262A;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="rcpt-wrap">
    <div class="rcpt-head">
        <div>
            <h1>Clinic Receipts & Blockchain Proof</h1>
            <div class="rcpt-sub">SHA-256 Merkle roots, hash-chained blocks, and 365-day AES-256-GCM encrypted owner archive copies.</div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <span id="chainBadge" class="badge badge-ok">Checking Ledger...</span>
            <button type="button" class="btn btn-accent" onclick="verifyChain()">Verify Blockchain</button>
        </div>
    </div>

    <?php if ($viewerRole !== 'owner'): ?>
    <div class="card">
        <h2 style="margin:0 0 12px;font-size:16px;">Issue New Receipt Block</h2>
        <form id="issueReceiptForm" class="form-grid">
            <div>
                <label>Pet Owner</label>
                <select name="owner_id" id="issueOwnerId" required>
                    <option value="">Select owner...</option>
                    <?php foreach ($owners as $o): ?>
                        <option value="<?php echo (int)$o['Owner_ID']; ?>">
                            <?php echo htmlspecialchars(trim($o['First_Name'] . ' ' . $o['Last_Name']), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Pet Name</label>
                <input type="text" name="pet_name" id="issuePetName" placeholder="e.g., Mochi" required>
            </div>
            <div>
                <label>Service Description</label>
                <input type="text" name="service_summary" id="issueService" placeholder="e.g., Wellness Exam" required>
            </div>
            <div>
                <label>Amount (PHP)</label>
                <input type="number" step="0.01" min="0" name="amount" id="issueAmount" placeholder="850.00" required>
            </div>
            <div style="display:flex;align-items:flex-end;">
                <button type="submit" class="btn" style="width:100%;">Mint Receipt Block</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin:0 0 12px;font-size:16px;">Receipt Ledger (1-Year Encrypted Archive Retention)</h2>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Block</th>
                        <th>Receipt Code</th>
                        <th>Service</th>
                        <th>Amount</th>
                        <th>Merkle Root</th>
                        <th>Issued</th>
                        <th>Expires</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="receiptRows">
                    <tr><td colspan="8">Loading receipt blocks...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="protectedReceiptModal" role="dialog" aria-modal="true" aria-labelledby="rcptModalTitle">
    <div id="protectedReceiptSurface">
        <div id="protectedWatermark"></div>
        <div id="privacyVeil">
            <div style="font-size:18px;font-weight:800;">Receipt Protected</div>
            <div style="font-size:13px;max-width:380px;color:#CFE2E6;">Window focus loss or capture attempt detected. Click below to unblur your protected clinic receipt.</div>
            <button type="button" class="btn btn-accent" onclick="unveilReceipt()">Resume Protected View</button>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;position:relative;z-index:6;">
            <div>
                <h2 id="rcptModalTitle" style="margin:0;font-size:18px;color:#1F3A3D;">Protected Clinic Receipt</h2>
                <div style="font-size:11.5px;color:#546E73;">Screen capture, copy, and printing disabled</div>
            </div>
            <button type="button" class="btn" onclick="closeReceiptModal()">Close</button>
        </div>
        <div id="protectedReceiptBody"></div>
    </div>
</div>

<script>
const viewerUsername = <?php echo json_encode($viewerUser); ?>;

async function loadReceipts() {
    const res = await fetch('api/receipt_api.php?action=list');
    const data = await res.json();
    const tbody = document.getElementById('receiptRows');
    if (!data.success || !Array.isArray(data.receipts) || data.receipts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#62787B;">No active receipts in ledger yet.</td></tr>';
        return;
    }
    tbody.innerHTML = data.receipts.map(r => `
        <tr>
            <td>#${Number(r.Block_Index)}</td>
            <td><strong>${escapeHtml(r.Receipt_Code)}</strong></td>
            <td>${escapeHtml(r.Service_Summary)}</td>
            <td>PHP ${Number(r.Amount).toFixed(2)}</td>
            <td><code class="hash">${escapeHtml(String(r.Merkle_Root).slice(0, 16))}...</code></td>
            <td>${escapeHtml(r.Created_At)}</td>
            <td>${escapeHtml(r.Expires_At)}</td>
            <td><button type="button" class="btn btn-accent" onclick="viewReceipt(${Number(r.Block_ID)})">Protected View</button></td>
        </tr>
    `).join('');
}

async function verifyChain() {
    const badge = document.getElementById('chainBadge');
    const res = await fetch('api/receipt_api.php?action=verify');
    const data = await res.json();
    if (data.success && data.verification) {
        const v = data.verification;
        if (v.valid) {
            badge.className = 'badge badge-ok';
            badge.textContent = `Blockchain Verified (${v.total_blocks} blocks)`;
        } else {
            badge.className = 'badge badge-warn';
            badge.textContent = `Verification Warning (${v.errors.length} issues)`;
        }
    }
}

async function viewReceipt(blockId) {
    const res = await fetch(`api/receipt_api.php?action=view&id=${encodeURIComponent(blockId)}`);
    const payload = await res.json();
    if (!payload.success || !payload.data) {
        alert(payload.message || 'Unable to load protected receipt.');
        return;
    }
    const { block, receipt } = payload.data;
    const itemsHtml = (receipt.items || []).map(it =>
        `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #EEF5F7;">
            <span>${escapeHtml(it.description)} (x${Number(it.qty || 1)})</span>
            <strong>PHP ${Number(it.price || 0).toFixed(2)}</strong>
        </div>`
    ).join('');

    document.getElementById('protectedWatermark').textContent =
        `${viewerUsername}\n${block.Receipt_Code}\nPROTECTED ARCHIVE COPY`;

    document.getElementById('protectedReceiptBody').innerHTML = `
        <div style="font-size:13px;line-height:1.55;">
            <div><strong>Receipt Code:</strong> ${escapeHtml(block.Receipt_Code)}</div>
            <div><strong>Owner:</strong> ${escapeHtml(receipt.owner_name)} &middot; <strong>Pet:</strong> ${escapeHtml(receipt.pet_name)}</div>
            <div><strong>Issued By:</strong> ${escapeHtml(receipt.issued_by)} &middot; <strong>Issued:</strong> ${escapeHtml(block.Created_At)}</div>
            <div><strong>365-Day Encrypted Archive:</strong> ${block.Archive_Active ? 'Active' : 'Expired'} (Expires ${escapeHtml(block.Expires_At)})</div>
            <div style="margin:12px 0;padding:10px 12px;background:#F8FCFD;border:1px solid #D5E3E6;border-radius:10px;">
                ${itemsHtml}
                <div style="display:flex;justify-content:space-between;padding-top:8px;font-size:15px;">
                    <strong>Total Paid</strong>
                    <strong>PHP ${Number(receipt.amount || 0).toFixed(2)}</strong>
                </div>
            </div>
            <div style="font-size:11.5px;background:#F4F9FA;padding:10px;border-radius:8px;border:1px solid #D5E3E6;">
                <div><strong>Integrity Status:</strong> ${block.Verified ? 'Verified' : 'Mismatch'} (Block #${Number(block.Block_Index)})</div>
                <div><strong>Merkle Root:</strong> <code class="hash">${escapeHtml(block.Merkle_Root)}</code></div>
                <div><strong>Previous Hash:</strong> <code class="hash">${escapeHtml(block.Prev_Hash)}</code></div>
                <div><strong>Block Hash:</strong> <code class="hash">${escapeHtml(block.Block_Hash)}</code></div>
            </div>
        </div>
    `;
    unveilReceipt();
    document.getElementById('protectedReceiptModal').style.display = 'flex';
}

function closeReceiptModal() {
    document.getElementById('protectedReceiptModal').style.display = 'none';
}

function triggerPrivacyVeil() {
    const modal = document.getElementById('protectedReceiptModal');
    if (modal.style.display === 'flex') {
        document.getElementById('protectedReceiptBody').classList.add('hw-privacy-blur');
        document.getElementById('privacyVeil').classList.add('active');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText('').catch(() => {});
        }
    }
}

function unveilReceipt() {
    document.getElementById('protectedReceiptBody').classList.remove('hw-privacy-blur');
    document.getElementById('privacyVeil').classList.remove('active');
}

const surface = document.getElementById('protectedReceiptSurface');
['contextmenu', 'copy', 'cut', 'dragstart'].forEach(evtName => {
    surface.addEventListener(evtName, e => e.preventDefault());
});

window.addEventListener('blur', triggerPrivacyVeil);
document.addEventListener('visibilitychange', () => {
    if (document.hidden) triggerPrivacyVeil();
});
document.addEventListener('keydown', (e) => {
    const key = (e.key || '').toLowerCase();
    if (
        e.key === 'PrintScreen' ||
        ((e.ctrlKey || e.metaKey) && ['p', 's', 'c'].includes(key)) ||
        ((e.ctrlKey || e.metaKey) && e.shiftKey && ['s', '3', '4', '5'].includes(key))
    ) {
        if (document.getElementById('protectedReceiptModal').style.display === 'flex') {
            e.preventDefault();
            triggerPrivacyVeil();
        }
    }
});
document.addEventListener('keyup', (e) => {
    if (e.key === 'PrintScreen') {
        triggerPrivacyVeil();
    }
});

const issueForm = document.getElementById('issueReceiptForm');
if (issueForm) {
    issueForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await fetch('api/receipt_api.php?action=issue', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                owner_id: document.getElementById('issueOwnerId').value,
                pet_name: document.getElementById('issuePetName').value,
                service_summary: document.getElementById('issueService').value,
                amount: document.getElementById('issueAmount').value,
            }),
        });
        const data = await res.json();
        if (data.success) {
            issueForm.reset();
            await loadReceipts();
            await verifyChain();
        } else {
            alert(data.message || 'Could not issue receipt.');
        }
    });
}

function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
    loadReceipts();
    verifyChain();
});
</script>
</body>
</html>
