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
  environment:
    - ENV_DB_HOST=isp_crm_db
    - ENV_DB_USER=crm
    - ENV_DB_PASSWORD=your_password
    - ENV_DB_NAME=isp_crm
```

## Key Configuration: Unknown Users

The `sites-enabled/default` file includes this block in the `authorize` section:

```
if (notfound || noop) {
    if (!&control:Cleartext-Password && !&control:NT-Password && !&control:SSHA-Password) {
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

This accepts unknown users and assigns them to the `expired-pool` so MikroTik can redirect them to the captive portal.

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
