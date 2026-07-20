# A1 — Excel-alapú tömeges diákimport — tervezési dokumentum

Dátum: 2026-07-20

Forrás: „Vespa fejlesztés, 2026.07.17..md" — az **A1** tétel (2. csomag).

## Cél

A tanulói adatok egyesével történő, időigényes rögzítése helyett strukturált
Excel/CSV sablon alapján történő **tömeges importálás**, kétlépéses
(előnézet → jóváhagyás) folyamattal. Tartalmazza a feltöltő felületet, az
Excel-sablon kialakítását, a soronkénti validációt (kötelező mezők, formátum),
a hibás/duplikált sorok visszajelzését és a sikeres import naplózását.

## A teljes fejlesztési lista hatókör-bontása

Emlékeztető (az 1. csomag brainstormingjából, a felhasználóval egyeztetve):

| Csomag | Tartalom | Állapot |
|---|---|---|
| 1. | A5 + A3 + A6 | **kész, merge-elve a main-be** |
| **2. (ez a spec)** | A1 — Excel-alapú tömeges diákimport | tervezés |
| 3. | A2 — bővített szűrés az eredménylistákban | később, saját spec |
| 4. | A4 — szabadidősport külső regisztráció | később, saját spec |
| ~~B1~~ | ~~FODISZ honlap megtekintés-kimutatás~~ | **elvetve** — külön WP telepítés, nem ez a plugin kezeli |

## Háttér / jelenlegi állapot

A jelenlegi import a `templates/import_athletes.php`-ben él (223 sor), és minden
felelősséget egyetlen fájlban kever: CSV-parse, validáció, DB-írás, HTML.

**Belépési pont / jogosultság:**
- `includes/Admin/menu.masterdata.php` — `add_submenu_page('athletes',
  'Sportoló import', ..., 'sportolok_letrehozasa_modositasa', 'import_athletes',
  'vespa_menu_import_athletes')`. URL: `admin.php?page=import_athletes`.
- A `sportolok_letrehozasa_modositasa` cap birtokosai: TESTNEVELO,
  FOVESZ_FODISZ_SPORTIGAZGATO, ADMINISZTRATOR.
- **A template maga nem tartalmaz cap-ellenőrzést és nonce-t** — kizárólag a
  menü capability argumentuma véd.

**Oszlopsorrend** (a `includes/Export/csv.athletes.php` mintafájl fejlécével
egyezően), `data[0]`..`data[15]`:

| idx | fejléc | cél oszlop (`vespa_athletes`) |
|---|---|---|
| 0 | Sportoló neve | `athlete_name` |
| 1 | Születési hely | `birth_place` |
| 2 | Születési dátum | `birth_date` |
| 3 | Anyja neve | `mothers_name` |
| 4 | Telefonszám | `phone` |
| 5 | Email | `email` |
| 6 | Irányítószám | `home_zipcode` |
| 7 | Település | `home_city` |
| 8 | Lakcím | `home_address` |
| 9 | Állampolgárság | `nationality` |
| 10 | Igazolványszám | `personal_id` |
| 11 | Nem | `gender` |
| 12 | Fogyatékosság típusa | `disability_type` (névből ID) |
| 13 | Nyilvántartásba vétele | `registered_at` |
| 14 | Megjegyzés | `note` |
| 15 | Aktív | `active` |

**Kritikus hibák a jelenlegi kódban** (az A1-nek orvosolnia kell):

1. **Néma-kihagyás** (`import_athletes.php:60`): `elseif (count($import_errors)
   == 0)` — a beszúrás feltétele a **teljes, addig gyűlt hibalista üressége**.
   Az első hibás sor után **minden további sor némán kimarad** (nem is kap külön
   hibaüzenetet). Nincs tranzakció, nincs rollback: a hiba előtti sorok bent
   maradnak.
2. **Fordított `active`** (`:46-52`): `if($data[15] == 0){ $data[15]=null; }
   else { $data[15]=0; }` — a mintafájl `"1"`-je `active=0`-ként (inaktív!), a
   `"0"` pedig `NULL`-ként kerül be. Gyakorlatilag minden importált diák inaktív
   lesz.
3. **Fogyatékosság-szentinel** (`:30-36, :121-133`): ismeretlen fogyatékossági
   típusnál `getDisabilityId()` az `id=1`-et adja vissza szentinelként →
   hibaüzenet. Következmény: az **1-es ID-jű valódi rekord soha nem
   importálható**, és a beszúrás (ha lefutna) rossz típust írna.

**További gyengeségek:**
- MIME-ellenőrzés a **böngésző által küldött** `$_FILES['file']['type']`-ra
  (`:19`), csak `text/csv` — sok legitim fájl elutasítva.
- `fgetcsv($handle, 1000, ";")` (`:24`) — **1000 bájt/sor limit**, hosszú sorok
  csonkolva.
- Nincs xlsx-olvasás (a PhpSpreadsheet be van vendorolva — `lib/vendor/
  phpoffice/phpspreadsheet ^1.21` —, de a plugin csak **írásra** használja).
- Nincs napló (`:90` a logolás ki van kommentezve; a `vitarex_log`
  infrastruktúra létezik: `includes/Core/functions.php:9-28`).
- Nincs formátum-validáció (üres név/dátum átmegy; e-mail sosem ellenőrzött).
- Az iskola a form legördülőből jön (`:63`), nem a fájlból — **egy iskola per
  fájl**. Az intézménylista szűrés nélkül (`:11-12`), testnevelő is bármelyik
  iskolát választhatja.
- **Dedup** (`:138-148`): 4 mezős egyezés (név+szül.hely+szül.dátum+anyja neve),
  `is_deleted=0`, iskolától függetlenül (globális).

**Rendelkezésre álló eszközök:**
- PhpSpreadsheet readerek (`Xlsx`, `Csv`, `Xls`, `Ods`) a `lib/vendor`-ban.
- `vitarex_log($action, $description, $table_name, $level)` naplózáshoz.
- `vespa_validate_email()`, `vespa_validate_phone()` (az 1. csomagban bevezetve).
- `VespaFileUpload` (`includes/Core/core.fileupload.php`) — a versenyfájlokhoz
  való (kép/pdf → `vespa_files`), **nem** újrahasznosítható ide, de mutatja a
  Core-osztály mintát.

## Döntések (a brainstormingból)

1. **Formátum: xlsx + a meglévő CSV is.** A PhpSpreadsheet `IOFactory` egységesen
   olvassa mindkettőt, egy kódúton. A régi CSV-fájlok visszafelé kompatibilisek
   maradnak.
2. **Iskola: marad az űrlap-legördülő, egy iskola/fájl.** A sablonban **nincs**
   iskola oszlop. (A testnevelőnek úgyis csak a saját iskolája van.)
3. **Hibamodell: kétlépéses előnézet → jóváhagyás → import.** A feltöltés előbb
   validál és megmutatja, mi lesz (hány jó / dup / hibás), de **semmit nem ír a
   DB-be**; a felhasználó jóváhagyása után mennek be a jó sorok.
4. **Kötelező mezők:** Sportoló neve, Születési dátum, Nem, Fogyatékosság
   típusa. A többi opcionális. (A születési hely és anyja neve opcionális, de a
   dedup-kulcs része.)
5. **Duplikátumok: kihagyva, jelezve.** Nem hiba és nem blokkol — külön
   csoportban jelenik meg („már létezik, kihagyva"), az import átugorja.
6. **Naplózás: import-összegzés** a `vitarex_log`-ba (nem soronként).
7. **Validátorok:** az e-mail/telefon a meglévő `vespa_validate_email()` /
   `vespa_validate_phone()` helperekkel.

**Technikai döntések (a brainstorming során rögzítve, nem külön kérdezve):**
- Az előnézet-állapot átvitele a két lépés között **WP transient**-tel
  (`vespa_import_<token>`, 15 perc TTL); a `valid[]` sorokat **és a `school_id`-t**
  tárolja, így a fájlt **nem kell újra feltölteni** a jóváhagyáshoz. A token
  rejtett mezőben + nonce. A `school_id` a transientből jön (nem a 2. lépésben
  újraküldött értékből), így a jóváhagyáskor nem hamisítható idegen iskolára.
- A commit **tranzakcióban** fut, biztonsági újra-dedup ellenőrzéssel.

## Komponensek

### 1. `VESPA_Athlete_Importer` — `includes/Core/vespa.athlete_import.php` (ÚJ)

Az import motor, HTML és felület nélkül. A `Core` könyvtár töltődik be elsőként
(`vitarex-vespa-plugin.php:51`), így az osztály az Admin/Ajax rétegből elérhető.

**`parse($file_path): array`**
- A PhpSpreadsheet `IOFactory::identify()` + `IOFactory::load()` felismeri az
  xlsx/CSV formátumot, és beolvassa a munkalapot soronkénti tömbbé.
- A fejlécsort (1. sor) átugorja.
- Visszaadja a nyers cellák tömbjét (soronként 0..15 indexelt tömb).
- Sérült/olvashatatlan fájlnál `Exception`-t dob (a hívó barátságos üzenetté
  fordítja).

**`validate(array $rows, int $school_id): array`** — **tiszta függvény, DB-írás
nélkül.** Visszaad egy strukturált eredményt:
```php
[
    'valid'     => [ /* import-kész, sanitizált sorok (a commit ezt írja) */ ],
    'duplicate' => [ ['row' => N, 'name' => '...'], ... ],   // már létezik
    'error'     => [ ['row' => N, 'messages' => ['...']], ... ],
    'total'     => <beolvasott sorok száma>,
]
```
Soronkénti szabályok (lásd a „Validációs szabályok" táblát lent). A dedup
(`is_deleted=0`, 4 mezős) a `duplicate` csoportba sorol; a hibás sor az `error`
csoportba (a jó és a hibás állapot **kizáró**: egy hibás sor nem kerül a
`valid`-ba, akkor sem, ha egyébként dup lenne).

**`commit(array $valid_rows, int $school_id): int`** — a `$school_id` a
transientből származik (a hívó onnan olvassa), nem a 2. lépésben újraküldött
POST-ból.
- `$wpdb->query('START TRANSACTION')` → soronkénti `$wpdb->insert('vespa_athletes',
  ...)` → `COMMIT` (hiba esetén `ROLLBACK`).
- **Biztonsági újra-dedup** minden sorra közvetlenül a beszúrás előtt (az
  előnézet óta létrejöhetett ütközés); a közben duplikálttá vált sort átugorja.
- Minden beszúrt sor `modified_at` + `modified_by` mezőt kap.
- Visszaadja a ténylegesen beszúrt sorok számát.

### 2. Template `import_athletes.php` — kétlépéses UI (átírás)

**Csak felület, üzleti logika nélkül** — a `VESPA_Athlete_Importer`-t hívja.

- **1. lépés — feltöltés:** iskola-legördülő (testnevelőnél a saját iskolájára
  szűrve) + fájlmező + „Feltöltés" gomb, `wp_nonce_field`-del.
  - Beküldéskor: cap-ellenőrzés → fájl-ellenőrzés → `parse()` → `validate()`.
  - A `valid[]` egy transientbe (`vespa_import_<token>`, 15 perc), a token rejtett
    mezőbe.
- **Előnézet:** összegző sor („42 sor beolvasva — 38 importálható, 3 már létezik
  (kihagyva), 1 hibás"), majd táblázat a **hibás** sorokról (sorszám + okok) és a
  **kihagyott duplikátumokról** (sorszám + név). Alul: „Jóváhagyom és
  importálom" (rejtett token + nonce) és „Mégse".
- **2. lépés — commit:** a „Jóváhagyom" beküldésekor nonce + token ellenőrzés →
  a transient betöltése (a `valid[]` sorok **és a `school_id`** onnan jön, nem
  újraküldött POST-ból) → `commit()` → a transient törlése → összegző üzenet +
  `vitarex_log`. Lejárt/hiányzó transientnél barátságos elutasítás („az előnézet
  lejárt, tölts fel újra").

### 3. Excel-sablon generátor — `includes/Export/csv.athletes.php` (kiegészítés)

- A meglévő CSV-minta (`?vespa_athletes_csv_sample=1`) **megmarad**.
- Új: xlsx-minta (`?vespa_athletes_xlsx_sample=1`) a PhpSpreadsheet `Writer\Xlsx`-
  szel, **azonos fejléc-oszlopokkal** és 1-2 mintasorral. Mindkettő letölthető a
  felületről.
- A minta-letöltés cap-ellenőrzést kap (ma csak `is_user_logged_in()`).

### 4. Validációs szabályok

| Mező | Szabály | Sérülés |
|---|---|---|
| Sportoló neve (0) | **kötelező**, nem üres | hibás sor |
| Születési dátum (2) | **kötelező**, érvényes dátum `YYYY-MM-DD` | hibás sor |
| Nem (11) | **kötelező**, `Nő`/`Férfi` (kis/nagybetű tűrve) | hibás sor |
| Fogyatékosság típusa (12) | **kötelező**, létezik a `vespa_disability_groups`-ban | hibás sor |
| E-mail (5) | opcionális; ha kitöltve → `vespa_validate_email()` | hibás sor |
| Telefon (4) | opcionális; ha kitöltve → `vespa_validate_phone()` | hibás sor |
| Születési hely (1), anyja neve (3) | opcionális (dedup-kulcs része) | — |
| Irsz/település/cím/állampolgárság/ig.szám/nyilvántartás/megjegyzés | opcionális, `sanitize_text_field` | — |
| Aktív (15) | opcionális: `1`/`Igen`/üres→1 (aktív, a séma default), `0`/`Nem`→0 (inaktív) | — |

- **Dátum-validáció:** a `birth_date` és (ha kitöltve) a `registered_at`
  `DateTime`-mal ellenőrizve (`YYYY-MM-DD`); a hibás dátum a sort hibássá teszi
  (a `registered_at` opcionális, üresen átmegy).
- **Fogyatékosság:** a nevet a `vespa_disability_groups`-ban keressük; ha nincs
  találat, a sor **hibás** (nincs több szentinel-id-1 hack), a beszúrás a valódi
  lekérdezett `disability_group_id`-t használja.
- Minden szöveges mező `sanitize_text_field`-en át kerül a `valid[]`-ba (XSS a
  visszarenderelésnél).

### 5. Hibakezelés / biztonság

- **Fájl-ellenőrzés:** kiterjesztés (`.xlsx`/`.csv`) + a PhpSpreadsheet
  `IOFactory::identify()` általi tartalmi felismerés — **nem** a böngésző
  MIME-jére hagyatkozva. Érvénytelen/sérült fájl → barátságos hibaüzenet.
- **Nonce** mindkét lépésen (`vespa_nonce`, `wp_verify_nonce`).
- **Jogosultság:** a kezelő explicit `current_user_can(
  VESPA_Roles::sportolok_letrehozasa_modositasa)` ellenőrzést kap. Az
  iskola-legördülő testnevelőnél csak a saját iskoláját kínálja
  (`vespa_get_my_school_id()`); a beküldött `school_id`-t is ellenőrzi, hogy a
  felhasználó jogosult-e rá (idegen iskola nem választható).
- **Tranzakció** a commitkor (rollback hibánál).
- **Transient TTL** 15 perc; lejárt/hiányzó token → elutasítás.
- A commit `$wpdb->insert` prepare-el megy (SQL-injection ellen véd); a szöveges
  mezők `sanitize_text_field`-esek.

### 6. Naplózás

A commit után egy `vitarex_log` bejegyzés:
- `action`: `athlete_import`
- `description`: iskola (id/név), beolvasott / importált / kihagyott (dup) /
  hibás darabszám.
- `table_name`: `vespa_athletes`.
Az automatikus `user_id` + `user_login` a `vitarex_log`-ból jön.

## Tesztelés (manuális — nincs automatizált tesztkészlet)

- **`php -l`** minden módosított/új PHP fájlra.
- **Jó fájl** (xlsx és CSV is): minden sor importálható → az összes bemegy, a
  napló és az összegzés stimmel, a diákok **aktívak** (a fordított-active bug
  javítva).
- **Vegyes fájl** (jó + duplikátum + hibás sorok): az előnézet helyesen bontja
  szét a három csoportot, a jóváhagyás **csak a jó sorokat** írja be, a
  duplikátum kimarad, a hibás sor kimarad — és **a hibás sor utáni jó sorok is
  bemennek** (a néma-kihagyás bug javítva).
- **Kötelező mező hiánya** (üres név/dátum/nem/fogyatékosság): a sor hibás,
  konkrét üzenettel.
- **Ismeretlen fogyatékosság:** a sor hibás (nem a szentinel-id-1), a jó sorok
  bemennek.
- **Rossz e-mail/telefon** kitöltve: a sor hibás; üresen átmegy.
- **Régi CSV visszafelé-kompatibilitás:** a meglévő `;`-elválasztású CSV
  ugyanúgy importálódik.
- **Sérült fájl / rossz kiterjesztés:** barátságos hiba, semmi nem íródik be.
- **Lejárt transient:** a jóváhagyás elutasít, újrafeltöltésre kér.
- **Idegen iskola:** testnevelő nem választhat/küldhet más iskolát.
- **Nonce hiánya/rossz:** a beküldés elutasítva.

## Hatókörön kívül (YAGNI)

- **Iskola oszlop a sablonban** (több iskola egy fájlban). A 2. döntés szerint
  egy iskola/fájl marad.
- **Upsert** (meglévő diák frissítése a fájlból). Az 5. döntés szerint a
  duplikátum kihagyva, nem frissítve.
- **Soronkénti naplózás.** Csak import-összegzés.
- **Aszinkron / chunkolt feldolgozás** nagyon nagy fájlokhoz. Az iskolai
  névsorok mérete (tíz–néhányszáz sor) mellett a szinkron feldolgozás elég; a
  transient a `valid[]` sorokat tárolja, ami ezen a méreten belül biztonságos.
- **A régi `xls`/`ods` formátumok** aktív hirdetése. A `IOFactory` beolvassa
  őket, de a sablon és a dokumentáció xlsx + CSV.
- **A `active` oszlop `Igen/Nem` melletti egyéb szinonimák** (pl. `true/false`).
  Csak `1`/`0`/`Igen`/`Nem`/üres.

## Érintett fájlok

- `includes/Core/vespa.athlete_import.php` — **ÚJ**: `VESPA_Athlete_Importer`
  (`parse`, `validate`, `commit`).
- `templates/import_athletes.php` — **átírás**: kétlépéses UI, üzleti logika
  nélkül; nonce + cap + iskola-szűrés.
- `includes/Export/csv.athletes.php` — **kiegészítés**: xlsx-minta generátor +
  cap-ellenőrzés a minta-letöltésen.
- (Esetleg) `includes/Admin/menu.masterdata.php` — csak ha a menü-callback
  finomításra szorul; a cap változatlan.
