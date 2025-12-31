FROM freeradius/freeradius-server:3.2.3

RUN apt-get update && apt-get install -y \
    freeradius-postgresql \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

COPY config/mods-enabled/ /etc/freeradius/3.0/mods-enabled/
COPY config/mods-enabled/sql.template /etc/freeradius/3.0/mods-enabled/sql.template
COPY config/mods-config/ /etc/freeradius/3.0/mods-config/
COPY config/sites-enabled/ /etc/freeradius/3.0/sites-enabled/
COPY config/policy.d/ /etc/freeradius/3.0/policy.d/
COPY config/clients.conf /etc/freeradius/3.0/clients.conf
COPY config/radiusd.conf /etc/freeradius/3.0/radiusd.conf
COPY config/proxy.conf /etc/freeradius/3.0/proxy.conf
COPY config/huntgroups /etc/freeradius/3.0/huntgroups
COPY config/hints /etc/freeradius/3.0/hints
COPY config/users /etc/freeradius/3.0/users
COPY config/acct_users /etc/freeradius/3.0/acct_users
COPY docker-entrypoint.sh /docker-entrypoint.sh

RUN chmod +x /docker-entrypoint.sh && \
    chown -R freerad:freerad /etc/freeradius/3.0/

EXPOSE 1812/udp 1813/udp

ENTRYPOINT ["/docker-entrypoint.sh"]
