#!/bin/bash
# Restrict the Docker-published port 8097 (npcrm web) to the reverse proxy only.
# UFW INPUT rules do NOT filter Docker-published ports (kernel DNAT -> FORWARD),
# so the rule lives in DOCKER-USER and matches the ORIGINAL (pre-DNAT) dest port
# via conntrack. Idempotent: re-running replaces our own tagged rules.
set -u
PORT=8097
TAG="npcrm-lock-${PORT}"
IPT=/usr/sbin/iptables

$IPT -L DOCKER-USER -n >/dev/null 2>&1 || { echo "NO_DOCKER_USER (docker not up?)"; exit 1; }

# remove any rules we previously added (idempotent)
$IPT -S DOCKER-USER | grep -- "${TAG}" | sed 's/^-A /-D /' | while read -r l; do
  eval "$IPT $l" 2>/dev/null || true
done

C="-p tcp -m conntrack --ctorigdstport ${PORT} --ctdir ORIGINAL -m comment --comment ${TAG}"
# allow the proxy, this host, loopback, and docker-internal; drop everyone else
$IPT -A DOCKER-USER $C -s 192.168.1.118 -j RETURN
$IPT -A DOCKER-USER $C -s 192.168.1.141 -j RETURN
$IPT -A DOCKER-USER $C -s 127.0.0.1     -j RETURN
$IPT -A DOCKER-USER $C -s 172.16.0.0/12 -j RETURN
$IPT -A DOCKER-USER $C                   -j DROP

echo "APPLIED ${TAG}:"
$IPT -S DOCKER-USER | grep -- "${TAG}"
