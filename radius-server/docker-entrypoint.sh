#!/bin/bash
set -e

# Substitute only specific environment variables in SQL config
# (to avoid replacing FreeRADIUS internal variables like ${thread[pool]...})
envsubst '${POSTGRES_HOST} ${POSTGRES_PORT} ${POSTGRES_USER} ${POSTGRES_PASSWORD} ${POSTGRES_DB}' \
    < /etc/freeradius/3.0/mods-enabled/sql.template \
    > /etc/freeradius/3.0/mods-enabled/sql
chown freerad:freerad /etc/freeradius/3.0/mods-enabled/sql

# Start FreeRADIUS in debug mode
exec freeradius -X
