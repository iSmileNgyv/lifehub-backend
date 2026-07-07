# LifeHub — Dizayn və Qərarlar

> Bu sənəd LifeHub layihəsinin razılaşdırılmış memarlıq qərarlarını saxlayır.
> Məqsəd: söhbət kontekstini itirəndə buradan xatırlatmaq. Yeni qərar verdikcə bu fayl yenilənir.
> Qeyd: layihə **Procurement (qəlyan ERP)** bazasından scaffold edilib — generic auth/RBAC/i18n saxlanılıb, qəlyan domeni atılıb. Bu sənəd **yalnız LifeHub-a aiddir**.

---

## 1. Məqsəd və əhatə
**Şəxsi "həyat idarəetmə" tətbiqi** — bir istifadəçi (sahib) üçün fərqli sahələri bir yerdə izləyən modul-əsaslı sistem. Tam ERP deyil; hər modul müstəqil, addım-addım qurulur.

Mövcud modullar:
- **Trading** — kripto (USD) alqı-satqısı, FIFO maya, mənfəət, jurnal/post, dashboard.
- **Kassa** — nağd hesablar (cash desk) + kassa kitabçası (ledger).
- **Kataloq** — ölçü vahidləri, kateqoriyalar (ağac), məhsullar (barkod + vahid çevirmə).
- **Maşın** — nəqliyyat, probeq oxunuşları, ehtiyat hissə ömrü, yanacaq, xərclər.
- **Öyrənmə** — flashcard (Anki tipli) + SM-2 aralıqlı təkrar.

---

## 2. Texnologiya və infrastruktur
- **Backend:** Laravel 13 — **yalnız API**. PHP 8.3+ (Sail runtime).
- **Frontend:** ayrıca **Next.js** (App Router, TypeScript, Tailwind). `(dashboard)` route qrupu.
- **Auth:** Laravel Sanctum (**bearer token**, localStorage-da saxlanır). Cookie/session-əsaslı deyil → CORS problemi yox.
- **RBAC:** öz custom API-əsaslı sistem (spatie yox). Bax §4.
- **i18n:** JSON (az/en/ru); frontend-də bütün mətn `useLanguage().t()` ilə — **heç bir hardcoded mətn yox**.
- **Infra:** Laravel Sail + Docker. Konteynerlər: `lifehub`, `lifehub_pgsql`, `lifehub_redis`.
- **Portlar (digər layihələrlə toqquşmasın deyə ayrı):**
  | Servis | Host port |
  |---|---|
  | App (API) | **8090** |
  | Postgres | **5433** |
  | Redis | **6382** |
  | Vite | 5174 |
  | Frontend (`npm run dev`) | **3001** |
- **DB:** Postgres, `DB_DATABASE=lifehub`.

---

## 3. Baza memarlıq qərarları (bütün modullara ortaq)
- **Açar siyasəti:** demək olar heç bir cədvəldə auto-increment `id` yox — hər yerdə **`uid` = ULID** (`HasUlids`, vaxta görə sıralanır, ledger üçün faydalı). `roles` istisna: açarı **`code`**.
- **DB sxemləri (Postgres):**
  - `admin` — auth/sistem: users, roles, user_role, role_access, operations, languages, personal_access_tokens.
  - `app` — biznes/domen: trading, cash, kataloq, maşın, study.
  - framework cədvəlləri (`migrations, cache, jobs, sessions`) `public`-də.
  - Domen cədvəlləri açıq qualified adla (`app.items`, `admin.users`).
- **Enum-lar:** **Postgres native enum** + ona uyğun PHP enum. Migration-da `DO $$ ... CREATE TYPE` (idempotent). Nümunə: `app.card_state` = `new|learning|review`.
- **Çoxdilli mətn:** JSONB sütun + `App\Support\Translatable` (məs. kateqoriya/məhsul adı `{az,en,ru}`). Frontend `translateValue()` + `useContentLanguages().defaultCode`.
- **Fayl saxlama:** `stored_files` (uid, driver, path, mime, size). `App\Storage\StorageFactory` → `local` driver (`Storage::disk('public')`, `uploads/{uid}.ext`). `GET files/{file}` **public redirect** (`<img>` üçün). Yükləmə auth-lu.
- **Nömrə seriyaları:** `App\Support\TransactionNumber` + `number_series` — ledger/jurnal sənəd nömrələri və `transaction_number` (post-lar arası link) üçün.
- **Köməkçi siniflər (`app/Support`):** `FormulaEvaluator`, `PaceEstimator`, `Srs`, `TransactionNumber`, `Translatable`, `Usage`.

---

## 4. RBAC (operations + roles)
- **Operations kataloqu** — `OperationSeeder` `module_ACTION` kodları seed edir (məs. `TRADING_VIEW`, `VEHICLE_UPDATE`, `STUDY_CREATE`).
- Route-lar `middleware('access:OP')` ilə qorunur.
- **super_admin** bütün yoxlamaları bypass edir (`is_super_admin`).
- Frontend `useAuth().can(op)` ilə düyməni/menyunu gizlədir; super_admin hər şeyi görür.
- Cədvəllər: `roles` (açar=`code`), `user_role`, `role_access`, `operations`.

---

## 5. Modul: Trading (kripto)
Şəxsi USD (kripto) alqı-satqısının uçotu.
- **`trading_formulas`** — istifadəçinin təyin etdiyi **pilləli (tiered) formula** (məs. məbləğə görə fərqli kurs/komissiya). `FormulaEvaluator` (PHP, təhlükəsiz recursive-descent evaluator) hesablayır; frontend `lib/formula.ts` eyni məntiqin JS güzgüsüdür. Aktiv formula seçilir (`activate`), `compute` endpoint önizləmə verir.
- **`trading_ledger_entry`** — FIFO. `TradingEntryType`: `buy` (USD alışı → **təbəqə/giriş**), `sell` (USD satışı → **FIFO çıxış**, ən köhnə təbəqədən yeyir).
- **`trading_journals` + `trading_journal_entries`** — draft sənəd; sətirlər buy/sell. `post` ilə qətiləşir.
- **Post (`TradingPostingService`, atomik):** trading_ledger_entry (FIFO təbəqə/çıxış) + **kassa** hərəkəti (net nağd) + mənfəət hesablanması. Mənfəət = satış gəliri − FIFO maya.
- **Hesabatlar:** `balance` (cari USD qalığı + maya), `stats` (aylıq statistika) — dashboard-da göstərilir.
- **Ops:** `TRADING_VIEW/CREATE/UPDATE/DELETE/POST`, `TRADING_FORMULA_MANAGE`.
- **Frontend:** `/trading` (jurnallar), `/trading/formulas` (formulalar).

---

## 6. Modul: Kassa
- **`cash_desk`** — nağd hesab (uid, ad, `balance_lcy` keşlənmiş qalıq, status). `CashDeskStatus`.
- **`cash_ledger_entry`** — hərəkətlər; `CashOrderType` = `cash_in` / `cash_out`. Post zamanı `cash_desk.balance_lcy` yenilənir (in→+, out→−).
- Trading post-u kassaya net nağd hərəkəti yazır (`transaction_number` ilə linklənir).
- **Ops:** `CASHDESK_VIEW/CREATE/UPDATE/DELETE`. **Frontend:** `/cash-desks`.

---

## 7. Modul: Kataloq
Procurement-dəki kataloq sistemi (ölçü/kateqoriya/məhsul) buraya gətirilib.
- **`measurements`** — ölçü vahidləri (məs. LT, KM, ƏDƏD). `MEASURE_*`. Frontend `/measures`.
- **`item_categories`** — **ağac** (parent-child), sıralana bilən (`reorder`). Ad JSONB çoxdilli. `CATEGORY_*`. Frontend `/categories` (card görünüş, kateqoriya adı, yeniləmə ikonu, silmə confirmation).
- **`items`** — məhsullar; JSONB ad, kateqoriya, şəkil, status (`ItemStatus`). `PRODUCT_*`. Frontend `/products`.
- **`item_barcodes`** — məhsul barkodları (çox barkod).
- **`items_measurement` (`ItemMeasurement`)** — məhsul üzrə **vahid çevirmə** (məs. 1 qutu = 12 ədəd), barkodla birlikdə. Endpoint `items/{item}/measures`.

---

## 8. Modul: Maşın
Şəxsi avtomobil(lər)in texniki və xərc izlənməsi.
- **`vehicles`** — nəqliyyat vasitəsi (uid, ad, marka, probeq vahidi mi/km və s.). `VEHICLE_*`.
- **`vehicle_readings`** — probeq oxunuşları (tarix → odometr). **Monoton artım validasiyası**. **mil → km çevirmə**: kanonik olaraq km saxlanır, istifadəçi mil daxil etsə sistem çevirir.
- **Probeq təxmini (`PaceEstimator`):** oxunuşlar seyrək/qeyri-müntəzəm olduğu üçün **çəkili xətti reqressiya** ilə gündəlik templi (pace) hesablayır → "indi təxmini probeq nədir", "bu hissə nə vaxt bitəcək".
- **`vehicle_services` (ehtiyat hissə/servis)** — hissənin **ömrü**: **probeq (km) VƏ YA müddət (vaxt) — hansı əvvəl gəlirsə**. `closed_at` (bağlama), `close`/`reactivate` əməliyyatları. Servis xərci → xərcə çevrilir.
  - Diqqət: Eloquent magic property (`$service->item_name`) üzərində birbaşa `reset()` etmə — əvvəl lokal dəyişənə köçür (keçmiş bug).
- **`vehicle_fuel`** — yanacaq (litr, məbləğ, probeq) → **L/100km** hesablanır.
- **`vehicle_expenses`** — sərbəst xərclər (kateqoriya, məbləğ, tarix).
- Silmələrdə **confirmation** məcburi. **Frontend:** `/vehicles` (+ detal səhifəsi).

---

## 9. Modul: Öyrənmə (flashcards)
Anki tipli flashcard + aralıqlı təkrar (istifadəçi rus dili öyrənir; şəkil dəstəyi vacibdir).
- **`decks`** — kolodalar (uid, `owner_uid`, ad, təsvir). **owner-scoped** (yalnız sahib görür).
- **`cards`** — `deck_uid`, `front`/`back` (uzun mətn, sadə text), `front_image`/`back_image` (stored_file, nullable). Bir tərəf **mətn VƏ YA şəkil** ola bilər (ikisi məcburi deyil). SRS sahələri: `state` (native enum `new/learning/review`), `due` (tarix), `interval`, `ease` (decimal 2.50), `reps`, `lapses`.
- **SM-2 (`App\Support\Srs`):** `apply(card, rating)` — again→interval 0 + ease−0.20 + learning + lapses++; hard→×1.2 + ease−0.15; good→×ease; easy→×ease×1.3 + ease+0.15; ilk düz 1 gün (easy 4); ease min 1.3. `preview(card)` 4 düymə üçün gün sayı qaytarır.
- **Sessiya:** `queue` (due ≤ bugün), `answer` (rating tətbiq edir). "again" kartları sessiya daxilində sona re-queue olunur.
- **Ops:** `STUDY_VIEW/CREATE/UPDATE/DELETE`.
- **Frontend:** `/study` (koloda grid, due sayı), `/study/[deck]` (kart idarəetmə + **kart axtarışı ön/arxa üzrə** + şəkil upload), `/study/[deck]/learn` (**tam ekran slaydşou**: böyük şəkil/mətn, klik → arxa, 4 rəngli qiymət düyməsi + aralıq preview).

---

## 10. Frontend konvensiyaları
- Nav (`Sidebar.tsx`): Dashboard · Trading (jurnallar/formulalar) · Maşınlar · Öyrənmə · Kataloq (məhsullar/kateqoriyalar/ölçülər) · Kassalar · İstifadəçilər · Rollar · Ayarlar. Hər element `op` ilə qorunur (`can(op)`).
- Dillər: AZ/EN/RU (Topbar seçici). Bütün mətn `t()` ilə.
- Təkrar komponentlər: `Modal`, `Button`, `Input`, `PageHeader`, `ConfirmDialog`, `TranslatableInput`, `EntityImage`, `PickerModal`/`PickerField`/`ItemPicker`.
- Şəkil: `fileService` (XHR progress upload) + `EntityImage`/`fileUrl` (`@/services/fileService`).
- **Silmə əməliyyatları həmişə `ConfirmDialog` ilə** (təsadüfən data itməsin).

---

## 11. İş üsulu
- **Çox kiçik-kiçik tasklar** — istifadəçi strukturu/sxemi özü verir.
- **Kod yazmadan əvvəl müzakirə** — qabağa qaçma, scaffold etmə.
- Cavablar **Azərbaycanca**.
- Faktları yoxla (yaddaşdan təxmin etmə).
- **İstifadəçinin real datasını silmə** — yalnız öz test qeydlərini unikal koda görə sil, heç vaxt `delete-all`/`truncate`.
