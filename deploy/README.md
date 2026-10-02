# Testinstanz: dev.feuerwehr-quierschied.org

Ein Debian-LXC auf dem Proxmox-Host mit Docker Compose. Davor sitzt der zentrale
Traefik (öffentliches IPv4 und IPv6), der an den LXC weiterleitet.
Der LXC holt sich neue Versionen selbst (GitHub erreicht ihn nicht).

```
git push (dev) → GitHub Actions baut Image → ghcr.io/…:dev
LXC: systemd-Timer alle 5 Min → docker compose pull → up -d
Besucher → Traefik (:443, Zertifikat) → LXC intern (:8080)
```

## 0. LXC anlegen (Proxmox)

- Vorlage: Debian, unprivilegiert
- 1 Kern, 1 GB RAM, 8 GB Disk reichen für die Seite
- Netzwerk: intern an `vmbr1` (z. B. `10.10.10.2/24`, Gateway `10.10.10.1`)
- Docker im LXC braucht verschachtelte Container:

```bash
pct set <ID> --features nesting=1,keyctl=1
```

## 1. Ausgehendes IPv4 für den LXC (auf dem Proxmox-Host)

`ghcr.io` und `github.com` haben kein IPv6. Der Host leitet das interne Netz per NAT nach außen.

In `/etc/network/interfaces` auf dem Host (Ausgangs-Interface `vmbr0` ggf. anpassen):

```
auto vmbr1
iface vmbr1 inet static
    address 10.10.10.1/24
    bridge-ports none
    bridge-stp off
    bridge-fd 0
    post-up   echo 1 > /proc/sys/net/ipv4/ip_forward
    post-up   iptables -t nat -A POSTROUTING -s 10.10.10.0/24 -o vmbr0 -j MASQUERADE
    post-down iptables -t nat -D POSTROUTING -s 10.10.10.0/24 -o vmbr0 -j MASQUERADE
```

```bash
ifreload -a
```

Test im LXC:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" https://ghcr.io/v2/   # erwartet: 401
```

## 2. Docker im LXC

```bash
apt update && apt install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  > /etc/apt/sources.list.d/docker.list
apt update && apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

Anmeldung an der Registry (klassischer GitHub-Token mit `read:packages`):

```bash
docker login ghcr.io -u <github-user>
```

## 3. Dateien und Konfiguration

```bash
mkdir -p /opt/feuerwehr
```

Vom Mac aus:

```bash
scp deploy/docker-compose.yml deploy/feuerwehr-update.service deploy/feuerwehr-update.timer root@[<IPv6-des-LXC>]:/opt/feuerwehr/
```

`/opt/feuerwehr/.env` anlegen (nicht ins Repository!). `WEB_BIND` ist die interne
Adresse des LXC, damit die Seite nur über Traefik erreichbar ist:

```bash
cat > /opt/feuerwehr/.env <<EOF
APP_KEY=base64:$(openssl rand -base64 32)
WEB_BIND=10.10.10.2
EOF
chmod 600 /opt/feuerwehr/.env
```

## 4. Traefik

Den Inhalt von `traefik-dynamic.yml` im File-Provider des zentralen Traefik eintragen
und anpassen: interne Adresse des LXC, Entrypoint und Name des Zertifikats-Resolvers.

DNS: `dev.feuerwehr-quierschied.org` mit A- und AAAA-Eintrag auf die öffentlichen
Adressen des Traefik.

## 5. Starten und automatische Updates

```bash
cd /opt/feuerwehr
docker compose pull          # prüft Login und Image
docker compose up -d
curl -s -o /dev/null -w "%{http_code}\n" http://10.10.10.2:8080/up   # erwartet: 200

cp feuerwehr-update.service feuerwehr-update.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now feuerwehr-update.timer
```

Nützlich:

```bash
docker compose logs -f web             # Laravel-Logs
systemctl list-timers feuerwehr-update # nächster Update-Lauf
journalctl -u feuerwehr-update -n 30   # letzter Update-Lauf
systemctl start feuerwehr-update       # sofort aktualisieren
```
