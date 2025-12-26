# FreeRADIUS Server for ISP Billing

This FreeRADIUS Docker container integrates with the ISP RADIUS Billing module to provide AAA (Authentication, Authorization, Accounting) services for MikroTik routers.

## Deployment

FreeRADIUS is integrated into the main `docker-compose.yml` file. It starts automatically with other services:

```bash
docker-compose up -d --build
```

### Test RADIUS Authentication

```bash
docker exec -it isp_crm_freeradius radtest testuser testpassword localhost 0 testing123
```

## Configuration

### Client Configuration (NAS Devices)

Edit `config/clients.conf` to add your MikroTik routers:

```
client mikrotik-router-1 {
    ipaddr = 192.168.1.1
    secret = your-radius-secret
    require_message_authenticator = no
    nas_type = mikrotik
    shortname = router-1
}
```

Or configure NAS devices through the ISP Management UI - they will be loaded dynamically from the database.

### MikroTik Configuration

On your MikroTik router:

```routeros
/radius
add address=RADIUS-SERVER-IP secret=radiussecret service=ppp,hotspot timeout=3s

/ppp aaa
set use-radius=yes accounting=yes

/ip hotspot profile
set hsprof1 use-radius=yes radius-accounting=yes
```

## Database Tables Used

- `radius_subscriptions` - User credentials and service info
- `radius_packages` - Speed profiles and limits
- `radius_sessions` - Active/historical session tracking
- `radius_nas` - NAS device configuration (dynamic client loading)

## Ports

- **1812/UDP** - RADIUS Authentication
- **1813/UDP** - RADIUS Accounting
- **18120/TCP** - RADIUS Status Server (optional)

## Troubleshooting

### View Logs
```bash
docker logs -f freeradius
```

### Debug Mode
The container runs in debug mode by default (`-X` flag). For production, modify the Dockerfile CMD:
```dockerfile
CMD ["freeradius", "-f"]
```

### Common Issues

1. **Connection refused**: Check firewall rules for UDP 1812/1813
2. **Access-Reject**: Verify username/password in radius_subscriptions table
3. **Accounting not working**: Ensure session table has proper permissions

## Security Notes

- Change default secrets in `clients.conf` and on NAS devices
- Use strong passwords in the database
- Consider running behind VPN for remote NAS devices
