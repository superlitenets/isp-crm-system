FROM freeradius/freeradius-server:3.2.3

RUN apt-get update && apt-get install -y \
    freeradius-postgresql \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

# Copy to /etc/freeradius/ (without 3.0) - the actual config location
COPY config/mods-enabled/ /etc/freeradius/mods-enabled/
COPY config/mods-enabled/sql.template /etc/freeradius/mods-enabled/sql.template
COPY config/mods-config/ /etc/freeradius/mods-config/
COPY config/sites-enabled/ /etc/freeradius/sites-enabled/
COPY config/policy.d/ /etc/freeradius/policy.d/
COPY config/clients.conf /etc/freeradius/clients.conf
COPY config/radiusd.conf /etc/freeradius/radiusd.conf
COPY config/proxy.conf /etc/freeradius/proxy.conf
COPY config/huntgroups /etc/freeradius/huntgroups
COPY config/hints /etc/freeradius/hints
COPY config/users /etc/freeradius/users
COPY config/acct_users /etc/freeradius/acct_users
COPY docker-entrypoint.sh /docker-entrypoint.sh

RUN chmod +x /docker-entrypoint.sh && \
    chown -R freerad:freerad /etc/freeradius/

EXPOSE 1812/udp 1813/udp

ENTRYPOINT ["/docker-entrypoint.sh"]
