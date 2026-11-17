# Canlıya çıkış planı

Hazırlanma: 2026-11-16. Her faz **ayrı bir sohbette** uygulanır. Yeni oturuma şöyle başla:

> `docs/launch-plan.md` dosyasındaki **Faz N**'yi uygula.

Oturum kuralları:
- Sadece o fazın bölümünü ve fazda adı geçen dosyaları oku; tüm projeyi yeniden keşfetme.
- CLAUDE.md'deki iş akışı geçerli: testleri çalıştır, değişen dosyaları özetle, commit atma.
- Faz bitince aşağıdaki durum tablosunu güncelle ve bulduğun yeni bilgileri ilgili faz bölümüne kısa not olarak ekle.
- "Sorulacak" diye işaretli konularda kod yazmadan önce kullanıcıya sor.

**Ödeme (2026-11-18, yapıldı):** iyzico Checkout Form ile dönemlik manuel ödeme, kayıtta "hemen öde", makbuz, hatırlatma mailleri, webhook ve mutabakat; ayrıntılar [subscriptions.md § Payments](architecture/subscriptions.md#payments). Canlıya çıkış için kullanıcı kontrol listesi orada.

**Kapsam dışı (ayrı iş):** otomatik tekrarlı çekim, kıst hesabı, e-Arşiv entegrasyonu, proxy / HTTPS (`trustProxies`, SSL sonlandırma, HSTS).

## Durum

| Faz | Konu | Boyut | Durum |
|---|---|---|---|
| 0 | Kapsam ve doküman güncellemesi | S | ✅ 2026-11-16 |
| 1 | Hızlı işler: hata sayfaları, log, env örneği, robots | S | ✅ 2026-11-16 |
| 2 | Canlı ortam altyapısı (Compose, image, nginx, deploy) | M | ✅ 2026-11-16 |
| 3 | E-posta | S | ✅ 2026-11-16 |
| 4 | Yedekleme | M | ⬜ |
| 5 | İzleme (Sentry, kontroller) | S | ✅ 2026-11-16 |
| 6 | Temalar vitrini için canlı seed | S | ✅ 2026-11-16 |
| 9 | İki adımlı doğrulama (MFA) | S | ✅ 2026-11-16 |
| 7 | Ekip yönetimi `/app` | L | ✅ 2026-11-16 |
| 8 | Kapsamlı admin + impersonation | L | ✅ 2026-11-16 |
| 10 | Hukuk ve KVKK | S + kullanıcı | 🟡 kod 2026-11-16 (testler çalıştırılmadı), kullanıcı adımları açık |
| 11 | Plan ve fiyatlar | kullanıcı | 🟡 kod 2026-11-16 (testler çalıştırılmadı), planlar/fiyatlar kullanıcıda |
| 12 | Lansman sonrası iyileştirmeler | — | ⬜ |

Önerilen sıra: 0 → 1 → 2 → 3 → 4 → 5 → 6 → 9 → 7 → 8. Faz 10 ve 11 paralel yürür. Lansman 7 ve 8'i beklemezse ikisi lansman sonrasına kayabilir.

## Kullanıcının verdiği kararlar (2026-11-16)

- Sunucu: **tek VPS + Docker Compose**.
- Görseller: başlangıçta **VPS diski** (`MEDIA_DRIVER=local`). S3 paketi kurulmaz.
- Ekip yönetimi: **`/app` içinde davetli sistem**; üye sayısı **`max_members`** plan limitine bağlı.
- Admin: **kapsamlı** işletme ve kullanıcı yönetimi, **sınırlı impersonation** dahil.
- Mail: **SMTP üzerinden, sağlayıcıdan bağımsız**. Sağlayıcıyı kullanıcı seçecek; yeni paket yok.
- Hata takibi: **Sentry**. `sentry/sentry-laravel` paketini kurmadan önce son bir onay istenir.

---

## Faz 0: Kapsam ve dokümanlar (S)

Ekip yönetimi ve admin'de işletme/kullanıcı yönetimi şu an kapsam dışı yazıyor. Kodlamadan önce dokümanlar güncellenmeli.

- `CLAUDE.md` §1 ("Out of scope" listesi): ekip yönetimini ve admin'de işletme/kullanıcı yönetimini kapsama al. §3.15–16'ya davet akışını ve impersonation'ı ekle.
- `docs/architecture/overview.md`: kapsam tablosu ve yol haritası. Bu plana link ver.
- `.claude/rules/security.md`: davet token kuralları, impersonation sınırları, admin MFA zorunluluğu.
- `docs/architecture/tenancy.md` § Allowed scope bypasses: admin kaynakları tüm işletmeleri listelemek için tenant scope'unu atlamak zorunda. `TenantScopeBypassTest` bugün izinli yerler dışındaki her `withoutTenantScope()` çağrısında düşüyor. Admin için dar bir izinli alan tanımla (ör. `app/Filament/Admin/Resources/Tenants/**` ve ilgili servis), gerekirse testi buna göre güncelle.
- Kod yok. Bitiş ölçütü: dokümanlar tutarlı ve plandaki kararları yansıtıyor.

**Notlar (2026-11-16, tamamlandı):**
- `Tenant`, `User`, `TenantMembership` tenant-scoped değil; admin'in bypass'a ihtiyacı yalnızca `Location`, `Subscription`, `SubscriptionEvent` için. tenancy.md'de bu üç model izin listesine alındı; menü içeriği, QR, tema ayarları ve medya admin'de tenant'lar arası okunmaz.
- İzinli alan: `app/Filament/Admin/Resources/Tenants/**` ve `app/Services/Admin/**`. Kurallar: sadece okuma, açık `where('tenant_id', …)`, sadece admin paneli + policy. `TenantScopeBypassTest`'e `ALLOWED_PREFIXES` eklendi (dizinler henüz yok). `UserResource` bypass gerektirmiyor; gerekirse Faz 8'de alan genişletilip doküman güncellenir.
- Impersonation bypass gerektirmez (normal `/app` üyelik çözümlemesinden geçer).
- security.md'ye yeni bölümler: admin MFA, admin platform yönetimi + audit, impersonation, ekip davetleri (token ≥ 32 byte, hash'li, tek kullanımlık, 7 gün).
- `php artisan test --parallel` çalışmıyor (paratest kurulu değil); süit sıralı ~2,5 dk.

## Faz 1: Hızlı işler (S)

Bulgular: `resources/views/errors/` yok; `LOG_STACK=single`; `.env.example` içinde `APP_NAME=Laravel`; `public/robots.txt` her ortamda her şeye izin veriyor.

- **Hata sayfaları:** `resources/views/errors/{403,404,419,429,500,503}.blade.php`, tr/en (`lang/*/errors.php`).
  - Merkezi domain'de marketing tasarımı (`components/ui`, `app.css`).
  - Menü host'larında (`{slug}.restano.app`, `/m/*`) işletme bilgisi göstermeyen sade platform sayfası; Livewire, app.js veya oturum yok.
  - `menu.unavailable` (503, `bootstrap/app.php`) mevcut haliyle kalır, bozulmadığını test et.
- **Log:** `.env.example` → `LOG_STACK=daily`; `config/logging.php` daily kanalı `days=14`.
- **`.env.production.example`:** `APP_NAME=Restano`, `APP_ENV=production`, `APP_DEBUG=false`, `CENTRAL_DOMAIN=restano.app`, `SESSION_SECURE_COOKIE=true`, `REDIS_PASSWORD`, `LOG_LEVEL=warning`, `MARKETING_LEGAL_REVIEW_PENDING=true`, `MAIL_*` yer tutucuları. Gerçek sır yazma.
- **robots:** canlı dışındaki ortamlarda `Disallow: /`. Statik dosya yerine bir route ile çözülebilir; `sitemap.xml` satırı canlıda kalsın.
- Testler: her hata kodu iki host tipinde render oluyor; menü host'unda oturum çerezi set edilmiyor.

**Notlar (2026-11-16, tamamlandı):**
- Bağlam `App\Support\ErrorPage` + `errors::*` view composer'ı (`AppServiceProvider`). Menü host'u = merkezi olmayan host **veya** `/m/*`, **`/q/*`** (QR yönlendirmesi de oturumsuz olduğu için eklendi). Hata sayfaları DB'ye dokunmaz.
- Kod görünümleri tek `errors/page.blade.php`'ye bağlanır; varyantlar `errors/partials/{central,menu}`. Menü varyantı `menu-base.css` + mevcut `.menu-unavailable` stillerini kullanır. Ek olarak `4xx`/`5xx` genel yedek sayfaları var.
- Eşleşmeyen URL'lerde dil, `/en/...` önekinden alınır. Merkezi domain'de açık bir 403 nedeni (`abort(403, __('tenancy.suspended'))`) gösterilir; Laravel'in çevrilmemiş "This action is unauthorized." metni gösterilmez (askıdaki işletme testleri buna dayanıyor).
- `config/logging.php` zaten `LOG_DAILY_DAYS` (varsayılan 14) okuyordu, değişmedi. Yerel `.env`'e dokunulmadı (hâlâ `LOG_STACK=single`).
- robots: `public/robots.txt` silindi; `RobotsController` `bootstrap/app.php` içinde host'tan bağımsız ve `web` grubu olmadan kayıtlı (oturum yok). Canlıda `Disallow:` + `Sitemap:`, diğer ortamlarda `Disallow: /`. Faz 2 nginx'i statik `robots.txt` aramamalı (dosya yok, `try_files` PHP'ye düşer).
- Faz 3 için: Laravel 12'de `MAIL_SCHEME` Symfony DSN şemasıdır (`smtp` / `smtps`); `tls` geçerli değil. 587 + STARTTLS için boş bırakılır (`.env.production.example` böyle).

## Faz 2: Canlı ortam altyapısı (M)

*`docker-compose*.yml` ve `phpdocker/*` değişiklikleri onay gerektirir (CLAUDE.md §9). Plan onaylandı; yine de değişiklikleri özetleyip başlamadan önce teyit al.*

Bulgular: geliştirme Compose'unda Postgres `37004` portundan dışarı açık; `phpdocker/nginx/nginx.conf` içinde `server_name` yok ve `client_max_body_size 108M`.

- **`docker-compose.prod.yml`:** Postgres ve Redis portları dışarı açılmaz; Redis `requirepass` ile; `restart: unless-stopped`; healthcheck'ler; `queue` ve `scheduler` servisleri `www-data` ile (gelişmedekiyle aynı mantık); volume adları korunur. `down -v` asla kullanılmaz.
- **Canlı PHP image'ı** (ayrı bir Dockerfile veya multi-stage): opcache açık (`validate_timestamps=0`), `composer install --no-dev --optimize-autoloader`, derlenmiş asset'ler (`public/build`) image'ın içinde, canlı `php-ini` (`display_errors=Off`, `expose_php=Off`, yükleme limitleri).
- **nginx (canlı):** `server_name restano.app *.restano.app`; `client_max_body_size` en büyük `MediaType::maxKilobytes()` değerine göre küçük bir pay bırakılarak; gzip; `build/` ve `fonts/` için `Cache-Control: public, max-age=31536000, immutable`; `storage/` altında PHP çalıştırma yasak; `server_tokens off`. SSL ve proxy başlıkları **kapsam dışı** (proxy/HTTPS işi), sadece yerini yorumla belirt.
- **`scripts/deploy.sh`:** `php artisan down` → `git pull` → `composer install --no-dev` → node container'ında `npm ci && npm run build` → `migrate --force` → `optimize` + `filament:optimize` + `view:cache` → `queue:restart` (ve queue container'ını yeniden başlat) → `up`. Hata olursa durur (`set -euo pipefail`).
- **`README.md`:** Laravel'in varsayılan metnini, projenin kurulum (geliştirme) ve deploy (canlı) runbook'u ile değiştir. CLAUDE.md §9'daki komutlara referans ver, tekrarlama.
- Bitiş ölçütü: canlı Compose yerelde ayağa kalkıyor, `/up` 200 dönüyor, kuyruk ve scheduler çalışıyor, test süiti yeşil.

**Notlar (2026-11-16, tamamlandı):**
- Karar: kod image'a gömülmez, sunucudaki checkout'tan **bind mount** edilir. "Asset'ler image içinde" maddesi bunun yerine şöyle uygulandı: `deploy.sh`, `public/build`'i node container'ında checkout içine derler. Image (`phpdocker/php-fpm/Dockerfile.prod` + `production/{php.ini,fpm-pool.conf}`) sadece çalışma ortamını içerir. `validate_timestamps=0` olduğu için deploy sonunda `php-fpm` yeniden başlatılır.
- `docker-compose.prod.yml`: `name: restano` (volume'lar `restano_postgres-data`, yeni `restano_redis-data`). Kimlik bilgileri Laravel'in okuduğu `.env`'den gelir (`DB_*`, `REDIS_PASSWORD`, `CENTRAL_DOMAIN`, opsiyonel `HTTP_PORT`). Redis AOF açık, eviction yok. Log rotasyonu 10 MB × 5. `mailpit` yok.
- nginx şablonu (`nginx.prod.conf.template`, resmi image'ın envsubst'ü ile): bilinmeyen host'lara 444, sadece `index.php` çalışır, `storage/` altında `.php` 404, dotfile 403, `client_max_body_size 8M` (PHP: 6M dosya / 8M post). **php-fpm, Docker DNS'i ile istek anında çözülür** (`resolver 127.0.0.11`). Aksi halde php-fpm yeniden başlayıp yeni IP alınca nginx 502 veriyordu.
- `storage:link`, `www-data` olarak `public/`'e yazamadığı için root ile ve sadece symlink yoksa çalışır. Laravel 12'de `optimize` zaten `view:cache` içeriyor; ayrıca çağrılmadı.
- Yerel doğrulama: ayrı bir klon + `COMPOSE_PROJECT_NAME=restano-prodtest` ile 3 deploy (ilk deploy, tekrar, bakım modu sırasında 503). Kontrol edilenler: `/up` 200, kuyruk işi (`ContactMessageMail`) işlendi, scheduler çalışıyor, Redis şifresiz bağlantıyı reddediyor, Postgres ve Redis portları dışarı açık değil, gzip ve immutable cache başlıkları var.
- Proxy/HTTPS işi için: TLS olmadan `request()->getScheme()` http döndüğü için robots'taki `Sitemap:` satırı `http://` çıkıyor. `trustProxies` kurulunca düzelir.
- Faz 4 için: `PlanSeeder` `updateOrCreate` kullanıyor; canlıda `db:seed` sadece ilk deploy'da çalıştırılmalı (README'de uyarı var). Uploadlar `storage/app/public` (checkout içinde), DB volume'u `restano_postgres-data`.

## Faz 3: E-posta (S)

- `.env.production.example`: SMTP (`MAIL_MAILER=smtp`, host/port/kullanıcı/şifre yer tutucu, `MAIL_SCHEME` boş veya `smtps`; bkz. Faz 1 notu), `MAIL_FROM_ADDRESS=no-reply@restano.app`, `MAIL_FROM_NAME=Restano`.
- Tüm maillerin (doğrulama, şifre sıfırlama, `WelcomeMail`, `ContactMessageMail`, ileride davet maili) kuyruktan gittiğini doğrula. Fortify bildirimleri kuyrukta değilse `ShouldQueue` ekle.
- Doğrulama ve şifre sıfırlama maillerinin tr/en metinleri ve markası kontrol edilir.
- **Kullanıcıya verilecek kontrol listesi:** sağlayıcı seçimi, SPF / DKIM / DMARC kayıtları, gönderen alan adının doğrulanması, test gönderimi (mail-tester).
- Sağlayıcının KVKK tedarikçi listesine (Faz 10) eklenmesi için not düş.

**Notlar (2026-11-16, tamamlandı):**
- `WelcomeMail` ve `ContactMessageMail` zaten kuyruktaydı. Doğrulama ve şifre sıfırlama bildirimleri senkron gidiyordu; artık `App\Notifications\Auth\{VerifyEmailNotification,ResetPasswordNotification}` (`ShouldQueue`, `tries=3`) kullanılıyor, `User::sendEmailVerificationNotification()` / `sendPasswordResetNotification()` override'ları ile. Fortify ve route'lar değişmedi.
- Dil: kuyruğa atılırken isteğin dili (`app()->getLocale()`) bildirime yazılır; worker'ın kendi istek dili yok. Auth sayfaları bugün sadece `tr` (`APP_LOCALE`), yani bu mailler pratikte Türkçe gidiyor; `en` render'ı test ediliyor.
- Marka: Laravel'in varsayılan markdown şablonu yerine `WelcomeMail` ile aynı görünüm (`mail/auth-action` + `-text`), metinler `lang/{tr,en}/auth.php` → `mail`. Kullanıcı adı escape'li.
- Linkler worker'da `APP_URL`'den üretilir: canlıda `APP_URL=https://restano.app` olmalı, yoksa imzalı doğrulama linki ve sıfırlama linki yanlış şema/host ile çıkar (proxy/HTTPS işiyle birlikte kontrol et).
- `AccountMailTest`: `app/Mail` ve `app/Notifications` altındaki her sınıfın `ShouldQueue` olduğunu kontrol eder. Faz 7'deki davet maili de buna takılır.
- `.env.production.example`: `MAIL_FROM_NAME="Restano"` (açık değer), `MAIL_SCHEME` açıklaması. Yerel `.env`'e dokunulmadı (gönderen hâlâ `Laravel <hello@example.com>`).
- Yerel doğrulama: gerçek kuyruk → Mailpit, iki mail doğru dil ve konu ile ulaştı.
- **Faz 10 için:** seçilen mail sağlayıcısı KVKK tedarikçi listesine (ve yurt dışındaysa yurt dışı aktarım bölümüne) eklenmeli.

**Kullanıcı kontrol listesi (mail):**
1. SMTP sağlayıcısı seç (ör. Amazon SES, Postmark, Brevo, Mailgun, Resend; AB/TR veri merkezi seçeneği KVKK açısından artı).
2. Sağlayıcıda `restano.app` gönderen alan adını doğrula.
3. DNS: **SPF** (`v=spf1 include:<sağlayıcı> -all`; tek SPF kaydı olmalı), **DKIM** (sağlayıcının verdiği CNAME/TXT), **DMARC** (`_dmarc.restano.app` → başta `v=DMARC1; p=none; rua=mailto:<adres>`, birkaç hafta sonra `quarantine`). Sağlayıcı istiyorsa özel Return-Path / MAIL FROM alt alan adı.
4. Sunucudaki `.env`'e `MAIL_HOST`, `MAIL_PORT` (587 → `MAIL_SCHEME` boş; 465 → `smtps`), `MAIL_USERNAME`, `MAIL_PASSWORD` yaz; `php artisan config:cache` (deploy zaten yapar) ve `docker compose -f docker-compose.prod.yml restart queue`.
5. Test: kayıt ol veya şifre sıfırla; mail-tester.com'a bir mail gönderip 9/10+ puan al; Gmail ve Outlook'ta spam'e düşmediğini ve linklerin `https://restano.app` ile başladığını kontrol et.
6. `no-reply@` yerine yanıtların gideceği bir adres istenirse site ayarlarındaki platform e-postası kullanılabilir (şu an Reply-To yok).

## Faz 4: Yedekleme (M)

- `docker-compose.prod.yml` içinde ayrı bir **`backup`** servisi (`postgres:15-alpine` + crond). PHP image'ına `postgresql-client` eklenmez.
- Günlük `pg_dump -Fc` ve `storage/app/public/tenants` (media diski) tar arşivi.
- Saklama: 7 günlük, 4 haftalık, 3 aylık. Yerel hedef `storage/app/private/backups/` (git-ignored).
- **Sorulacak:** dış hedef (Hetzner Storage Box / S3 benzeri bucket / başka bir sunucu) ve gerekirse araç (`rclone` gibi).
- README'ye **geri yükleme prosedürü**: `pg_restore` ile ayrı bir veritabanına geri yükleme denenmeli ve sonucu yazılmalı.
- Yedek başarısız olursa bildirim (Faz 5'teki kanal).

## Faz 5: İzleme (S)

- **Sentry:** `sentry/sentry-laravel` paketini kurmadan önce onay al (CLAUDE.md §2). `send_default_pii=false`; istek gövdesi, form verisi ve kullanıcı e-postası gönderilmez; `SENTRY_LARAVEL_DSN` sadece env'de; `traces_sample_rate` düşük veya 0.
- **Kontrol komutu** (ör. `ops:health-check`, scheduler'da 15 dakikada bir): başarısız kuyruk işi sayısı, disk doluluğu (%85+), son yedeğin yaşı. Eşik aşılırsa platform e-postasına (site settings) mail; aynı uyarıyı saatte bir defadan sık gönderme.
- **Kullanıcının adımı:** `/up` için dışarıdan uptime izleyicisi (UptimeRobot / Better Stack).
- Testler: komutun eşikleri ve bildirim tekrar kısıtı.

**Notlar (2026-11-16, tamamlandı):**
- `sentry/sentry-laravel` **^4.30** kuruldu (kullanıcı onayıyla). `config/sentry.php` açık bir `data_collection` kullanıyor; bu seçenek verildiğinde SDK'nın kendi varsayılanları (kullanıcı bilgisi, istek gövdeleri, stack frame değişkenleri) gevşek, o yüzden her alan elle kapatıldı. Kullanıcı/IP, çerez, gövde, query string, SQL binding ve job payload gitmiyor; header'lar allow-list'te. Tracing, profiling, logs ve metrics kapalı. `send_default_pii` env ile açılamıyor.
- `SentryEventScrubber` (`before_send`): `/reset-password/…`, `/email/verify/…`, `/invitations/…` yollarındaki token'ları `[redacted]` yapıyor ve event'e `tenant_id` tag'i ekliyor. **Faz 7:** davet route'u `/invitations/{token}` dışında bir yol kullanırsa scrubber'a ve `SentryPrivacyTest`'e eklenmeli.
- `bootstrap/app.php`: `Sentry::handles($exceptions)` + `dontReport(MenuUnavailableException)`. Bu exception her offline menü açılışında loga düşüyordu (yerel logda 252 kayıt) ve Sentry'yi dolduracaktı; render davranışı değişmedi.
- `ops:health-check` (`config/ops.php`, 15 dakikada bir, `withoutOverlapping` + `onOneServer`): başarısız işler (son uyarıdan bu yana `id` ile; her hata bir kez bildirilir, son görülen id Redis'te), `storage/` diskinin doluluğu, en yeni yedek dosyasının yaşı. Uyarılar senkron (`sendNow`) gidiyor, çünkü kontrol edilen şeylerden biri kuyruğun kendisi. `OpsAlertMail` mimari test gereği yine `ShouldQueue`. Her kontrol için `Cache::add` ile cooldown var; mail gönderilemezse cooldown geri alınıyor ve bir sonraki çalışmada tekrar deneniyor. Alıcı `OPS_ALERT_EMAIL`, boşsa site ayarlarındaki `contact_email`.
- **Faz 4 için:** yedek kontrolü yazıldı ama kapalı (`OPS_BACKUP_CHECK=false`). Varsayılan yol `storage/app/private/backups` (alt klasörler dahil en yeni dosyaya bakar), eşik 26 saat. Yedekler çalışmaya başlayınca `.env`'de `OPS_BACKUP_CHECK=true` yapılmalı; yol farklıysa `OPS_BACKUP_PATH`. Yedek servisi hata verirse ayrı bir bildirim şart değil: yedek eskiyince bu kontrol yakalar.
- **Faz 10 için:** Sentry tedarikçi listesine (ve yurt dışı aktarım bölümüne; Sentry'de AB veri bölgesi seçilebilir) eklenmeli.
- `phpunit.xml`: `SENTRY_LARAVEL_DSN`, `OPS_ALERT_EMAIL` boş ve `OPS_BACKUP_CHECK=false`, böylece yerel `.env` testleri etkilemiyor. README'ye "Monitoring" bölümü, `.claude/rules/security.md`'ye "Monitoring" kuralları, overview teknoloji tablosuna ve CLAUDE.md §2'ye Sentry eklendi.
- Yerel doğrulama: `config:cache` ile çalışıyor; disk eşiği 0 yapılınca gerçek uyarı Mailpit'e ulaştı, ikinci çalışmada cooldown devreye girdi.

**Kullanıcının adımları:**
1. Sentry hesabı ve Laravel projesi (mümkünse AB veri bölgesi); DSN'i sunucudaki `.env`'e `SENTRY_LARAVEL_DSN` olarak yaz, `scripts/deploy.sh` çalıştır. Sentry'de yeni issue için e-posta alarm kuralı kur.
2. `.env`'e `OPS_ALERT_EMAIL` (yoksa site ayarlarındaki platform e-postası kullanılır).
3. Dışarıdan uptime izleyicisi: `https://restano.app/up` (1–5 dk) ve bir public menü URL'si.

## Faz 6: Temalar vitrini için canlı seed (S)

Bulgular: `/temalar` (`ThemeShowcaseController`, `config/marketing.php` → `showcase`) `kahve-konagi`, `noon`, `maison-ardoise`, `kiln-coffee`, `petal-bakehouse` işletmelerine bağlı. Bunları sadece `DemoSeeder` oluşturuyor; o da local'de çalışıyor (`DatabaseSeeder`) ve şifresi `password` olan kullanıcılar açıyor. Vitrin işletmeleri `demo` planında **365 günlük trial** ile başlıyor (`DemoSeeder` satır ~79 ve ~257), yani canlıda bir yıl sonra kapanırlar.

- `DemoSeeder`'daki vitrin bölümünü **`ShowcaseSeeder`**'a taşı: giriş yapan kullanıcısı (üyeliği) olmayan 5 işletme, idempotent, canlıda çalıştırılabilir. `DemoSeeder` local demo hesaplarını oluşturmaya devam eder ve vitrin için `ShowcaseSeeder`'ı çağırır.
- Vitrin işletmeleri için süresiz bir abonelik: `SubscriptionService` üzerinden ve `subscription_events` kaydıyla (ör. `active`, `current_period_ends_at` çok ileri veya null; lifecycle komutunun bunu süresi dolmuş saymadığını test et). Bu ödeme akışı değildir.
- Üyesi olmayan işletmenin hiçbir yerde hata vermediğini doğrula (admin listeleri, lifecycle job'ı, dashboard sorguları).
- README'ye canlıda `php artisan db:seed --class=ShowcaseSeeder --force` adımını ekle.

**Notlar (2026-11-16, tamamlandı):**
- `ShowcaseSeeder` 5 vitrin işletmesinin tamamını (Kahve Konağı dahil, iki şube + kahvaltı menüsüyle) ve `demo` planını oluşturur; kullanıcı ve üyelik yok. Ortak builder'lar `database/seeders/Concerns/SeedsMenuContent` trait'inde. `DemoSeeder` local'de platform kullanıcılarını, `ShowcaseSeeder`'ı, Kahve Konağı ekibini (sahip/müdür/personel, şifre `password`) ve askıdaki `eski-firin`'i oluşturur.
- Abonelik: yeni `SubscriptionService::startComplimentary()` (yeni satır `active`, fiyat ve sağlayıcı yok, `current_period_ends_at = 2099-12-31 23:59:59 UTC`, event `complimentary_started`, `reason: showcase`). `null` bitiş tarihi kullanılmadı, çünkü `accessUntil()` null dönünce menü kapanıyor. Lifecycle bitiş tarihi gelmediği için dokunmuyor (2090'a zaman atlatılarak test edildi). subscriptions.md tablosuna eklendi.
- İdempotent: var olan işletme (silinmiş dahil) atlanır; var olan `demo` planı **ezilmez** (admin'den düzenlenen limitler korunur; `premium_themes` kapatılırsa vitrin temaları kaydedilemez ve seed düşer).
- Kahve Konağı'nın sabit QR kodları (`KNKM0DA001` vb.) kaldırıldı; artık rastgele üretiliyor (hiçbir test bunlara bağlı değildi). Mevcut yerel veritabanındaki işletmeler değişmedi (hâlâ 365 günlük trial); temiz bir `migrate:fresh --seed` ile complimentary aboneliğe geçerler.
- Üyesiz işletme kontrolü: `/admin`, `/admin/plans`, `ops:health-check`, lifecycle komutu ve job'ı hatasız. `/app` dashboard'u üyelik gerektirdiği için bu işletmelere zaten erişilemez. **Faz 8:** `TenantResource` listesi ve detayında üyesi olmayan (vitrin) işletmeler görünecek; sahip sütunu boş olabilir. Askıya alma/slug değiştirme vitrin işletmelerini de etkiler (`config/marketing.php` → `showcase` slug'larına bağlı).
- Testler: `tests/Feature/Marketing/ShowcaseSeederTest.php`; `ThemeShowcaseTest` artık `ShowcaseSeeder` ile seed ediyor.

## Faz 9: İki adımlı doğrulama (S)

- Filament'in yerleşik MFA'sı: TOTP (`pragmarx/google2fa` Filament bağımlılığı olarak zaten kurulu, yeni paket yok) ve kurtarma kodları.
- `AdminPanelProvider`: MFA **zorunlu** (platform rolü olan herkes ilk girişte kurmak zorunda). `AppPanelProvider`: isteğe bağlı.
- Gerekli kolonlar için migration (Filament'in beklediği alanlar, şifreli cast'ler).
- Testler: MFA kurmamış platform kullanıcısı admin'e giremez / kuruluma yönlenir; `/app` MFA'sız çalışır.

**Notlar (2026-11-16, tamamlandı):**
- Filament 5.11 yerleşik `AppAuthentication` (TOTP + kurtarma kodları), yeni paket yok (`pragmarx/google2fa-qrcode`, `bacon/bacon-qr-code` zaten kurulu; QR SVG data URI olarak üretiliyor). Kolonlar: `users.app_authentication_secret` (`encrypted`) ve `app_authentication_recovery_codes` (`encrypted:array`; Filament kodları ayrıca hash'liyor). İkisi de `$hidden` ve fillable değil.
- **İki panelin sağlayıcıları aynı olmak zorunda** (`App\Filament\Shared\Auth\MultiFactor::providers()`): paneller oturumu paylaşıyor ve Filament login'i sadece o panelin sağlayıcılarına göre kod soruyor. `/app`'te MFA tanımlı olmasaydı MFA'sı açık bir admin `/app/login`'den kodsuz girip `/admin`'e geçebilirdi. Bu durum test ediliyor.
- Zorunluluk Filament'in route middleware'i ile (`EnsureMultiFactorAuthenticationIsEnabled`): MFA'sız platform kullanıcısı her admin sayfasından `/admin/multi-factor-authentication/set-up`'a gidiyor. Bu middleware "kullanıcının MFA'sı açık mı" diye bakıyor, "bu oturum kodu girdi mi" diye değil. Güvence, tüm giriş yollarının kod sorması (yukarıdaki madde). Fortify'ın giriş yaptıran tek yolu public kayıt (platform rolü olamaz).
- Profil sayfası (`/admin/profile`, `/app/profile`, `App\Filament\Shared\Auth\EditProfile`) **sadece MFA yönetimi** içeriyor: form boş, `save()` 403. Ad/e-posta/şifre değişikliği bilinçli olarak yok (e-posta değişikliği yeniden doğrulama gerektirir, şifre için sıfırlama akışı var). Admin MFA'yı kapatabilir (kod ister); kapatınca tekrar kuruluma yönlenir.
- Kilitlenme: `php artisan platform:reset-two-factor {email}` (README'de).
- Factory: `superAdmin()` / `platformStaff()` artık MFA'lı geliyor (sabit test secret'ı `UserFactory::TWO_FACTOR_SECRET`, kurtarma kodları `recovery-code-1..8`); `withoutTwoFactor()` / `withTwoFactor()` state'leri var. `DemoSeeder` platform kullanıcıları MFA'sız, yerelde ilk `/admin` girişinde kurulum istenir.
- **Faz 8 için:** impersonation sırasında MFA değişikliği engellenmeli: `EditProfile` (MFA action'ları: kurulum, kapatma, kod yenileme) ve `platform:reset-two-factor` dışında MFA yazan yer yok. Profil sayfasının erişimi (`canAccess()` veya mount) impersonation'da kapatılabilir. `UserResource`'ta "MFA sıfırla" istenirse audit'e yazılmalı.
- Testler: `tests/Feature/Panels/TwoFactorAuthenticationTest.php` (16 test).

## Faz 7: Ekip yönetimi `/app` (L)

Bağlam: üyelikler `tenant_user` tablosunda (`role`, `is_active`, `UNIQUE (tenant_id, id)`); şube erişimi `location_user` üzerinden (composite FK). `TenantRole`: `owner`, `manager`, `branch_staff`. Yetki kuralları için CLAUDE.md §3.16 ve `TenantMembership::canAccessLocation()`.

- **Veritabanı:**
  - `tenant_invitations`: `tenant_id`, `email`, `role`, `token_hash` (ham token saklanmaz), `invited_by_membership_id` (composite FK), `expires_at`, `accepted_at`, `revoked_at`, `UNIQUE (tenant_id, id)`. Aynı işletmede aynı e-postaya tek açık davet.
  - `tenant_invitation_location`: `(tenant_id, tenant_invitation_id)` ve `(tenant_id, location_id)` composite FK'leri.
  - `PlanFeature::MaxMembers` (`max_members`): admin plan formuna ve `PlanSeeder`'a eklenir. Kullanım = aktif üyelikler + açık davetler.
- **Servisler:** `TeamInvitationService` (davet, tekrar gönderme, iptal, kabul; `FeatureGate::ensureCanCreate()` davet anında) ve `TeamService` (rol değiştirme, şube atama, pasifleştirme, çıkarma). Transaction ile. `role` ve membership durumu asla mass-assignable değil.
- **Panel (sadece owner, policy ile):** "Ekip" sayfası/resource'u, üye listesi, rol ve şube düzenleme, davet listesi. Navigation gizlemek yetki değildir; `canAccess()` ve her action'da yeniden kontrol.
- **Daveti kabul etme** (merkezi domain, Blade, tr/en, `throttle`):
  - `GET/POST /invitations/{token}`.
  - Hesabı olan kişi giriş yapar; e-postası davetle eşleşirse üyelik oluşur. Eşleşmiyorsa açık bir hata, üyelik yok.
  - Hesabı olmayan kişi ad ve şifre belirler (Fortify şifre kuralları). E-postası doğrulanmış sayılır.
  - Token tek kullanımlık, süreli (ör. 7 gün), sabit zamanlı karşılaştırma. Geçersiz token işletmeyi ifşa etmez.
  - Public kayıt ile karışmaz: `TenantRegistrationService` değişmez.
- **Davet maili:** kuyrukta, tr/en, işletme adı escape'li.
- **Kurallar:** tek owner; owner kendini çıkaramaz veya rolünü düşüremez; `manager` ve `branch_staff` için en az bir şube zorunlu; pasifleştirilen üyenin erişimi anında kesilir. Owner devri ve müdürün personel davet etmesi **ilk sürümde yok**.
- **Testler:** yetki matrisi (owner / manager / branch_staff), başka işletmenin şubesine atama (DB reddeder), token güvenliği (süresi geçmiş, iptal edilmiş, tekrar kullanılmış, yanlış e-posta), `max_members` limiti, rate limit, askıdaki işletme.

**Notlar (2026-11-16, tamamlandı):**
- **Karar (kullanıcı):** oturumu kapalı, hesabı olan kişi davet sayfasında **mevcut şifresiyle** kabul eder. Oturum açılmaz, sonra `/app/login`'den girer (MFA orada sorulur). Sebep: Filament login'i aktif üyeliği olmayan hesabı içeri almıyor, yani çıkarılıp yeniden davet edilen biri başka türlü kabul edemezdi. Oturumu açık ve e-postası eşleşen kişi tek tıkla kabul eder; eşleşmiyorsa hata alır ve üyelik oluşmaz. Yeni hesap ad + şifre belirler, e-postası doğrulanmış sayılır ve kayıttaki gibi oturum açılır. CLAUDE.md §3.16 ve security.md buna göre güncellendi.
- Tablolar: `tenant_invitations` (ek olarak `locale` ve `sent_at`; açık davet için partial unique index; davet eden için composite FK `ON DELETE SET NULL (invited_by_membership_id)`, PG15) ve `tenant_invitation_location`. Model `TenantInvitation` (tenant-scoped, fillable boş, `token_hash` hidden).
- Token: 32 byte, URL-safe base64 (43 karakter), sadece SHA-256 hash'i saklanıyor; 7 gün (`config('tenancy.team.invitation_days')`). Tek kullanım, kabulde satır kilidiyle tekrar kontrol ediliyor. Tekrar gönderme yeni token üretir (eski link ölür; 60 sn bekleme). Süresi dolmuş açık davet, aynı adrese yeni davette iptal edilip değiştirilir.
- Yeni dokümante bypass: `App\Services\Team\InvitationTokenResolver` (token işletmeyi belirler; tenancy.md + `TenantScopeBypassTest`). Geçersiz, kullanılmış, iptal edilmiş, süresi dolmuş token ve askıdaki işletme aynı genel 404 sayfasını alır.
- Servisler: `TeamInvitationService`, `TeamService`, ortak şube doğrulaması `BranchSelection`. `max_members` = aktif üyelikler (owner dahil) + bekleyen davetler (tek sorgu); davette ve **üyeyi tekrar aktifleştirmede** kontrol ediliyor. Çıkarma üyeliği ve şube atamalarını siler, hesap kalır.
- Panel: `TeamMemberResource` (`/app/{tenant}/team`) ve `TeamInvitationResource` (`/app/{tenant}/team-invitations`), işlemler modal. Policy'ler `TenantMembershipPolicy`, `TenantInvitationPolicy`. **Dikkat:** Filament, resource modeline global tenant scope ve `creating` hook'u ekliyor; `TenantMembership` için bu, başka işletmedeki üyelik kontrollerini bozuyordu (`canAccessTenant`, switcher). Bu yüzden `TeamMemberResource::$isScopedToTenant = false`; sorgu `TenantContext`'e açıkça bağlı. **Faz 8:** admin'de `TenantMembership` (veya `User`) için resource yazarken aynı tuzak var.
- `TenantMembership::locations()` / `TenantInvitation::locations()`: eager loading'de `withPivotValue` boş modelde patladığı için pivot değeri sadece `tenant_id` doluyken ekleniyor.
- Route'lar: `GET /invitations/accepted`, `GET/POST /invitations/{token}` (`throttle:invitations`: görüntüleme 30/dk/IP; kabul 5/dk/IP + 10/dk/token). Sayfalar `no-store`, `noindex`, `no-referrer`. Dil davetin `locale`'i (davet edenin panel dili). Sentry scrubber `/invitations/…` yolunu zaten maskeliyordu.
- `PlanFeature::MaxMembers`: admin plan formu ve fiyat sayfası enum'dan otomatik okuyor. `PlanSeeder` starter = 3, vitrin `demo` planı = 10. **Mevcut planlarda `max_members` satırı yoksa limit 0 olur ve davet gönderilemez:** canlıda Faz 11'de planlara değer girilmeli; yerelde `php artisan db:seed --class=PlanSeeder` starter'a ekler (demo planı seeder tarafından ezilmez, admin'den girilmeli).
- `DashboardTest` sorgu tavanı 12 → 13 (`PlanUsageWidget` sayılan her özellik için bir sorgu çalıştırıyor; şube/menü sayısıyla büyümüyor).
- Testler: `tests/Feature/Panels/TeamManagementTest.php` (15), `tests/Feature/Auth/TeamInvitationAcceptTest.php` (11). Süit: 698 test yeşil.
- **Faz 10 için:** davet kabul ekranındaki yeni hesap formuna kullanım koşulları onayı eklenmeli (`resources/views/invitations/show.blade.php`, `AcceptInvitationRequest` register modu).

## Faz 8: Kapsamlı admin + impersonation (L)

Önkoşul: Faz 0'da tenancy.md'deki izinli scope bypass alanı tanımlanmış olmalı.

- **`admin_audit_events`:** `actor_user_id`, `action`, `subject_type/id`, `tenant_id` (nullable), `meta` (jsonb), `ip`, `created_at`. Sadece ekleme, düzenleme yok. Admin'de salt okunur liste.
- **`TenantResource`:**
  - Liste: ad, slug, durum, plan, abonelik durumu, oluşturulma tarihi; filtreler.
  - Detay: üyeler, şubeler, abonelik ve `subscription_events`. Abonelik **salt okunur** (elle aktivasyon ödeme işinin parçası).
  - İşlemler: askıya alma / geri açma (`TenantStatus`, resolution cache temizliği), slug değiştirme (`TenantSlugService`: format, `tenancy.reserved_slugs`, benzersizlik, cache temizliği). Kalıcı silme yok; soft delete sadece super_admin ve onaylı.
  - **Sorulacak:** slug değişince eski subdomain/path yeni adrese 301 ile yönlensin mi? (QR kodlar `/q/{code}` sayesinde etkilenmez.)
- **`UserResource`:** liste ve detay (üyelikler), doğrulama mailini tekrar gönderme, platform kullanıcısı oluşturma. `platform_role` sadece super_admin atar. Kullanıcı silme yok; gerekirse pasifleştirme.
- **Yetki:** her resource için policy. `super_admin` her şey; `platform_staff` okuma ve sınırlı işlem (hangi işlemler: policy'de netleştir, dokümana yaz). İş yetkileri için global `Gate::before` yok.
- **Impersonation (sınırlı):**
  - Sadece super_admin, sadece platform rolü olmayan işletme kullanıcılarına.
  - Oturumda impersonator kimliği saklanır; 60 dakika sonra otomatik biter.
  - `/app` panelinde "X adına görüntülüyorsun / Çık" bandı (render hook).
  - Bu oturum sırasında şifre/e-posta/MFA değiştirme, `/admin`'e geçme ve iç içe impersonation engellenir.
  - Başlangıç ve bitiş `admin_audit_events`'e yazılır. Çıkışta admin oturumuna güvenli dönüş (session regenerate).
- **Testler:** policy matrisi, platform_staff ile super_admin farkları, impersonation sınırları ve süresi, audit kayıtları, slug değişikliği sonrası cache ve menü URL'leri.

**Notlar (2026-11-16, tamamlandı):**
- **Kararlar (kullanıcı):** (1) Slug değişince eski slug `tenant_slug_redirects`'e yazılır, eski menü URL'leri (path ve subdomain, aynı sayfa + query string) **301** ile yeni slug'a gider ve eski slug başka işletmeye verilmez (kayıt formu da kontrol ediyor). (2) `platform_staff` sadece okur ve doğrulama mailini tekrar gönderir; diğer her işlem super_admin. (3) Ek olarak: silinen işletmeyi **geri alma** ve **kullanıcı pasifleştirme** (`users.deactivated_at`). MFA sıfırlama admin'e eklenmedi (artisan komutu yeterli).
- Yetki tablosu overview.md § Platform management'ta. `TenantPolicy`'ye admin yetenekleri eklendi (`manageSettings` ayrı kaldı), yeni `UserPolicy`, `AdminAuditEventPolicy`. Servisler `app/Services/Admin/` (`TenantAdminService`, `UserAdminService`, `Impersonation`, `AdminAudit`, `TenantInspector`) ve `app/Services/Tenancy/TenantSlugService`; hepsi yetkiyi tekrar kontrol eder ve audit'e yazar.
- `admin_audit_events`: model güncelleme/silmeyi reddeder, ayrıca DB trigger'ı `UPDATE`/`DELETE`'i reddeder. Trigger fonksiyonu `CREATE OR REPLACE` ile oluşturuluyor, çünkü `migrate:fresh` tabloları siler ama PG fonksiyonlarını silmez. `meta` jsonb olduğu için anahtar sırası korunmaz (testlerde `assertEquals`).
- Tenant listesi abonelik/sahip bilgisini düz alt sorgularla alıyor; detay sayfasındaki şube ve abonelik geçmişi relation manager'ları `TenantInspector`'daki scope'suz `HasMany` ile. **Tuzak:** scope'lu modele `withCount` (ör. `locations`) fail-closed scope'a takılıp patlıyor; pivot sayılmalı.
- Impersonation state'i sadece sunucu oturumunda; `Auth::login()` şifre hash'ini de güncellediği için `AuthenticateSession` sorun çıkarmıyor. `EnforceImpersonation` ve `LogoutDeactivatedUsers` hem `web` grubunda hem iki panelin middleware listesinde (paneller `web` grubunu kullanmıyor) ve **priority listesinde `AuthenticatesRequests`'ten önce**; aksi halde Laravel önceliklendirmesi Filament'in `Authenticate`'ini önce çalıştırıp pasif kullanıcıya sadece 403 veriyordu. Oturum durumu `session()->driver()->isStarted()` ile okunuyor (menü route'ları, komutlar, job'lar → state yok).
- Impersonation sırasında engellenenler: `/admin`, davet kabulü (POST), profil sayfası, model seviyesinde MFA yazımı; şifre sıfırlama zaten `guest`. Logout da bitiş olarak audit'e yazılır. 60 dk sonra ilk istekte admin geri yüklenir. Band sadece `/app`'te; `/account` sayfasında yok.
- Livewire testlerinde dikkat: aynı testte önce tenant subdomain'ine HTTP isteği atılırsa sonraki Livewire çağrıları o host'u kullanıyor ve merkezi domain'e bağlı update route'u bulunamıyor.
- Slug değişikliğinde `/app/{slug}` yönlendirilmez; vitrin işletmelerinin slug'ı değişirse `config/marketing.php` → `showcase` elle güncellenmeli.
- Testler: `tests/Feature/Admin/{TenantAdminTest (12), UserAdminTest (8), ImpersonationTest (8)}`. Süit: 726 test yeşil.

## Faz 10: Hukuk ve KVKK (S kod + kullanıcı)

Bulgular: `lang/tr/legal.php` (ve `en`) içinde `[şirket unvanı]`, `[adres]`, `[başvuru adresi]`, tedarikçi listesi yer tutucuları var; `MARKETING_LEGAL_REVIEW_PENDING` varsayılanı `true`.

- **Kod:**
  - Kayıt formunda kullanım koşulları onayı ve aydınlatma metnini okudum kutusu (zorunlu, Form Request ile).
  - `users.terms_accepted_at` ve `users.terms_version` (migration); sürüm config'de.
  - Davet kabul ekranında (Faz 7) aynı onay.
- **Kullanıcının adımları:** şirket bilgileri; tedarikçi listesi (hosting, mail sağlayıcısı, Sentry, yurt dışı aktarım); avukat incelemesi; ardından `MARKETING_LEGAL_REVIEW_PENDING=false`.
- **Runbook:** KVKK m.11 başvuru süreci (iletişim formu → kimlik doğrulama → süre: 30 gün); silme talebinde admin'den işletme soft-delete edilir, otomatik silme yok.

**Notlar (2026-11-16, kod yazıldı; Docker WSL'den erişilemediği için migration ve testler çalıştırılmadı):**
- **Karar (kullanıcı):** tek zorunlu kutu ("Kullanım koşullarını okudum ve kabul ediyorum") + altında aydınlatma metnine link veren bilgi notu. Aydınlatma bir onay kutusu değil (KVKK'da bilgilendirme, açık rıza ile karıştırılmamalı).
- Ortak bileşen `<x-ui.terms-consent>` (kayıt + davet register modu); linkler `MarketingUrl::to()` ile o anki dilde, yeni sekmede. Doğrulama `terms => accepted` (`CreateNewUser`, `AcceptInvitationRequest` sadece register modunda).
- Migration `2026_11_20_100000_add_terms_acceptance_to_users_table` (`terms_accepted_at`, `terms_version`, nullable). Sürüm `config('marketing.terms_version')` = `2026-11-15` (metnin tarihi). Yazan tek yer `User::recordTermsAcceptance()`; `TenantRegistrationService::register()` ve `TeamInvitationService::acceptAsNewUser()` çağırıyor (servisin tek çağıranı formu doğruluyor; e-posta doğrulama ile aynı desen).
- Mevcut, seed ile ya da artisan ile oluşturulan hesaplarda kayıt yok. Sürüm değişince tekrar onay isteme akışı **yok** (gerekirse lansman sonrası).
- Runbook README § "Legal and KVKK requests".
- Testler: `RegistrationTest` (onay zorunlu, sürüm kaydı, mass-assignment), `TeamInvitationAcceptTest` (yeni hesapta onay zorunlu). Mevcut kayıt payload'larına `terms` eklendi (`WelcomeMailTest`, `FeatureGateTest`, `TenantAdminTest`).
- **Kalan (kullanıcı):** şirket bilgileri, tedarikçi listesi (hosting, mail sağlayıcısı, Sentry; yurt dışı aktarım), saklama süreleri, sorumluluk/mahkeme, avukat incelemesi → `updated` tarihleri + `marketing.terms_version` güncellenir → `MARKETING_LEGAL_REVIEW_PENDING=false`.

## Faz 11: Plan ve fiyatlar (kullanıcı)

- Gerçek plan isimleri, TRY fiyatları ve limitler (`max_members` dahil) `/admin` → Planlar'dan girilir. `PlanSeeder` sadece geliştirme yer tutucusudur.
- KDV gösterimi ("dahil" mi "+KDV" mi) kararı verilince fiyat sayfası metni (`lang/*/marketing.php`) güncellenir.
- `custom_domain` ve `qr_analytics` için kod yok: fiyat sayfasında ve plan açıklamalarında vaat edilmediğini kontrol et.

**Notlar (2026-11-16, kod yazıldı; testler çalıştırılmadı):**
- **Karar (kullanıcı):** fiyatlar **KDV dahil**. Fiyat sayfasında ve ana sayfadaki fiyat önizlemesinde kartların altında "Fiyatlara KDV dahildir." (`marketing.pricing.vat_note`); admin plan formundaki fiyat yardım metni buna göre.
- `PlanFeature::isAvailable()`: `custom_domain`, `qr_analytics` false. Admin plan formunda toggle kapalı + açıklama, `PlanService` her durumda 0 yazar, `PricingPlan::includes()` false döner (DB'de 1 olsa bile kartta ve karşılaştırmada çıkmaz). Özellik yazılınca enum'da açılır.
- Seed'lenen SSS'lerde ve pazarlama metinlerinde özel alan adı / analitik vaadi yok (`FeaturesPageTest` özellikler sayfasını zaten koruyor). Plan **açıklamaları** admin'den serbest metin: girerken bu iki özellik yazılmamalı.
- Testler: `PricingPageTest` (vaat yok, KDV notu), `PlanAdminTest` (alan kapalı, servis 0 yazar).
- **Kalan (kullanıcı):** `/admin` → Planlar'dan gerçek isimler, KDV dahil TRY fiyatlar, limitler (**`max_members` dahil**; satır yoksa davet gönderilemez).

## Faz 12: Lansman sonrası

- CI: GitHub Actions (Postgres + Redis servisleri, `pint --test`, PHPUnit, `npm run build`).
- WebP dönüşümü ve küçük görseller (thumbnail) (`ImageNormalizer`).
- Panel CSP nonce'ları (`SecurityHeaders`).
- OG görselleri, Search Console kaydı.
