#!/bin/bash

# GenieACS Pre-configuration Script
# Configures provisions and presets for ONU provisioning including WAN configuration
# Version 2.0 - Enhanced WAN Provisioning for Huawei ONUs

GENIEACS_NBI_URL="${GENIEACS_NBI_URL:-http://localhost:7557}"

echo "=============================================="
echo "GenieACS Pre-Configuration Script v2.0"
echo "=============================================="
echo "NBI URL: $GENIEACS_NBI_URL"
echo ""

# Wait for GenieACS NBI to be ready
echo "Waiting for GenieACS NBI to be ready..."
for i in {1..30}; do
    if curl -s "$GENIEACS_NBI_URL/provisions" > /dev/null 2>&1; then
        echo "GenieACS NBI is ready!"
        break
    fi
    echo "Attempt $i/30 - waiting..."
    sleep 2
done

# =====================================================
# PROVISION: refresh-all - Discovers all device parameters
# =====================================================
echo ""
echo "Creating provision: refresh-all..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/refresh-all" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();
declare("Device.*", {path: now, value: now});
declare("InternetGatewayDevice.*", {path: now, value: now});
'
echo " Done"

# =====================================================
# PROVISION: inform - Basic inform handler
# =====================================================
echo "Creating provision: inform..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/inform" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();
declare("DeviceID.Manufacturer", {value: now});
declare("DeviceID.OUI", {value: now});
declare("DeviceID.ProductClass", {value: now});
declare("DeviceID.SerialNumber", {value: now});
declare("InternetGatewayDevice.DeviceInfo.*", {path: now, value: now});
declare("Device.DeviceInfo.*", {path: now, value: now});
'
echo " Done"

# =====================================================
# PROVISION: wifi-config - WiFi parameter discovery
# =====================================================
echo "Creating provision: wifi-config..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/wifi-config" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*", {path: now, value: now});
declare("InternetGatewayDevice.WLANConfiguration.*", {path: now, value: now});
declare("Device.WiFi.*", {path: now, value: now});
'
echo " Done"

# =====================================================
# PROVISION: wan-discover - Force discovery of WAN/LAN objects
# Huawei ONUs hide objects until explicitly declared
# Uses correct GenieACS declare syntax: declare(path, timestamps, values)
# =====================================================
echo "Creating provision: wan-discover..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/wan-discover" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Force full WAN discovery - request fresh path info from device
// {path: now} in timestamps forces rediscovery of child objects
declare("InternetGatewayDevice.WANDevice.*", {path: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*", {path: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANPPPConnection.*", {path: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANIPConnection.*", {path: now});

// Force LAN / WiFi discovery
declare("InternetGatewayDevice.LANDevice.*", {path: now});
declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.*", {path: now});

// Layer 3 forwarding
declare("InternetGatewayDevice.Layer3Forwarding.*", {path: now, value: now});

// Device info and management server
declare("InternetGatewayDevice.DeviceInfo.*", {path: now, value: now});
declare("InternetGatewayDevice.ManagementServer.*", {path: now, value: now});

log("WAN/LAN discovery completed");
'
echo " Done"

# =====================================================
# PROVISION: wan-create - Create WAN objects for PPPoE/IPoE
# This provision creates the necessary WAN structure:
# - WANDevice.1.WANConnectionDevice.1
# - WANPPPConnection.* (for PPPoE) or WANIPConnection.* (for IPoE)
# Uses correct GenieACS syntax: declare(path, timestamps, values)
# =====================================================
echo "Creating provision: wan-create..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/wan-create" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Get parameters from args
let wanDeviceIndex = args[0] || 1;
let wanConnDeviceIndex = args[1] || 1;
let connectionType = args[2] || "pppoe"; // pppoe or ipoe

log("WAN Create: wanDevice=" + wanDeviceIndex + ", wanConnDevice=" + wanConnDeviceIndex + ", type=" + connectionType);

// First refresh the WAN structure to discover what exists
declare("InternetGatewayDevice.WANDevice.*", {path: now});
declare("InternetGatewayDevice.WANDevice." + wanDeviceIndex + ".WANConnectionDevice.*", {path: now});

if (connectionType === "pppoe") {
    // Ensure we have a WANPPPConnection instance (creates one if none exist)
    // {path: 1} in values means "ensure at least 1 instance exists"
    log("WAN Create: Creating WANPPPConnection (if necessary)");
    declare("InternetGatewayDevice.WANDevice." + wanDeviceIndex + ".WANConnectionDevice." + wanConnDeviceIndex + ".WANPPPConnection.*", null, {path: 1});
    
    // Refresh the newly created node
    declare("InternetGatewayDevice.WANDevice." + wanDeviceIndex + ".WANConnectionDevice." + wanConnDeviceIndex + ".WANPPPConnection.*.*", {path: now});
} else {
    // Ensure we have a WANIPConnection instance
    log("WAN Create: Creating WANIPConnection (if necessary)");
    declare("InternetGatewayDevice.WANDevice." + wanDeviceIndex + ".WANConnectionDevice." + wanConnDeviceIndex + ".WANIPConnection.*", null, {path: 1});
    
    // Refresh the newly created node
    declare("InternetGatewayDevice.WANDevice." + wanDeviceIndex + ".WANConnectionDevice." + wanConnDeviceIndex + ".WANIPConnection.*.*", {path: now});
}

log("WAN Create: Structure creation complete");
'
echo " Done"

# =====================================================
# PROVISION: wan-pppoe-config - Configure PPPoE credentials and settings
# Args: username, password, vlan, wanDeviceIndex, wanConnDeviceIndex, pppConnIndex
# Assumes WANPPPConnection already exists (use wan-create first)
# =====================================================
echo "Creating provision: wan-pppoe-config..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/wan-pppoe-config" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Get parameters from args
let username = args[0] || "";
let password = args[1] || "";
let vlan = parseInt(args[2]) || 0;
let wanDeviceIndex = args[3] || 1;
let wanConnDeviceIndex = args[4] || 1;

if (!username || !password) {
    log("WAN PPPoE Config: ERROR - Username and password required");
    return;
}

log("WAN PPPoE Config: user=" + username + ", vlan=" + vlan);

// Build base path with wildcard to apply to all PPP instances
let basePath = "InternetGatewayDevice.WANDevice." + wanDeviceIndex + 
               ".WANConnectionDevice." + wanConnDeviceIndex + 
               ".WANPPPConnection.*";

// First refresh the PPP connection to get current state
declare(basePath + ".*", {path: now});

// Set PPPoE parameters - {value: now} ensures we are setting fresh values
declare(basePath + ".Name", {value: now}, {value: "Internet_PPPoE"});
declare(basePath + ".ConnectionType", {value: now}, {value: "IP_Routed"});
declare(basePath + ".NATEnabled", {value: now}, {value: true});
declare(basePath + ".Username", {value: now}, {value: username});
declare(basePath + ".Password", {value: now}, {value: password});
declare(basePath + ".Enable", {value: now}, {value: true});

// Set VLAN if specified (Huawei specific parameter)
if (vlan > 0) {
    declare(basePath + ".X_HW_VLAN", {value: now}, {value: vlan});
}

// Refresh Layer3Forwarding and set as default connection
declare("InternetGatewayDevice.Layer3Forwarding.*", {value: now});
declare("InternetGatewayDevice.Layer3Forwarding.X_HW_DefaultConnectionService", {value: now}, 
        {value: "InternetGatewayDevice.WANDevice." + wanDeviceIndex + ".WANConnectionDevice." + wanConnDeviceIndex + ".WANPPPConnection.1"});

log("WAN PPPoE Config: Configuration complete");
'
echo " Done"

# =====================================================
# PROVISION: wan-ipoe-config - Configure IPoE/DHCP settings
# Args: vlan, addressingType, wanDeviceIndex, wanConnDeviceIndex
# Assumes WANIPConnection already exists (use wan-create first)
# =====================================================
echo "Creating provision: wan-ipoe-config..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/wan-ipoe-config" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Get parameters from args
let vlan = parseInt(args[0]) || 0;
let addressingType = args[1] || "DHCP"; // DHCP or Static
let wanDeviceIndex = args[2] || 1;
let wanConnDeviceIndex = args[3] || 1;

log("WAN IPoE Config: vlan=" + vlan + ", addressing=" + addressingType);

// Build base path with wildcard
let basePath = "InternetGatewayDevice.WANDevice." + wanDeviceIndex + 
               ".WANConnectionDevice." + wanConnDeviceIndex + 
               ".WANIPConnection.*";

// First refresh the IP connection to get current state
declare(basePath + ".*", {path: now});

// Set IPoE parameters
declare(basePath + ".Name", {value: now}, {value: "Internet_IPoE"});
declare(basePath + ".ConnectionType", {value: now}, {value: "IP_Routed"});
declare(basePath + ".AddressingType", {value: now}, {value: addressingType});
declare(basePath + ".NATEnabled", {value: now}, {value: true});
declare(basePath + ".Enable", {value: now}, {value: true});

// Set VLAN if specified (Huawei specific parameter)
if (vlan > 0) {
    declare(basePath + ".X_HW_VLAN", {value: now}, {value: vlan});
}

// Refresh Layer3Forwarding and set as default connection
declare("InternetGatewayDevice.Layer3Forwarding.*", {value: now});
declare("InternetGatewayDevice.Layer3Forwarding.X_HW_DefaultConnectionService", {value: now}, 
        {value: "InternetGatewayDevice.WANDevice." + wanDeviceIndex + ".WANConnectionDevice." + wanConnDeviceIndex + ".WANIPConnection.1"});

log("WAN IPoE Config: Configuration complete");
'
echo " Done"

# =====================================================
# PROVISION: wan-bridge-config - Configure Bridge mode WAN
# Args: vlan, wanDeviceIndex, wanConnDeviceIndex, ipConnIndex
# =====================================================
echo "Creating provision: wan-bridge-config..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/wan-bridge-config" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Get parameters from args
let vlan = parseInt(args[0]) || 0;
let wanDeviceIndex = args[1] || 1;
let wanConnDeviceIndex = args[2] || 1;
let ipConnIndex = args[3] || 1;

log("WAN Bridge Config: vlan=" + vlan);

// Build base path
let basePath = "InternetGatewayDevice.WANDevice." + wanDeviceIndex + 
               ".WANConnectionDevice." + wanConnDeviceIndex + 
               ".WANIPConnection." + ipConnIndex;

// Set Bridge mode parameters
declare(basePath + ".Enable", {value: now}, {value: true});
declare(basePath + ".ConnectionType", {value: now}, {value: "IP_Bridged"});
declare(basePath + ".Name", {value: now}, {value: "Bridge"});

// Set VLAN if specified
if (vlan > 0) {
    declare(basePath + ".X_HW_VLAN", {value: now}, {value: vlan});
}

log("WAN Bridge Config: Configuration complete for " + basePath);
'
echo " Done"

# =====================================================
# PROVISION: ntp-config - Configure NTP for time sync
# =====================================================
echo "Creating provision: ntp-config..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/ntp-config" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

log("NTP Config: Configuring time servers");

// Standard NTP paths for TR-098 (InternetGatewayDevice)
declare("InternetGatewayDevice.Time.Enable", {value: now}, {value: true});
declare("InternetGatewayDevice.Time.NTPServer1", {value: now}, {value: "pool.ntp.org"});
declare("InternetGatewayDevice.Time.NTPServer2", {value: now}, {value: "time.google.com"});
declare("InternetGatewayDevice.Time.LocalTimeZoneName", {value: now}, {value: "Africa/Nairobi"});

// TR-181 (Device) paths
declare("Device.Time.Enable", {value: now}, {value: true});
declare("Device.Time.NTPServer1", {value: now}, {value: "pool.ntp.org"});
declare("Device.Time.NTPServer2", {value: now}, {value: "time.google.com"});

log("NTP Config: Complete");
'
echo " Done"

# =====================================================
# PROVISION: management-config - Configure management access (disable WAN HTTP)
# =====================================================
echo "Creating provision: management-config..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/management-config" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Disable WAN-side HTTP management for security
// Huawei specific paths
declare("InternetGatewayDevice.UserInterface.RemoteAccess.Enable", {value: now}, {value: false});
declare("InternetGatewayDevice.X_HW_WebUserInterface.WANAccessEnabled", {value: now}, {value: false});

// Keep LAN-side access enabled
declare("InternetGatewayDevice.X_HW_WebUserInterface.LANAccessEnabled", {value: now}, {value: true});

log("Management Config: WAN HTTP disabled for security");
'
echo " Done"

# =====================================================
# PROVISION: full-refresh - Complete device discovery
# Forces discovery of all parameters from device
# =====================================================
echo "Creating provision: full-refresh..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/full-refresh" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Force full discovery - {path: now} requests fresh path info from device
declare("InternetGatewayDevice.DeviceInfo.*", {path: now, value: now});
declare("InternetGatewayDevice.ManagementServer.*", {path: now, value: now});
declare("InternetGatewayDevice.WANDevice.*", {path: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*", {path: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANPPPConnection.*", {path: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANIPConnection.*", {path: now});
declare("InternetGatewayDevice.LANDevice.*", {path: now});
declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.*", {path: now});
declare("InternetGatewayDevice.Layer3Forwarding.*", {path: now, value: now});
declare("InternetGatewayDevice.Time.*", {path: now, value: now});

// TR-181 devices
declare("Device.DeviceInfo.*", {path: now, value: now});
declare("Device.ManagementServer.*", {path: now, value: now});

log("Full device refresh completed");
'
echo " Done"

# =====================================================
# PROVISION: periodic-wan-check - Check WAN status periodically
# =====================================================
echo "Creating provision: periodic-wan-check..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/periodic-wan-check" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Refresh WAN connection status
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANPPPConnection.*.ConnectionStatus", {value: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANPPPConnection.*.ExternalIPAddress", {value: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANPPPConnection.*.Uptime", {value: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANIPConnection.*.ConnectionStatus", {value: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANIPConnection.*.ExternalIPAddress", {value: now});

// Also refresh WiFi status
declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.*.Status", {value: now});

log("Periodic WAN/WiFi status refresh completed");
'
echo " Done"

# =====================================================
# PROVISION: huawei-wan-pppoe - Complete Huawei PPPoE setup
# This is the main provision for configuring PPPoE on Huawei ONUs
# Args: username, password, vlan
# Uses correct GenieACS syntax based on official wiki examples
# =====================================================
echo "Creating provision: huawei-wan-pppoe..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/huawei-wan-pppoe" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

let username = args[0] || "";
let password = args[1] || "";
let vlan = parseInt(args[2]) || 0;

if (!username || !password) {
    log("Huawei WAN PPPoE: ERROR - Username and password are required");
    return;
}

log("Huawei WAN PPPoE: Starting for user " + username + " with VLAN " + vlan);

// First refresh the WAN structure to discover what exists
declare("InternetGatewayDevice.WANDevice.*", {path: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*", {path: now});

// Disable any existing WANIPConnection (we want PPPoE, not IPoE)
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.*.Enable", {path: now, value: now}, {value: false});

// Ensure we have a WANPPPConnection instance (creates one if none exist)
log("Huawei WAN PPPoE: Creating WANPPPConnection (if necessary)");
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*", null, {path: 1});

// Refresh the newly created node to get all parameters
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.*", {path: now});

// Configure PPPoE parameters using wildcard to apply to all instances
log("Huawei WAN PPPoE: Setting PPPoE parameters");
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.Name", {value: now}, {value: "Internet_PPPoE"});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.ConnectionType", {value: now}, {value: "IP_Routed"});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.NATEnabled", {value: now}, {value: true});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.Username", {value: now}, {value: username});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.Password", {value: now}, {value: password});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.Enable", {value: now}, {value: true});

// Huawei specific VLAN configuration
if (vlan > 0) {
    log("Huawei WAN PPPoE: Setting VLAN " + vlan);
    declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.X_HW_VLAN", {value: now}, {value: vlan});
}

// Set as default WAN connection (Huawei specific) - refresh first
declare("InternetGatewayDevice.Layer3Forwarding.*", {value: now});
declare("InternetGatewayDevice.Layer3Forwarding.X_HW_DefaultConnectionService", {value: now}, 
        {value: "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1"});

// Refresh the status to see if it connected
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.ConnectionStatus", {value: now});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.*.ExternalIPAddress", {value: now});

log("Huawei WAN PPPoE: Configuration complete");
'
echo " Done"

# =====================================================
# PRESETS - Event-based triggers for provisions
# =====================================================

echo ""
echo "Creating presets..."

# Preset: bootstrap - Full refresh on first connect
echo "Creating preset: bootstrap..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/bootstrap" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"0 BOOTSTRAP": true},
  "configurations": [
    {"type": "provision", "name": "full-refresh"},
    {"type": "provision", "name": "ntp-config"}
  ]
}'
echo " Done"

# Preset: periodic-refresh - Refresh on periodic inform
echo "Creating preset: periodic-refresh..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/periodic-refresh" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"2 PERIODIC": true},
  "configurations": [
    {"type": "provision", "name": "inform"},
    {"type": "provision", "name": "periodic-wan-check"}
  ]
}'
echo " Done"

# Preset: boot-refresh - Refresh on device reboot
echo "Creating preset: boot-refresh..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/boot-refresh" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"1 BOOT": true},
  "configurations": [
    {"type": "provision", "name": "inform"},
    {"type": "provision", "name": "wifi-config"},
    {"type": "provision", "name": "wan-discover"}
  ]
}'
echo " Done"

# Preset: set-periodic-inform - Enable 5-min periodic inform
echo "Creating preset: set-periodic-inform..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/set-periodic-inform" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"0 BOOTSTRAP": true},
  "configurations": [
    {"type": "value", "name": "InternetGatewayDevice.ManagementServer.PeriodicInformEnable", "value": true},
    {"type": "value", "name": "InternetGatewayDevice.ManagementServer.PeriodicInformInterval", "value": 300},
    {"type": "value", "name": "Device.ManagementServer.PeriodicInformEnable", "value": true},
    {"type": "value", "name": "Device.ManagementServer.PeriodicInformInterval", "value": 300}
  ]
}'
echo " Done"

# Preset: connection-request - Handles connection request events
echo "Creating preset: connection-request..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/connection-request" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"6 CONNECTION REQUEST": true},
  "configurations": [
    {"type": "provision", "name": "wan-discover"}
  ]
}'
echo " Done"

# Preset: huawei-onu - Specific preset for Huawei ONUs
echo "Creating preset: huawei-onu..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/huawei-onu" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 10,
  "precondition": "DeviceID.Manufacturer LIKE \"%Huawei%\" OR DeviceID.OUI = \"00259E\"",
  "events": {"0 BOOTSTRAP": true, "1 BOOT": true},
  "configurations": [
    {"type": "provision", "name": "full-refresh"},
    {"type": "provision", "name": "wan-discover"},
    {"type": "provision", "name": "wifi-config"},
    {"type": "provision", "name": "ntp-config"}
  ]
}'
echo " Done"

echo ""
echo "=============================================="
echo "GenieACS Pre-Configuration Complete!"
echo "=============================================="
echo ""
echo "Created Provisions:"
echo "  - refresh-all: Discover all device parameters"
echo "  - inform: Basic device info refresh"
echo "  - wifi-config: WiFi parameter discovery"
echo "  - wan-discover: WAN structure discovery"
echo "  - wan-create: Create WAN objects (WANDevice/WANConnectionDevice/WANPPPConnection)"
echo "  - wan-pppoe-config: Configure PPPoE credentials and settings"
echo "  - wan-ipoe-config: Configure IPoE/DHCP settings"
echo "  - wan-bridge-config: Configure Bridge mode"
echo "  - ntp-config: Configure NTP time servers"
echo "  - management-config: Disable WAN HTTP for security"
echo "  - full-refresh: Complete device tree discovery"
echo "  - periodic-wan-check: Check WAN status periodically"
echo "  - huawei-wan-pppoe: Complete Huawei PPPoE setup"
echo ""
echo "Created Presets:"
echo "  - bootstrap: Full refresh + NTP on first connect"
echo "  - periodic-refresh: Refresh on periodic inform (5 min)"
echo "  - boot-refresh: Refresh WiFi/WAN on device reboot"
echo "  - set-periodic-inform: Enable 5-min periodic inform"
echo "  - connection-request: WAN discovery on connection request"
echo "  - huawei-onu: Huawei-specific preset with full discovery"
echo ""
echo "Usage from PHP/API:"
echo "  To configure PPPoE on a device:"
echo "    POST /devices/{deviceId}/tasks"
echo "    {\"name\": \"provision\", \"provision\": \"huawei-wan-pppoe\", \"args\": [\"username\", \"password\", \"902\"]}"
echo ""
echo "  Or use the individual provisions:"
echo "    1. POST /devices/{id}/tasks {\"name\": \"provision\", \"provision\": \"wan-create\", \"args\": [1, 1, \"pppoe\"]}"
echo "    2. POST /devices/{id}/tasks {\"name\": \"provision\", \"provision\": \"wan-pppoe-config\", \"args\": [\"user\", \"pass\", 902]}"
echo ""
