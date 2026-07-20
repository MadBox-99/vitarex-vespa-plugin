# A4 — Szabadidősport modul: külső regisztráció és adatvédelmi elkülönítés (design)

**Dátum:** 2026-07-20
**Csomag:** 4. (A4)
**Forrás tétel:** „Vespa fejlesztés, 2026.07.17..md" — A4

## Cél

A szabadidősport (contest_type = 4) modul alkalmassá tétele **külső, nem tanulói jogviszonyban álló** résztvevők fogadására (pl. Spar Maraton). Tartalmaz: OM azonosító és iskola nélküli, önálló (front-end) regisztrációt; korlátozott, elkülönített nézetet, amelyben a külső belépő kizárólag a szabadidős versenyek nevezési felületét látja; adatvédelmi elkülönítést, hogy a külső jelentkezők ne lássák egymás adatait/listáját.

## A modul jelenlegi állapota (felmérés)

- Az egész plugin **wp-adminban** fut; **nincs publikus/önkiszolgáló regisztráció** sehol. Felhasználót csak admin hoz létre; az `athletes_signup` AJAX csak bejelentkezett (`wp_ajax_`, nincs `nopriv`).
- `contest_type=4` = szabadidősport ([includes/Core/vespa.model.contest.php](../../../includes/Core/vespa.model.contest.php)). A type-4 nevezésnél már most **globális** a létszámkeret (nincs iskolánkénti korlát) — [includes/Ajax/contest.signup.php:162](../../../includes/Ajax/contest.signup.php).
- Blokkolók az iskola nélküli résztvevőhöz: `vespa_athletes.school_id` NOT NULL + INNER JOIN az intézményre; `validate_extra()` iskola-kényszer; iskola szerinti adat-szűrés mindenütt; `init_custom_roles()` törli a nem-listázott szerepeket.

**Következtetés:** a külső résztvevő **teljesen elkülönített alrendszerként** épül meg — külön táblák, saját szerepkör, front-end felület —, a meglévő diák-táblákhoz (`vespa_athletes`/`vespa_athlete_entries`) hozzá sem érve. A meglévő diák-folyamatok változatlanok maradnak.

## Döntések (a brainstormingból)

- Felület: **front-end, wp-admin nélkül** — a külső résztvevő sosem lát admint.
- Fiók-modell: **fiók + bejelentkezés** (WP-felhasználó, `wp_signon`).
- Adatmodell: **külön külső-résztvevő tábla** (nem a `vespa_athletes`).
- Nevezés szintje: **versenyszám** (a versenyen belül konkrét esemény).
- Nyitott versenyek: **per-verseny kapcsoló** (admin nyitja/zárja).
- Megerősítés: **e-mail double opt-in** (`wp_mail`).
- Fogyatékosság-adat: **nem gyűjtjük**.
- Adatkezelési hozzájárulás: **kötelező** checkbox (`consent_at` tárolva).

## Architektúra

Négy komponens:
1. **Publikus regisztráció** + e-mail-megerősítés (nopriv).
2. **Front-end belépés + saját nézet** (nevezés/visszavonás, csak saját adat).
3. **Külső nevezés-tábla** (elkülönítve a diákadatoktól).
4. **Admin-oldal** (verseny nyitás/zárás + nevezők listája/export).

Front-end egyetlen `[vespa_szabadidos]` shortcode-dal, állapotfüggő tartalommal (kijelentkezve: Regisztráció/Belépés fülek; belépve: saját nézet).

### Új táblák (idempotens `dbDelta` aktiváláskor + `init`-kor, mert nincs migrációs rendszer)

- `vespa_external_participants`: `participant_id` (PK AI), `user_id` (WP-felhasználó), `full_name`, `birth_date`, `gender` ('férfi'/'nő'), `email`, `phone`, `consent_at` (datetime), `created_at` (datetime).
- `vespa_external_entries`: `entry_id` (PK AI), `participant_id`, `contest_id`, `contest_event_id`, `entry_date` (datetime). Visszavonás = sor törlése.
- `vespa_szabadidos_open_contests`: `contest_id` (PK). A sor létezése = a verseny külső nevezésre nyitva. (Nem `ALTER`-eljük a `vespa_contests`-et.)

Táblanevek `$wpdb->prefix` **nélkül** (kódbázis-konvenció), mint a többi `vespa_*` tábla.

### Új szerepkör

`szabadidos_resztvevo` — felvéve a [includes/Core/vespa_roles.php](../../../includes/Core/vespa_roles.php) `$custom_roles_array`-be és `get_role_capabilites()`-be, **minimális jogokkal** (nincs `vespa_*` admin-capability). Kizárva a `validate_extra()` iskola-kényszeréből. A front-end hozzáférést a saját szerep-ellenőrzéseink adják, nem WP-capabilityk.

### wp-admin teljes kizárás (a „csak a nevezési felületet látja" magja)

- `admin_init` hook: ha a belépett felhasználó **kizárólag** `szabadidos_resztvevo` és nem `wp_doing_ajax()`, akkor `wp_safe_redirect()` a publikus oldalra.
- `show_admin_bar(false)` erre a szerepkörre.
- A regisztrációkor létrehozott felhasználó **kizárólag** ezt a szerepet kapja (nincs `subscriber`).

## Komponens 1 — Publikus regisztráció + e-mail megerősítés

**Űrlap** (`[vespa_szabadidos]` Regisztráció fül): teljes név, születési dátum, nem, e-mail (= felhasználónév), jelszó + megerősítés, telefon, **kötelező adatkezelési hozzájárulás** checkbox. Nincs iskola/OM/fogyatékosság. Formátum-validáció `vespa_validate_email`/`vespa_validate_phone` helperekkel (1. csomag).

**Beküldés — nopriv admin-ajax `vespa_szabadidos_register`:**
1. Validál (mezők, `is_email`, e-mail-egyediség, jelszó-egyezés, consent, nonce).
2. `wp_insert_user` — kizárólag `szabadidos_resztvevo` szerep; `user_meta`: `vespa_szabadidos_confirmed = 0`, `vespa_szabadidos_confirm_token = wp_generate_password(32, false)`.
3. `vespa_external_participants` sor beszúrása (`user_id`, név, szül. dátum, nem, e-mail, telefon, `consent_at = now`).
4. `wp_mail`: megerősítő link a tokennel (`?vespa_szabadidos_confirm=<token>&uid=<id>`).
5. Válasz: „Elküldtük a megerősítő e-mailt."

**Megerősítés — nopriv, `init`-en figyelt query paraméter:** token-egyeztetés `hash_equals`-szel a `uid`-hez; egyezés → `confirmed = 1`, token törlése, siker-üzenet; nem egyező → hiba.

**Belépés-gátlás megerősítésig:** `authenticate` szűrő — ha a felhasználó `szabadidos_resztvevo` és `confirmed != 1`, a bejelentkezés `WP_Error` („Előbb erősítsd meg a fiókodat.").

## Komponens 2 — Belépés + saját nézet

**Belépés** (`[vespa_szabadidos]` Belépés fül): e-mail + jelszó → nopriv `vespa_szabadidos_login` → `wp_signon` (a megerősítés-gátló szűrővel). „Elfelejtett jelszó" → WP `wp_lostpassword_url()`.

**Saját nézet** (belépve, `szabadidos_resztvevo`):
- **Megnyitott versenyek:** csak a `vespa_szabadidos_open_contests`-ben szereplő type-4 versenyek (nem véglegesített/lejárt a meglévő státusz szerint), versenyenként a `vespa_constest_events` → `vespa_sport_events` versenyszámaival.
- **Nevezés:** versenyszám melletti „Nevezek" → bejelentkezett `vespa_szabadidos_signup` → sor a `vespa_external_entries`-be (`participant_id` = saját, `contest_id`, `contest_event_id`). Dupla nevezés ugyanarra a versenyszámra kizárva.
- **Saját nevezéseim:** kizárólag a saját `participant_id` sorai (a belépett `user_id` → `participant_id`). „Nevezés visszavonása" → `vespa_szabadidos_withdraw` → saját sor törlése (idegen `entry_id` visszautasítva).
- **Létszámkeret:** ha a versenynek van `ppl_num_max`-ja, globális keret-ellenőrzés nevezéskor; betelt keretnél „A helyek száma betelt.".

**Adatvédelmi elkülönítés:** minden lekérdezés a saját `participant_id`-hez kötött; más résztvevő adata/listája sehol nem jelenik meg. Minden bejelentkezett végpont: (a) szerep = `szabadidos_resztvevo`, (b) a művelet a saját `participant_id`/`entry_id`-t érinti (IDOR: `entry_id` mindig `participant_id`-vel együtt szűrve). Nonce minden műveleten.

## Komponens 3 — Külső nevezés-tábla

A `vespa_external_entries` (fent) tárolja a nevezéseket, teljesen a `vespa_athlete_entries`-től függetlenül. A résztvevő adatai a `vespa_external_participants`-ban. A két tábla köti a nevezőt a versenyhez/versenyszámhoz.

## Komponens 4 — Admin-oldal (nyitás/zárás + nevezők + export)

**Új admin-almenü** „Szabadidős külső nevezés", `riportalas` cap mögött. Egy oldalon:
1. **Nyitás/zárás:** a type-4 versenyek listája versenyenkénti „Külső regisztráció engedélyezve" kapcsolóval → sor be/kivétele a `vespa_szabadidos_open_contests`-ből. A meglévő nevezéseket nem törli.
2. **Nevezők + export:** megnyitott verseny kiválasztása után a külső nevezők táblája (név, szül. dátum, nem, e-mail, telefon, versenyszám, nevezés dátuma) — `vespa_external_entries` ⨝ `vespa_external_participants` ⨝ `vespa_constest_events`/`vespa_sport_events`. **XLSX/CSV export** a meglévő minta szerint ([includes/Export/csv.athletes.php](../../../includes/Export/csv.athletes.php)/PhpSpreadsheet).

A szervező/admin látja az összes külső nevezőt egy versenyhez (ez nem sérti az elkülönítést — a résztvevők egymást nem látják).

## Biztonság / adatvédelem

- Kétrétegű izoláció minden bejelentkezett végponton (szerep + saját `participant_id`/`entry_id`; IDOR-védelem).
- wp-admin teljes kizárás a szerepkörre.
- Nonce minden űrlapon/AJAX-on; nopriv végpontok külön nonce-szal.
- Bemenet: `sanitize_text_field`/`is_email`/`intval`; minden SQL `$wpdb->prepare`; kimenet `esc_html`/`esc_attr`.
- Token `hash_equals`, egyszeri felhasználás.
- Consent időbélyeg; kötelező checkbox.
- Auth: WP `wp_signon`/`wp_insert_user` — nincs saját jelszókezelés.

## Tesztelés

Projekt-konvenció: nincs automata suite → `php -l` + `grep` + manuális böngésző.

- Statikus: `php -l` minden új/módosított fájlra; `grep` a nonce, `$wpdb->prepare`, szerep-ellenőrzés, `hash_equals`, `esc_*` jelenlétére.
- Manuális forgatókönyvek:
  1. Regisztráció → e-mail → megerősítés előtt belépés tiltva → megerősítés után OK.
  2. Belépve: csak megnyitott type-4 versenyek + versenyszámok; nevezés, dupla nevezés tiltva, visszavonás; más adata sehol.
  3. IDOR: idegen `entry_id` visszavonása visszautasítva.
  4. wp-admin URL külső szereppel → redirect; admin-sáv nem látszik.
  5. Admin: nyitás/zárás; nevezők listája + XLSX/CSV export.

## Nem cél (YAGNI)

- Saját jelszó-visszaállítás (WP beépítettje elég).
- Rate-limiting, fizetés/regisztrációs díj.
- A diák-nevezési folyamat bármilyen módosítása.
- `ALTER TABLE` a meglévő táblákon (csak új táblák).
- Fogyatékosság/OM/iskola gyűjtése a külső résztvevőtől.
