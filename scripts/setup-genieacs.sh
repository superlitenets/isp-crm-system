#!/bin/bash

# GenieACS Pre-configuration Script
# Configures provisions and presets for auto-discovery of ONU parameters

GENIEACS_NBI_URL="${GENIEACS_NBI_URL:-http://localhost:7557}"

echo "=============================================="
echo "GenieACS Pre-Configuration Script"
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

# Create provision: refresh-all (discovers all device parameters)
echo ""
echo "Creating provision: refresh-all..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/refresh-all" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();
// Refresh TR-069 data model paths
declare("Device.*", {path: now, value: now});
declare("InternetGatewayDevice.*", {path: now, value: now});
'
echo " Done"

# Create provision: inform (basic inform handler)
echo "Creating provision: inform..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/inform" \
  -H "Content-Type: application/javascript" \
  -d '
// Basic inform - refresh key parameters
const now = Date.now();

// Device info
declare("DeviceID.Manufacturer", {value: now});
declare("DeviceID.OUI", {value: now});
declare("DeviceID.ProductClass", {value: now});
declare("DeviceID.SerialNumber", {value: now});

// Try both TR-098 and TR-181 paths
declare("InternetGatewayDevice.DeviceInfo.*", {path: now, value: now});
declare("Device.DeviceInfo.*", {path: now, value: now});
'
echo " Done"

# Create provision: wifi-config (WiFi parameter discovery)
echo "Creating provision: wifi-config..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/wifi-config" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// TR-098 WiFi paths (most ONUs)
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*", {path: now, value: now});

// TR-181 WiFi paths (newer devices)
declare("Device.WiFi.*", {path: now, value: now});
'
echo " Done"

# Create provision: wan-config (WAN/Internet parameter discovery)
echo "Creating provision: wan-config..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/wan-config" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// TR-098 WAN paths
declare("InternetGatewayDevice.WANDevice.*", {path: now, value: now});

// TR-181 WAN paths
declare("Device.IP.*", {path: now, value: now});
declare("Device.PPP.*", {path: now, value: now});
'
echo " Done"

# Create provision: full-refresh (complete device discovery)
echo "Creating provision: full-refresh..."
curl -s -X PUT "$GENIEACS_NBI_URL/provisions/full-refresh" \
  -H "Content-Type: application/javascript" \
  -d '
const now = Date.now();

// Full device tree refresh - TR-098
declare("InternetGatewayDevice.*", {path: now, value: now});

// Full device tree refresh - TR-181  
declare("Device.*", {path: now, value: now});

log("Full device refresh completed");
'
echo " Done"

# Create preset: bootstrap (runs on first connect)
echo ""
echo "Creating preset: bootstrap..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/bootstrap" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"0 BOOTSTRAP": true},
  "configurations": [
    {
      "type": "provision",
      "name": "full-refresh"
    }
  ]
}'
echo " Done"

# Create preset: periodic-refresh (runs on periodic inform)
echo "Creating preset: periodic-refresh..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/periodic-refresh" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"2 PERIODIC": true},
  "configurations": [
    {
      "type": "provision",
      "name": "inform"
    }
  ]
}'
echo " Done"

# Create preset: boot-refresh (runs on device reboot)
echo "Creating preset: boot-refresh..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/boot-refresh" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"1 BOOT": true},
  "configurations": [
    {
      "type": "provision",
      "name": "inform"
    },
    {
      "type": "provision", 
      "name": "wifi-config"
    }
  ]
}'
echo " Done"

# Set default periodic inform interval (5 minutes = 300 seconds)
echo ""
echo "Creating preset: set-periodic-inform..."
curl -s -X PUT "$GENIEACS_NBI_URL/presets/set-periodic-inform" \
  -H "Content-Type: application/json" \
  -d '{
  "weight": 0,
  "precondition": true,
  "events": {"0 BOOTSTRAP": true},
  "configurations": [
    {
      "type": "value",
      "name": "InternetGatewayDevice.ManagementServer.PeriodicInformEnable",
      "value": true
    },
    {
      "type": "value",
      "name": "InternetGatewayDevice.ManagementServer.PeriodicInformInterval",
      "value": 300
    },
    {
      "type": "value",
      "name": "Device.ManagementServer.PeriodicInformEnable", 
      "value": true
    },
    {
      "type": "value",
      "name": "Device.ManagementServer.PeriodicInformInterval",
      "value": 300
    }
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
echo "  - wan-config: WAN/Internet parameter discovery"
echo "  - full-refresh: Complete device tree discovery"
echo ""
echo "Created Presets:"
echo "  - bootstrap: Full refresh on first connect"
echo "  - periodic-refresh: Refresh on periodic inform"
echo "  - boot-refresh: Refresh WiFi on device reboot"
echo "  - set-periodic-inform: Enable 5-min periodic inform"
echo ""
echo "Next steps:"
echo "  1. Existing devices: Click 'Summon' in UI to trigger reconnect"
echo "  2. New devices: Will auto-discover on first connect"
echo "  3. View parameters in device details page"
echo ""
