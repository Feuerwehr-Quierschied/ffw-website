# Testinstanz auf k3s

## Ablauf

1. `git push` auf `main`
2. GitHub Actions baut das Image (`.github/workflows/build.yml`) und legt es in der GitHub Container Registry ab:
   `ghcr.io/<owner>/<repo>:latest` und `ghcr.io/<owner>/<repo>:<commit>`
3. Kubernetes startet die Pods mit dem neuen Image (automatisch ab Phase 2)

Das Image enthält Code, Vendor-Pakete, gebautes CSS/JS und alle Dateien aus `public/` (z. B. das Logo).
Konfiguration und Geheimnisse kommen nicht ins Image, sondern aus ConfigMap und Secret.

## Einmalige Einrichtung auf dem Server

```bash
# k3s installieren (bringt Traefik als Ingress mit)
curl -sfL https://get.k3s.io | sh -

# APP_KEY lokal erzeugen und als Secret anlegen
php artisan key:generate --show
kubectl create namespace feuerwehr
kubectl -n feuerwehr create secret generic web-secrets --from-literal=APP_KEY='base64:...'
```

Ist das Paket in GHCR privat, braucht der Cluster Zugangsdaten
(GitHub Personal Access Token mit `read:packages`):

```bash
kubectl -n feuerwehr create secret docker-registry ghcr \
  --docker-server=ghcr.io --docker-username=<github-user> --docker-password=<token>
```

Dann im Deployment unter `spec.template.spec` ergänzen:

```yaml
imagePullSecrets:
  - name: ghcr
```

## Deployen

In `app.yaml` vorher anpassen: `ghcr.io/OWNER/REPO`, `APP_URL` und `host`.

```bash
kubectl apply -f k8s/app.yaml
kubectl -n feuerwehr get pods
```

Neues Image von Hand ausrollen (bis die Automatik steht):

```bash
kubectl -n feuerwehr rollout restart deployment/web
```
