# LifeHub — Deploy (Docker + mövcud Caddy)

Domen: **https://lifehub.iso.com.az** · eyni server, eyni Caddy (`orderhub1_default`), Shisha ilə yanaşı.

## Arxitektura
```
İnternet → Caddy (80/443) ─┬─ /api/* , /storage/* → lifehub_api  (Laravel)
                           └─ qalan hər şey        → lifehub_web  (Next.js)
lifehub_api → lifehub_pgsql (host 5435) , lifehub_redis
```

## Portlar (serverdə boş olanlar)
- Postgres host portu **5435** (5432=dist-full, 5434=shisha məşğul).

---

## 1) Repoları serverə gətir (yan-yana)
```bash
cd /var/www/html          # və ya istədiyin ana qovluq
git clone <backend-repo>  LifeHub
git clone <frontend-repo> lifehub-web      # qovluq adı FRONTEND_PATH ilə uyğun
cd LifeHub
```

## 2) .env hazırla
```bash
cp .env.production .env
echo "APP_KEY=base64:$(openssl rand -base64 32)"     # çıxanı .env-ə yaz
openssl rand -hex 24                                  # DB_PASSWORD üçün (yalnız hərf-rəqəm!)
nano .env
```
Doldur: **APP_KEY**, **DB_PASSWORD** (hex, simvolsuz), **CADDY_NETWORK=orderhub1_default**.

## 3) Qaldır (BuildKit söndürülü — docker-compose v1)
```bash
DOCKER_BUILDKIT=0 docker-compose -p lifehub -f compose.prod.yaml up -d --build
docker ps | grep lifehub
docker logs lifehub_api --tail 30
```

## 4) İlk admini yarat
```bash
docker exec lifehub_api php artisan db:seed --force
```
Giriş: **username `admin`, parol `password`** — sonra dəyiş.

## 5) Caddy-yə domeni əlavə et
`/var/www/html/OrderHub1/Caddyfile`-a `deploy/Caddyfile.snippet` blokunu əlavə et, sonra:
```bash
docker exec 25baa62a526f_orderhub-caddy caddy reload --config /etc/caddy/Caddyfile
```
DNS: `lifehub.iso.com.az` A qeydi bu serverə → Caddy sertifikatı avtomatik.

---

## Yeniləmə
```bash
cd /var/www/html/LifeHub && git pull
cd /var/www/html/lifehub-web && git pull
cd /var/www/html/LifeHub
DOCKER_BUILDKIT=0 docker-compose -p lifehub -f compose.prod.yaml up -d --build
```

## Qeydlər
- Server docker-compose **v1.29.2** → həmişə `DOCKER_BUILDKIT=0` və `-p lifehub`.
- DB parolunda **simvol olmasın** (yalnız hərf-rəqəm) — yoxsa .env/compose parsinqi pozulur.
- `.env.production` image-ə düşmür (.dockerignore) — Laravel yalnız mount olunan `.env`-i oxuyur.
- Yüklənən şəkillər `lifehub_storage` volume-da qalır.
- Frontend qovluğu başqa adla olsa: `.env`-də `FRONTEND_PATH=` düzəlt.
