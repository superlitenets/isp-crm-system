FROM freeradius/freeradius-server:3.2.3

RUN apt-get update && apt-get install -y \
    freeradius-postgresql \
    && rm -rf /var/lib/apt/lists/*

COPY config/mods-enabled/ /etc/freeradius/3.0/mods-enabled/
COPY config/sites-enabled/ /etc/freeradius/3.0/sites-enabled/
COPY config/clients.conf /etc/freeradius/3.0/clients.conf
COPY config/radiusd.conf /etc/freeradius/3.0/radiusd.conf

RUN chown -R freerad:freerad /etc/freeradius/3.0/

EXPOSE 1812/udp 1813/udp 18120/tcp

CMD ["freeradius", "-X"]
