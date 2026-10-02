# Testinstanz: dev.feuerwehr-quierschied.de

Ein Debian-LXC (oder eine VM) auf dem Proxmox-Host, Docker Compose, Caddy für HTTPS.
Die VM holt sich neue Versionen selbst (GitHub erreicht sie über IPv6 nicht).

```
git push → GitHub Actions baut Image → ghcr.io
VM: systemd-Timer alle 5 Min → docker compose pull → up -d
Besucher → Caddy (:443, Let's Encrypt) → web (:8080)
```

## 0. LXC anlegen (Proxmox)

- Vorlage: Debian 12, unprivilegiert
- 1 Kern, 1 GB RAM, 8 GB Disk reichen für die Seite
- Netzwerk: `eth0` an `vmbr0` mit eigener IPv6-Adresse, `eth1` an `vmbr1` (siehe Schritt 1)
- Docker im LXC braucht verschachtelte Container. Unter Optionen → Features
  `nesting` und `keyctl` aktivieren, oder auf dem Host:

```bash
pct set <ID> --features nesting=1,keyctl=1
```

Die eigene IPv6-Adresse des LXC bedeutet: Caddy kann die Ports 80/443 dort belegen,
ohne mit einem anderen Reverse-Proxy auf dem Host (z. B. Traefik) zu kollidieren.

## 1. Ausgehendes IPv4 für die VM/den LXC (auf dem Proxmox-Host)

`ghcr.io` und `github.com` haben kein IPv6. Die VM bekommt deshalb ein privates
IPv4-Netz, das der Host per NAT nach außen leitet.

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

Der VM eine zweite Netzwerkkarte an `vmbr1` geben, in der VM z. B.
`10.10.10.2/24`, Gateway `10.10.10.1`. IPv6 bleibt wie gehabt an `vmbr0`.

Test in der VM:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" https://ghcr.io/v2/   # erwartet: 401
```

## 2. Docker in der VM

```bash
apt update && apt install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  > /etc/apt/sources.list.d/docker.list
apt update && apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

Ist das Paket in GHCR privat (GitHub-Token mit `read:packages`):

```bash
docker login ghcr.io -u <github-user>
```

## 3. Dateien auf die VM

```bash
mkdir -p /opt/feuerwehr
# docker-compose.yml und Caddyfile aus diesem Ordner nach /opt/feuerwehr kopieren
```

In `docker-compose.yml` `ghcr.io/OWNER/REPO` anpassen.

`/opt/feuerwehr/.env` anlegen (nicht ins Repository!):

```
APP_KEY=base64:...
```

Den Schlüssel lokal erzeugen mit `php artisan key:generate --show`.

## 4. DNS

AAAA-Eintrag `dev.feuerwehr-quierschied.de` → IPv6-Adresse der VM.
Ports 80 und 443 in der Firewall freigeben. Caddy holt das Zertifikat beim ersten Start.

## 5. Starten und automatische Updates

```bash
cd /opt/feuerwehr && docker compose up -d

cp feuerwehr-update.service feuerwehr-update.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now feuerwehr-update.timer
```

Nützlich:

```bash
docker compose logs -f web             # Laravel-Logs
systemctl list-timers feuerwehr-update # nächster Update-Lauf
systemctl start feuerwehr-update       # sofort aktualisieren
```

## Hinweis IPv4-Besucher

Mit nur einem AAAA-Eintrag ist die Seite für Netze ohne IPv6 nicht erreichbar.
Für die Testinstanz ist das in Ordnung. Für den echten Betrieb: Ports 80/443 der
Host-IPv4 per DNAT auf die VM weiterleiten und zusätzlich einen A-Eintrag setzen.
