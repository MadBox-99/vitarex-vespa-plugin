# Tanár törölheti a saját intézménye diákjait (soft delete) — tervezési dokumentum

Dátum: 2026-05-29

## Cél

A testnevelő és az iskolaigazgató a **saját intézményéhez** tartozó diákokat
tudja törölni a Sportolók listából. A törlés **soft delete** (archiválás): a
diák fizikailag a DB-ben marad, csak megjelölve és a listákból kivéve, így az
előzmények (korábbi nevezések, eredmények) épek maradnak és DB-szinten
visszaállítható.

## Háttér / jelenlegi állapot

- A Sportolók lista ([templates] `page=athletes`) már most a bejelentkezett
  tanár/igazgató saját iskolájára van szűrve a
  `vespa_get_contest_athlete_filter()` ([includes/Core/functions.php]) révén
  (TESTNEVELŐ/ISKOLAIGAZGATÓ → `AND school_id = <saját>`).
- A törlés jelenleg csak admin / versenykezelő joghoz kötött
  ([includes/Datalist/datalist.athletes.php] `checkDelete`), és **hard delete**,
  mert a `vespa_athletes` táblának nincs `is_deleted` oszlopa.
- A `VESPA_Datalist` ősosztály ([includes/Core/core.datalist.php]) már támogatja
  a soft delete-et: ha `$soft_delete = true`, a `delete()` AJAX `is_deleted=1`-re
  állít (nem töröl fizikailag), és minden törlést `vitarex_log`-ba ír (audit).
  A `delete()` a valódi `$id`-vel hívja a `checkDelete($id)`-t, így a
  tulajdonlás ellenőrizhető.
- A `vespa_athletes`-ből ~20 helyen kérdezünk le (lista, nevezés, export,
  riport, eredmények). Külön „törölt elemek / visszaállítás" UI sehol nincs (a
  2.2.0-s sport-soft-delete sem készített ilyet).

## Döntések (a brainstormingból)

1. Jogosultak: **TESTNEVELŐ + ISKOLAIGAZGATÓ** (a saját iskolájuk diákjaira).
2. Törlés módja: **soft delete** (archiválás), nem hard delete, nem tiltás.
3. A már benevezett diák archiválásakor a **meglévő nevezések/eredmények
   érintetlenek maradnak** — az archiválás csak a listákból és a nevezhető
   listából veszi ki a diákot.

## Komponensek

### 0. Iskolaigazgató hozzáférése a Sportolók listához — `includes/Core/vespa_roles.php`

Az ISKOLAIGAZGATÓ jelenleg **nem** rendelkezik a `sportolok_listazasa`
joggal, ezért ma el sem éri a Sportolók oldalt (`page=athletes` ezt a jogot
követeli). Hogy a törlés értelmes legyen nála, a `get_role_capabilites()`
capability-mapben az ISKOLAIGAZGATÓ kapja meg:

```php
VESPA_Roles::sportolok_listazasa => true,
```

A `VESPA_Roles::init_custom_roles()` minden `init` hookon lefut és
`$role->add_cap()`-pal alkalmazza a mapet, így a jog a **következő
oldalbetöltéskor automatikusan** érvénybe lép — nincs szükség a plugin
újraaktiválására vagy migrációra. (A listát a meglévő
`vespa_get_contest_athlete_filter()` az igazgatónál is a saját iskolára szűri.)

### 1. DB migráció — `database/changes.sql`

Dátumozott bejegyzés a fájl végére:

```sql
ALTER TABLE `vespa_athletes`
    ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
```

Manuálisan futtatandó az éles DB-n (a plugin nem futtatja automatikusan a
changes.sql-t).

### 2. `VESPA_Athletes` osztály — `includes/Datalist/datalist.athletes.php`

- **`protected $soft_delete = true;`** — a base `delete()` ezzel archivál
  (`is_deleted=1`, `deleted_at=most`) hard delete helyett, és audit logot ír.
- **`checkDelete($id)`** átírása:
  - `true`, ha `current_user_can('manage_options')` vagy
    `current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)`
    (a jelenlegi admin/versenykezelő jog megmarad);
  - egyébként, ha a felhasználó **TESTNEVELŐ vagy ISKOLAIGAZGATÓ**:
    - `$id == 0` (gomb-megjelenítési próba az `addActionButtons`-ból) → `true`
      (hogy a gomb kirajzolódjon; a valódi tulajdonlás a tényleges törléskor
      dől el);
    - `$id > 0` → betölti a diákot, és `true` csak ha
      `athlete.school_id == vespa_get_my_school_id()`;
  - minden más esetben `false`.
- **`getFilters()`** kiegészítése `AND vespa_athletes.is_deleted=0`-val (a
  subclass felülírja a base getFilters-t, ezért itt explicit kell). Az archivált
  diákok így nem jelennek meg a listában.
- **`addActionButtons($item)`**: a törlés gomb feltétele
  `checkDelete($item->athlete_id)` (a jelenlegi `checkDelete(0)` helyett), így a
  kuka ikon csak a ténylegesen törölhető (saját iskolás) sorokon jelenik meg.

### 3. `is_deleted=0` szűrés a „jelenlegi diákot" listázó/választó lekérdezésekbe

Csak ott szűrünk, ahol **élő, jelenlegi** diákot listázunk vagy választunk;
a történeti (entries/results-on átmenő) lekérdezéseket **nem** szűrjük.

**Szűrendő (élő diák kontextus) — `AND ...is_deleted=0` hozzáadása:**
- `templates/contest_view_entering.php` — a „Nevezhető" lista lekérdezése
  (a meglévő `a.active=1` mellé `a.is_deleted=0`). A „Nevezett" oldal
  athlete_entries-en alapul, így a már nevezett (akár archivált) diák ott
  megmarad — ez szándékos.
- `templates/contest_view_save.php` — a diáklista lekérdezése.
- `includes/Export/export_athletes.php` — aktuális sportoló-export.
- `templates/import_athletes.php` — a dedup-ellenőrzés (az archivált diákot ne
  vegye létező találatnak; új importnál friss rekord jön létre).

**NEM szűrendő (történeti kontextus, athlete_entries/results JOIN athlete_id):**
- `templates/results_dashboard.php` — a sportoló-lista csak **eredménnyel
  rendelkező** diákokat tartalmaz (INNER JOIN `athlete_entries` + `results`),
  tehát történeti nézet: az archivált, de eredményes diák eredménye maradjon
  elérhető.
- `templates/contest_view_racelist.php`, `templates/contest_results.php`,
  `includes/Ajax/ajax.contest_entries.php`, `includes/Ajax/ajax.contest_results.php`,
  `includes/Ajax/contest.signup.php` (a benevezettek számolása),
  `includes/Export/download_contest_docs.php`, `includes/Export/download_riports.php`.
  Ezek már leadott nevezésekhez/eredményekhez tartozó diákok nevét jelenítik meg
  — az archivált, de korábban nevezett diák neve itt megmarad.

A megvalósítási terv minden érintett lekérdezést egyenként ellenőriz, és csak a
fenti „élő diák" csoportba esőkre teszi a szűrőt.

### 4. Megerősítés és felület

Új JS/UI nem szükséges: a meglévő `delete-entity` gomb + `confirm-box.php` modal
(amit az `addActionButtons` már használ) és a base `delete()` AJAX
(`wp_ajax_delete_athletes`, nonce-ellenőrzéssel) látja el a teljes folyamatot.

## Hibakezelés / biztonság

- A `delete()` a tényleges `$id`-vel hívja a `checkDelete`-et → **idegen iskola
  diákját nem lehet törölni** (a válasz: „Jogosulatlan hozzáférés").
- A nonce-ellenőrzés (`check_ajax_referer('vespa_nonce','nonce')`) már a base
  `delete()`-ben van.
- Minden törlést a base `delete()` `vitarex_log`-ba ír (mód: soft, id, rekord),
  így rekonstruálható ki, mikor, melyik diákot archiválta.

## Tesztelés (manuális — nincs automatizált tesztkészlet)

- Tanárként/igazgatóként a saját iskola egy diákjának archiválása: eltűnik a
  Sportolók listából és a nevezésnél a „Nevezhető" listából.
- Idegen iskola diákjának törlési kísérlete (pl. id-manipulációval): elutasítva.
- Archivált, de korábban nevezett diák: a múltbeli eredmény/nevezési lista/
  export továbbra is mutatja a nevét; a meglévő nevezései érintetlenek.
- Admin/versenykezelő továbbra is törölhet (most már szintén soft delete-tel).
- A diák `php -l`-clean marad minden módosított PHP fájl.

## Hatókörön kívül (YAGNI)

- Külön „Törölt diákok / visszaállítás" felület. Az archivált diák a DB-ben
  marad; a visszaállítás DB-szinten (`is_deleted=0`) vagy egy későbbi feature.
- A meglévő nevezések automatikus eltávolítása archiváláskor (a döntés szerint
  érintetlenek maradnak).

## Érintett fájlok összefoglalva

- `includes/Core/vespa_roles.php` — `sportolok_listazasa` jog az ISKOLAIGAZGATÓ-nak.
- `database/changes.sql` — `is_deleted` + `deleted_at` a `vespa_athletes`-hez.
- `includes/Datalist/datalist.athletes.php` — `$soft_delete`, `checkDelete`,
  `getFilters`, `addActionButtons`.
- `templates/contest_view_entering.php`, `templates/contest_view_save.php`,
  `templates/import_athletes.php`, `includes/Export/export_athletes.php` —
  `is_deleted=0` szűrő az élő-diák lekérdezésekbe.
