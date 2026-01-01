# =====================================================
# MikroTik Expired Client Redirect Script
# For ISP RADIUS Billing System
# =====================================================
# 
# This script configures your MikroTik to redirect expired
# RADIUS clients to the captive portal for payment/renewal.
#
# REQUIREMENTS:
# 1. RADIUS configured and working
# 2. FreeRADIUS returning Framed-Pool=expired-pool for expired users
# 3. Web server accessible at the configured URL
#
# CUSTOMIZE THESE VARIABLES:
# =====================================================

:local expiredPoolName "expired-pool"
:local expiredPoolRange "10.99.99.2-10.99.99.254"
:local expiredPoolNetwork "10.99.99.0/24"
:local expiredGateway "10.99.99.1"
:local crmDomain "crm.superlite.co.ke"
:local crmIP "YOUR_CRM_SERVER_IP"
:local dnsServer "8.8.8.8"

# =====================================================
# 1. CREATE IP POOL FOR EXPIRED USERS
# =====================================================
/ip pool
:if ([:len [find name=$expiredPoolName]] = 0) do={
    add name=$expiredPoolName ranges=$expiredPoolRange
    :log info "Created expired pool: $expiredPoolName"
} else={
    :log info "Expired pool already exists"
}

# =====================================================
# 2. ADD IP ADDRESS FOR EXPIRED NETWORK GATEWAY
# =====================================================
/ip address
:if ([:len [find address="$expiredGateway/24"]] = 0) do={
    :local pppoeServer [/interface pppoe-server server find]
    :if ([:len $pppoeServer] > 0) do={
        :local pppoeInt [/interface pppoe-server server get [:pick $pppoeServer 0] interface]
        add address="$expiredGateway/24" interface=$pppoeInt comment="Expired users gateway"
    } else={
        :log warning "No PPPoE server found - add gateway manually to your LAN interface"
    }
    :log info "Added expired gateway: $expiredGateway"
}

# =====================================================
# 3. CONFIGURE PPP PROFILE FOR EXPIRED USERS
# =====================================================
/ppp profile
:if ([:len [find name="expired-profile"]] = 0) do={
    add name="expired-profile" \
        local-address=$expiredGateway \
        remote-address=$expiredPoolName \
        dns-server=$dnsServer \
        rate-limit="256k/256k" \
        comment="Profile for expired RADIUS users"
    :log info "Created expired-profile"
}

# =====================================================
# 4. FIREWALL - NAT REDIRECT FOR EXPIRED USERS
# =====================================================
/ip firewall nat
:if ([:len [find comment="Redirect expired to portal"]] = 0) do={
    add chain=dstnat \
        src-address=$expiredPoolNetwork \
        dst-port=80 \
        protocol=tcp \
        action=dst-nat \
        to-addresses=$crmIP \
        to-ports=80 \
        comment="Redirect expired to portal"
    :log info "Added HTTP redirect NAT rule"
}

:if ([:len [find comment="Redirect expired HTTPS"]] = 0) do={
    add chain=dstnat \
        src-address=$expiredPoolNetwork \
        dst-port=443 \
        protocol=tcp \
        action=dst-nat \
        to-addresses=$crmIP \
        to-ports=443 \
        comment="Redirect expired HTTPS"
    :log info "Added HTTPS redirect NAT rule"
}

# =====================================================
# 5. FIREWALL - ALLOW ACCESS TO CRM SERVER
# =====================================================
/ip firewall filter
:if ([:len [find comment="Allow expired to CRM"]] = 0) do={
    add chain=forward \
        src-address=$expiredPoolNetwork \
        dst-address=$crmIP \
        action=accept \
        comment="Allow expired to CRM"
    :log info "Added firewall rule to allow CRM access"
}

:if ([:len [find comment="Allow expired DNS"]] = 0) do={
    add chain=forward \
        src-address=$expiredPoolNetwork \
        dst-port=53 \
        protocol=udp \
        action=accept \
        comment="Allow expired DNS"
    :log info "Added DNS allow rule"
}

:if ([:len [find comment="Block expired other traffic"]] = 0) do={
    add chain=forward \
        src-address=$expiredPoolNetwork \
        action=drop \
        comment="Block expired other traffic"
    :log info "Added block rule for expired users"
}

# =====================================================
# 6. DNS STATIC ENTRY (OPTIONAL - FOR HTTPS REDIRECT)
# =====================================================
/ip dns static
:if ([:len [find name=$crmDomain]] = 0) do={
    add name=$crmDomain address=$crmIP
    :log info "Added DNS static entry for $crmDomain"
}

# =====================================================
# 7. ENABLE RADIUS COA (CHANGE OF AUTHORIZATION)
# =====================================================
/radius incoming
set accept=yes port=3799

:log info "Enabled RADIUS CoA on port 3799"

# =====================================================
# 8. MANGLE - MARK EXPIRED CONNECTIONS (OPTIONAL)
# =====================================================
/ip firewall mangle
:if ([:len [find comment="Mark expired connections"]] = 0) do={
    add chain=prerouting \
        src-address=$expiredPoolNetwork \
        action=mark-connection \
        new-connection-mark=expired-conn \
        passthrough=yes \
        comment="Mark expired connections"
    :log info "Added connection marking for expired users"
}

# =====================================================
# SUMMARY
# =====================================================
:log info "=== Expired Redirect Setup Complete ==="
:log info "Pool: $expiredPoolName ($expiredPoolRange)"
:log info "Gateway: $expiredGateway"
:log info "Redirect to: $crmDomain ($crmIP)"
:log info "CoA Port: 3799 (enabled)"
:log info ""
:log info "IMPORTANT: Update YOUR_CRM_SERVER_IP with actual IP!"
:log info "Test by connecting with an expired user account."
