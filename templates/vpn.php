<?php
if (!defined('IN_APP')) {
    header('Location: /');
    exit;
}

$csrfToken = \App\Auth::generateToken();
$wgService = new \App\WireGuardService($db);
$wgSettings = $wgService->getSettings();
$wgServers = $wgService->getServers();
$wgPeers = $wgService->getAllPeers();

$olts = [];
try {
    $stmt = $db->query("SELECT id, name FROM huawei_olts ORDER BY name");
    $olts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<div class="container-fluid py-4">
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-shield-lock me-2"></i>VPN Management</h2>
        <div class="d-flex gap-2">
            <form method="post" class="d-inline" onsubmit="return confirm('Sync WireGuard config and routes?');">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="sync_vpn_config">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-arrow-repeat me-1"></i>Sync Config & Routes
                </button>
            </form>
            <button class="btn btn-outline-primary" onclick="refreshStatus()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh Status
            </button>
            <span class="badge bg-<?= $wgSettings['vpn_enabled'] === 'true' ? 'success' : 'secondary' ?> fs-6 d-flex align-items-center">
                <i class="bi bi-circle-fill me-1 small"></i>
                VPN <?= $wgSettings['vpn_enabled'] === 'true' ? 'Enabled' : 'Disabled' ?>
            </span>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Total Peers</h6>
                            <h2 class="mb-0"><?= count($wgPeers) ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-diagram-3 fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Active Peers</h6>
                            <h2 class="mb-0" id="activePeersCount"><?= count(array_filter($wgPeers, fn($p) => $p['is_active'])) ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">OLT Sites</h6>
                            <h2 class="mb-0"><?= count(array_filter($wgPeers, fn($p) => $p['is_olt_site'])) ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-hdd-network fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Gateway IP</h6>
                            <h4 class="mb-0"><?= htmlspecialchars($wgSettings['vpn_gateway_ip']) ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-router fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>VPN Peers & Connection Status</h5>
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addPeerModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Peer
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($wgPeers)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-diagram-3 fs-1 d-block mb-3"></i>
                        <p class="mb-0">No VPN peers configured</p>
                        <p class="small">Add peers to connect to OLT sites</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">Status</th>
                                    <th>Peer Name</th>
                                    <th>IP Address</th>
                                    <th>Endpoint</th>
                                    <th>Last Handshake</th>
                                    <th>Traffic</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="peersTableBody">
                                <?php foreach ($wgPeers as $peer): ?>
                                <tr data-peer-id="<?= $peer['id'] ?>">
                                    <td class="text-center">
                                        <span class="connection-status" data-peer-id="<?= $peer['id'] ?>">
                                            <?php if ($peer['is_active']): ?>
                                            <i class="bi bi-circle-fill text-warning" title="Unknown - Click Refresh"></i>
                                            <?php else: ?>
                                            <i class="bi bi-circle-fill text-secondary" title="Disabled"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($peer['name']) ?></strong>
                                        <?php if ($peer['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($peer['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($peer['allowed_ips']) ?></code></td>
                                    <td>
                                        <?php if ($peer['endpoint']): ?>
                                        <code><?= htmlspecialchars($peer['endpoint']) ?></code>
                                        <?php else: ?>
                                        <span class="text-muted">Dynamic</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="last-handshake" data-peer-id="<?= $peer['id'] ?>">
                                        <?php if ($peer['last_handshake']): ?>
                                        <span class="text-success"><?= date('H:i:s', strtotime($peer['last_handshake'])) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small traffic-stats" data-peer-id="<?= $peer['id'] ?>">
                                        <span class="text-success"><i class="bi bi-arrow-down"></i> <?= $wgService->formatBytes($peer['rx_bytes']) ?></span><br>
                                        <span class="text-primary"><i class="bi bi-arrow-up"></i> <?= $wgService->formatBytes($peer['tx_bytes']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($peer['is_olt_site']): ?>
                                        <span class="badge bg-info"><i class="bi bi-hdd-network me-1"></i>OLT Site</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">General</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewPeerConfig(<?= $peer['id'] ?>)" title="WireGuard Config">
                                                <i class="bi bi-download"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewMikroTikScript(<?= $peer['id'] ?>)" title="MikroTik Script">
                                                <i class="bi bi-terminal"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="editPeer(<?= $peer['id'] ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deletePeer(<?= $peer['id'] ?>)" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-server me-2"></i>VPN Servers</h5>
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addServerModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Server
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($wgServers)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">No VPN servers configured</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Interface</th>
                                    <th>Address</th>
                                    <th>Port</th>
                                    <th>Public Key</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wgServers as $server): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($server['name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($server['interface_name']) ?></code></td>
                                    <td><code><?= htmlspecialchars($server['interface_addr']) ?></code></td>
                                    <td><?= $server['listen_port'] ?></td>
                                    <td><code class="small"><?= substr($server['public_key'], 0, 20) ?>...</code></td>
                                    <td>
                                        <?php if ($server['enabled']): ?>
                                        <span class="badge bg-success">Enabled</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewServerConfig(<?= $server['id'] ?>)" title="Download Config">
                                                <i class="bi bi-download"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteServer(<?= $server['id'] ?>)" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>VPN Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="save_vpn_settings">
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="vpn_enabled" name="vpn_enabled" <?= $wgSettings['vpn_enabled'] === 'true' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="vpn_enabled"><strong>Enable VPN</strong></label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Server Public IP</label>
                            <input type="text" class="form-control" name="server_public_ip" value="<?= htmlspecialchars($wgSettings['server_public_ip'] ?? '') ?>" placeholder="Auto-detect if empty">
                            <div class="form-text">Public IP for MikroTik endpoints (auto-detected if blank)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Gateway IP (Tunnel)</label>
                            <input type="text" class="form-control" name="vpn_gateway_ip" value="<?= htmlspecialchars($wgSettings['vpn_gateway_ip']) ?>">
                            <div class="form-text">VPN server IP inside the tunnel (e.g., 10.200.0.1)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">VPN Network</label>
                            <input type="text" class="form-control" name="vpn_network" value="<?= htmlspecialchars($wgSettings['vpn_network']) ?>">
                            <div class="form-text">CIDR notation (e.g., 10.200.0.0/24)</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="tr069_use_vpn" name="tr069_use_vpn_gateway" <?= $wgSettings['tr069_use_vpn_gateway'] === 'true' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tr069_use_vpn">Use VPN Gateway for TR-069</label>
                            </div>
                            <div class="form-text">GenieACS will use VPN IP when enabled</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save me-1"></i>Save Settings
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Connection Legend</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-circle-fill text-success me-2"></i>
                        <span>Connected (handshake &lt; 3 min)</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-circle-fill text-warning me-2"></i>
                        <span>Stale (handshake &gt; 3 min)</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-circle-fill text-danger me-2"></i>
                        <span>Disconnected (no handshake)</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-circle-fill text-secondary me-2"></i>
                        <span>Disabled</span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-diagram-2 me-2"></i>Network Topology</h5>
                </div>
                <div class="card-body p-2">
                    <pre class="bg-light p-2 rounded small mb-0" style="font-family: monospace; font-size: 10px;">
┌──────────────────────────────┐
│         VPS (Cloud)          │
│  ┌──────┐ ┌───────┐ ┌─────┐ │
│  │ CRM  │ │GenieACS│ │ WG  │ │
│  └──────┘ └───────┘ └──┬──┘ │
└─────────────────────────│────┘
                          │ VPN
┌─────────────────────────│────┐
│      OLT Network        │    │
│  ┌─────────┐    ┌───────┴──┐ │
│  │   OLT   │◄───│ MikroTik │ │
│  └────┬────┘    └──────────┘ │
│       │                       │
│   ┌───┴───┐                  │
│   │ CPEs  │──► TR-069        │
│   └───────┘                  │
└──────────────────────────────┘
                    </pre>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addServerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add_vpn_server">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add VPN Server</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Server Name</label>
                        <input type="text" class="form-control" name="name" required placeholder="Main VPN Server">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interface Name</label>
                            <input type="text" class="form-control" name="interface_name" value="wg0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Listen Port</label>
                            <input type="number" class="form-control" name="listen_port" value="51820">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interface Address</label>
                        <input type="text" class="form-control" name="interface_addr" value="10.200.0.1/24" required>
                        <div class="form-text">Server IP in CIDR notation</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">DNS Servers (optional)</label>
                        <input type="text" class="form-control" name="dns_servers" placeholder="8.8.8.8, 1.1.1.1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Server</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addPeerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add_vpn_peer">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add VPN Peer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Server</label>
                        <select class="form-select" name="server_id" required>
                            <?php foreach ($wgServers as $server): ?>
                            <option value="<?= $server['id'] ?>"><?= htmlspecialchars($server['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peer Name</label>
                        <input type="text" class="form-control" name="name" required placeholder="OLT Site 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" placeholder="Main office OLT connection">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Allowed IPs</label>
                        <input type="text" class="form-control" name="allowed_ips" value="10.200.0.2/32" required>
                        <div class="form-text">IP assigned to this peer</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Endpoint (optional)</label>
                        <input type="text" class="form-control" name="endpoint" placeholder="vpn.example.com:51820">
                        <div class="form-text">Leave empty for dynamic endpoint</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_olt_site" name="is_olt_site" checked>
                            <label class="form-check-label" for="is_olt_site">This is an OLT site</label>
                        </div>
                    </div>
                    <div class="mb-3" id="oltSelectDiv">
                        <label class="form-label">Link to OLT</label>
                        <select class="form-select" name="olt_id">
                            <option value="">-- Select OLT --</option>
                            <?php foreach ($olts as $olt): ?>
                            <option value="<?= $olt['id'] ?>"><?= htmlspecialchars($olt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Peer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editPeerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update_vpn_peer">
                <input type="hidden" name="peer_id" id="edit_peer_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit VPN Peer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Peer Name</label>
                        <input type="text" class="form-control" name="name" id="edit_peer_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" id="edit_peer_description">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Allowed IPs / Subnets</label>
                        <input type="text" class="form-control" name="allowed_ips" id="edit_peer_allowed_ips" required>
                        <div class="form-text">Comma-separated. Example: 10.200.0.2/32, 10.78.0.0/24, 192.168.233.0/24</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Endpoint</label>
                        <input type="text" class="form-control" name="endpoint" id="edit_peer_endpoint" placeholder="IP:PORT">
                        <div class="form-text">Remote peer address (e.g., 102.205.239.250:51821)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Persistent Keepalive</label>
                        <input type="number" class="form-control" name="persistent_keepalive" id="edit_peer_keepalive" value="25">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_peer_is_olt_site" name="is_olt_site">
                            <label class="form-check-label" for="edit_peer_is_olt_site">This is an OLT site</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Save & Sync</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="configModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-file-code me-2"></i>Configuration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="configContent" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary" onclick="copyConfig()">
                    <i class="bi bi-clipboard me-1"></i>Copy
                </button>
                <button type="button" class="btn btn-primary" onclick="downloadConfig()">
                    <i class="bi bi-download me-1"></i>Download
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.currentConfigName = 'wireguard.conf';

function refreshStatus() {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking...';
    
    fetch('?page=api&action=vpn_peer_status')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.peers) {
                data.peers.forEach(peer => {
                    updatePeerStatus(peer);
                });
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refresh Status';
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refresh Status';
        });
}

function updatePeerStatus(peer) {
    const statusEl = document.querySelector(`.connection-status[data-peer-id="${peer.id}"]`);
    const handshakeEl = document.querySelector(`.last-handshake[data-peer-id="${peer.id}"]`);
    const trafficEl = document.querySelector(`.traffic-stats[data-peer-id="${peer.id}"]`);
    
    if (statusEl) {
        let iconClass = 'text-secondary';
        let title = 'Disabled';
        
        if (peer.is_active) {
            if (peer.connected) {
                iconClass = 'text-success';
                title = 'Connected';
            } else if (peer.stale) {
                iconClass = 'text-warning';
                title = 'Stale';
            } else {
                iconClass = 'text-danger';
                title = 'Disconnected';
            }
        }
        
        statusEl.innerHTML = `<i class="bi bi-circle-fill ${iconClass}" title="${title}"></i>`;
    }
    
    if (handshakeEl && peer.last_handshake) {
        handshakeEl.innerHTML = `<span class="text-success">${peer.last_handshake}</span>`;
    }
    
    if (trafficEl) {
        trafficEl.innerHTML = `
            <span class="text-success"><i class="bi bi-arrow-down"></i> ${peer.rx_formatted}</span><br>
            <span class="text-primary"><i class="bi bi-arrow-up"></i> ${peer.tx_formatted}</span>
        `;
    }
}

function viewServerConfig(serverId) {
    fetch(`?page=api&action=get_vpn_server_config&id=${serverId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('configContent').textContent = data.config;
                window.currentConfigName = `wg-server-${serverId}.conf`;
                new bootstrap.Modal(document.getElementById('configModal')).show();
            } else {
                alert('Error: ' + (data.error || 'Failed to load config'));
            }
        });
}

function viewPeerConfig(peerId) {
    fetch(`?page=api&action=get_vpn_peer_config&id=${peerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('configContent').textContent = data.config;
                window.currentConfigName = `wg-peer-${peerId}.conf`;
                new bootstrap.Modal(document.getElementById('configModal')).show();
            } else {
                alert('Error: ' + (data.error || 'Failed to load config'));
            }
        });
}

function viewMikroTikScript(peerId) {
    fetch(`?page=api&action=get_vpn_peer_mikrotik&id=${peerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('configContent').textContent = data.script;
                window.currentConfigName = `mikrotik-wg-peer-${peerId}.rsc`;
                new bootstrap.Modal(document.getElementById('configModal')).show();
            } else {
                alert('Error: ' + (data.error || 'Failed to load MikroTik script'));
            }
        });
}

function editPeer(peerId) {
    fetch(`?page=api&action=get_vpn_peer&id=${peerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.peer) {
                const peer = data.peer;
                document.getElementById('edit_peer_id').value = peer.id;
                document.getElementById('edit_peer_name').value = peer.name || '';
                document.getElementById('edit_peer_description').value = peer.description || '';
                document.getElementById('edit_peer_allowed_ips').value = peer.allowed_ips || '';
                document.getElementById('edit_peer_endpoint').value = peer.endpoint || '';
                document.getElementById('edit_peer_keepalive').value = peer.persistent_keepalive || 25;
                document.getElementById('edit_peer_is_olt_site').checked = peer.is_olt_site;
                new bootstrap.Modal(document.getElementById('editPeerModal')).show();
            } else {
                alert('Error loading peer: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
}

function deletePeer(peerId) {
    if (confirm('Are you sure you want to delete this peer?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="delete_vpn_peer">
            <input type="hidden" name="peer_id" value="${peerId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteServer(serverId) {
    if (confirm('Are you sure you want to delete this server? All associated peers will also be deleted.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="delete_vpn_server">
            <input type="hidden" name="server_id" value="${serverId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function copyConfig() {
    const content = document.getElementById('configContent').textContent;
    navigator.clipboard.writeText(content).then(() => {
        alert('Configuration copied to clipboard!');
    });
}

function downloadConfig() {
    const content = document.getElementById('configContent').textContent;
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = window.currentConfigName;
    a.click();
    URL.revokeObjectURL(url);
}

(function() {
    const isOltSiteEl = document.getElementById('is_olt_site');
    const oltSelectDivEl = document.getElementById('oltSelectDiv');
    if (isOltSiteEl && oltSelectDivEl) {
        isOltSiteEl.addEventListener('change', function() {
            oltSelectDivEl.style.display = this.checked ? 'block' : 'none';
        });
    }
})();
</script>
