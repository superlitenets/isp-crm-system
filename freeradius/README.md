# FreeRADIUS Configuration for ISP CRM

This directory contains FreeRADIUS configuration files for the ISP CRM system.

## Features

- **Unknown User Support**: Unregistered users are accepted with `expired-pool` assignment for captive portal redirect
- **Expired/Suspended Users**: Automatically assigned to `expired-pool` with limited bandwidth
- **Data Quota Enforcement**: Users exceeding quota are assigned to `expired-pool`
- **PostgreSQL Integration**: Direct SQL queries against `radius_subscriptions` table

## Docker Volume Mounts

Add these volume mounts to your FreeRADIUS container in `docker-compose.yml`:

```yaml
freeradius:
  image: freeradius/freeradius-server:latest
  volumes:
    - ./freeradius/sites-enabled/default:/etc/freeradius/sites-enabled/default:ro
    - ./freeradius/mods-enabled/sql:/etc/freeradius/mods-enabled/sql:ro
    - ./freeradius/queries.conf:/etc/freeradius/mods-config/sql/main/postgresql/queries.conf:ro
    - ./freeradius/policy.d/unknown-users:/etc/freeradius/policy.d/unknown-users:ro
  environment:
    - ENV_DB_HOST=isp_crm_db
    - ENV_DB_USER=crm
    - ENV_DB_PASSWORD=your_password
    - ENV_DB_NAME=isp_crm
```

After updating, restart FreeRADIUS:
```bash
docker-compose restart freeradius
```

## Key Configuration: Unknown Users

The system uses a policy (`policy.d/unknown-users`) called at the end of the `authorize` section:

```
policy accept_unknown_users {
    if (!&control:Auth-Type) {
        update control {
            Auth-Type := Accept
        }
        update reply {
            Framed-Pool := "expired-pool"
            Mikrotik-Rate-Limit := "128k/128k"
            Session-Timeout := 300
            Acct-Interim-Interval := 60
        }
    }
}
```

This runs AFTER the `pap` module. If no `Auth-Type` was set (meaning no password was found for the user), it accepts with expired-pool assignment for captive portal redirect.

**For existing users**: SQL finds them → sets Cleartext-Password → pap sets Auth-Type → policy skipped
**For unknown users**: SQL returns notfound → no password → no Auth-Type → policy triggers → Accept with expired-pool

## MikroTik Configuration

Configure your MikroTik to redirect `expired-pool` users to the captive portal:

```
/ip hotspot walled-garden ip
add action=accept dst-address=YOUR_PORTAL_IP dst-port=80,443 comment="Allow portal access"

/ip firewall nat
add chain=dstnat src-address-list=expired-pool dst-port=80 protocol=tcp action=dst-nat to-addresses=YOUR_PORTAL_IP to-ports=80 comment="Redirect expired users to portal"
```

## Testing

Test RADIUS authentication:
```bash
radtest username password localhost 0 testing123
```

Check FreeRADIUS logs:
```bash
docker logs -f isp_crm_freeradius
```
