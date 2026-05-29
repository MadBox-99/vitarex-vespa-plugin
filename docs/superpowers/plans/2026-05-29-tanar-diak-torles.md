# Tanár törölheti a saját iskolája diákjait (soft delete) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A testnevelő és az iskolaigazgató a saját intézményéhez tartozó diákokat archiválhassa (soft delete) a Sportolók listából, az előzmények (nevezések, eredmények) sérülése nélkül.

**Architecture:** A `vespa_athletes` táblára bevezetjük a meglévő `VESPA_Datalist` soft-delete infrastruktúrát (`is_deleted`/`deleted_at` + `$soft_delete=true`). A `checkDelete()` jogosultság + tulajdonlás (saját iskola) alapján enged. Az élő-diákot listázó/választó lekérdezésekbe `is_deleted=0` szűrő kerül; a történeti (entries/results-on átmenő) lekérdezések érintetlenek.

**Tech Stack:** WordPress plugin, PHP 8.4, `$wpdb`, a `VESPA_Datalist` ősosztály, kézzel futtatott SQL changelog (`database/changes.sql`).

**Megjegyzés a tesztelésről:** Nincs automatizált tesztkészlet. A „test" lépések PHP szintaxis-ellenőrzés (`php -l`) és manuális böngészős ellenőrzés. Minden PHP-módosítás után `php -l` fut.

---

## File Structure

- `database/changes.sql` — **módosít**: `is_deleted` + `deleted_at` oszlop a `vespa_athletes`-hez (dátumozott bejegyzés).
- `includes/Core/vespa_roles.php` — **módosít**: `sportolok_listazasa` jog az ISKOLAIGAZGATÓ capability-mapjébe.
- `includes/Datalist/datalist.athletes.php` — **módosít**: `$soft_delete`, `checkDelete`, `getFilters`, `addActionButtons`.
- `templates/contest_view_entering.php` — **módosít**: `a.is_deleted=0` a nevezhető-lista szűrőjébe.
- `templates/contest_view_save.php` — **módosít**: `is_deleted=0` az entry-modal diáklistájába.
- `includes/Export/export_athletes.php` — **módosít**: `is_deleted=0` az export-lekérdezésbe.
- `templates/import_athletes.php` — **módosít**: `is_deleted=0` a dedup-ellenőrzésbe.

---

## Task 1: DB migráció — `is_deleted` + `deleted_at` a `vespa_athletes`-hez

**Files:**
- Modify: `database/changes.sql` (fájl vége)

- [ ] **Step 1: A migráció hozzáfűzése a changelog végéhez**

Fűzd a `database/changes.sql` **végére**:

```sql

--2026.05.29.
-- Soft delete a sportolóknál: a testnevelő/igazgató archiválhatja a saját
-- iskolája diákjait. A diák a DB-ben marad, csak is_deleted=1 jelölést kap.
ALTER TABLE `vespa_athletes`
    ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
```

- [ ] **Step 2: A migráció lefuttatása az adatbázison**

Futtasd le az új `ALTER TABLE` utasítást a fejlesztői/éles DB-n (a plugin nem futtatja automatikusan a `changes.sql`-t). Ellenőrzés:

Run (a DB-ben): `SHOW COLUMNS FROM vespa_athletes LIKE 'is_deleted';`
Expected: egy sor (`is_deleted`, `tinyint(1)`).

> Ha innen nincs DB-hozzáférés, ez a lépés manuális marad a szerveren — a többi task kódja akkor is helyes, de a futáshoz a tábla már kell.

- [ ] **Step 3: Commit**

```bash
git add database/changes.sql
git commit -m "db: is_deleted + deleted_at a vespa_athletes-hez (soft delete)"
```

---

## Task 2: Iskolaigazgató hozzáférése a Sportolók listához

**Files:**
- Modify: `includes/Core/vespa_roles.php` (az `ISKOLAIGAZGATO => array(` blokk)

- [ ] **Step 1: `sportolok_listazasa` jog hozzáadása az ISKOLAIGAZGATÓ-hoz**

A `includes/Core/vespa_roles.php`-ben keresd meg ezt a két sort:

```php
                    VESPA_Roles::ISKOLAIGAZGATO => array(
                        VESPA_Roles::tanulok_listazasa => true,
```

És cseréld erre (új sor beszúrása a tömb elejére):

```php
                    VESPA_Roles::ISKOLAIGAZGATO => array(
                        VESPA_Roles::sportolok_listazasa => true,
                        VESPA_Roles::tanulok_listazasa => true,
```

> A `VESPA_Roles::init_custom_roles()` minden `init` hookon lefut és
> `$role->add_cap()`-pal alkalmazza a mapet, így a jog a következő
> oldalbetöltéskor automatikusan érvénybe lép (nincs újraaktiválás).

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa_roles.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add includes/Core/vespa_roles.php
git commit -m "feat: iskolaigazgató sportolok_listazasa jog (Sportolók lista hozzáférés)"
```

---

## Task 3: `VESPA_Athletes` — soft delete + tulajdonlás-alapú törlés

**Files:**
- Modify: `includes/Datalist/datalist.athletes.php`

- [ ] **Step 1: `$soft_delete` property bekapcsolása**

A `includes/Datalist/datalist.athletes.php`-ben keresd meg:

```php
    protected $columns           = array('athlete_id', 'athlete_name', 'ins_name', 'birth_place', 'birth_date', 'mothers_name');
```

És **közvetlenül utána** szúrd be:

```php
    protected $soft_delete       = true;
```

- [ ] **Step 2: `checkDelete()` átírása (jogosultság + tulajdonlás)**

Keresd meg a jelenlegi metódust:

```php
    public function checkDelete( $id ){
        return current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles );
    }
```

És cseréld erre:

```php
    public function checkDelete( $id ){
        // admin / versenykezelő: változatlanul törölhet
        if ( current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ) {
            return true;
        }

        // testnevelő / iskolaigazgató: csak a saját iskolája diákját
        $is_school_user = VESPA_Roles::getInstance()->current_user_has_role( VESPA_Roles::TESTNEVELO )
            || VESPA_Roles::getInstance()->current_user_has_role( VESPA_Roles::ISKOLAIGAZGATO );

        if ( ! $is_school_user ) {
            return false;
        }

        // $id == 0: csak a gomb megjelenítési próbája (addActionButtons) –
        // a valódi tulajdonlás a tényleges törléskor ($id > 0) dől el.
        if ( intval( $id ) === 0 ) {
            return true;
        }

        $athlete = $this->load( $id );

        return $athlete && intval( $athlete->school_id ) === intval( vespa_get_my_school_id() );
    }
```

- [ ] **Step 3: `getFilters()` — archiváltak kizárása a listából**

Keresd meg:

```php
    public function getFilters()
    {
        global $wpdb;
        $filters = '1';
```

És cseréld a `$filters = '1';` sort erre:

```php
        $filters = 'vespa_athletes.is_deleted=0';
```

(A metódus többi része változatlan: a `vespa_athletes` a fő tábla, így a
`vespa_athletes.is_deleted=0` egyértelmű.)

- [ ] **Step 4: `addActionButtons()` — törlés gomb a saját sorokon**

Keresd meg:

```php
        if( $this->checkDelete(0) ){
```

És cseréld erre (a sor valódi azonosítójával):

```php
        if( $this->checkDelete( $item->{$this->id_field} ) ){
```

- [ ] **Step 5: Szintaxis-ellenőrzés**

Run: `php -l includes/Datalist/datalist.athletes.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add includes/Datalist/datalist.athletes.php
git commit -m "feat: sportoló soft delete + tulajdonlás-alapú törlés (testnevelő/igazgató)"
```

---

## Task 4: `is_deleted=0` szűrő az élő-diákot listázó/választó lekérdezésekbe

**Files:**
- Modify: `templates/contest_view_entering.php`
- Modify: `templates/contest_view_save.php`
- Modify: `includes/Export/export_athletes.php`
- Modify: `templates/import_athletes.php`

> A történeti (entries/results-on átmenő) lekérdezéseket **nem** módosítjuk:
> `results_dashboard.php`, `contest_view_racelist.php`, `contest_results.php`,
> `ajax.contest_entries.php`, `ajax.contest_results.php`, `contest.signup.php`,
> `download_contest_docs.php`, `download_riports.php`. Ezekben a már nevezett/
> eredményes (akár archivált) diák neve szándékosan megmarad.

- [ ] **Step 1: `contest_view_entering.php` — nevezhető lista szűrője**

Keresd meg:

```php
                        $filters = "a.school_id=%d AND a.active=1 AND a.disability_type IN ($placeholders) ";
```

Cseréld erre:

```php
                        $filters = "a.school_id=%d AND a.active=1 AND a.is_deleted=0 AND a.disability_type IN ($placeholders) ";
```

- [ ] **Step 2: `contest_view_save.php` — entry-modal diáklista**

Keresd meg:

```php
    $athletes = $wpdb->get_results("SELECT * FROM vespa_athletes WHERE $filter ORDER BY athlete_name ASC");
```

Cseréld erre:

```php
    $athletes = $wpdb->get_results("SELECT * FROM vespa_athletes WHERE $filter AND is_deleted=0 ORDER BY athlete_name ASC");
```

- [ ] **Step 3: `export_athletes.php` — sportoló-export lekérdezés**

Keresd meg (a több soros SQL `WHERE 1`-gyel záruló része):

```php
            $sql = "SELECT vespa_athletes.*, vespa_institutions.ins_name, vespa_disability_groups.disability_group_name 
            FROM vespa_athletes 
            JOIN vespa_institutions ON vespa_institutions.institution_id = vespa_athletes.school_id 
            JOIN vespa_disability_groups ON vespa_disability_groups.disability_group_id = vespa_athletes.disability_type 
            WHERE 1";
```

Cseréld a záró sort (`WHERE 1";`) erre:

```php
            WHERE vespa_athletes.is_deleted=0";
```

(Az egész blokk így a `WHERE vespa_athletes.is_deleted=0` sorral záruljon.)

- [ ] **Step 4: `import_athletes.php` — dedup-ellenőrzés**

Keresd meg:

```php
            "SELECT * FROM vespa_athletes WHERE athlete_name=%s AND birth_place=%s AND birth_date=%s AND mothers_name=%s",
```

Cseréld erre:

```php
            "SELECT * FROM vespa_athletes WHERE is_deleted=0 AND athlete_name=%s AND birth_place=%s AND birth_date=%s AND mothers_name=%s",
```

- [ ] **Step 5: Szintaxis-ellenőrzés mind a 4 fájlra**

Run:
```bash
php -l templates/contest_view_entering.php
php -l templates/contest_view_save.php
php -l includes/Export/export_athletes.php
php -l templates/import_athletes.php
```
Expected: mindegyik `No syntax errors detected`.

- [ ] **Step 6: Ellenőrzés — nem szűrtük túl**

Run: `grep -rn "is_deleted" templates/contest_view_entering.php templates/contest_view_save.php includes/Export/export_athletes.php templates/import_athletes.php`
Expected: pontosan 4 találat (fájlonként 1).

Run: `grep -c "is_deleted" templates/results_dashboard.php templates/contest_view_racelist.php`
Expected: `0` mindkettőben (a történeti nézeteket nem szűrtük).

- [ ] **Step 7: Commit**

```bash
git add templates/contest_view_entering.php templates/contest_view_save.php includes/Export/export_athletes.php templates/import_athletes.php
git commit -m "feat: archivált (is_deleted) diákok kizárása az élő-diák listákból"
```

---

## Záró ellenőrzés (manuális, böngészőben)

- [ ] **Step 1: Teljes körű végigjátszás**

1. **Hozzáférés:** iskolaigazgatóként a Sportolók menü elérhető (a saját iskola diákjai).
2. **Archiválás:** testnevelő/igazgató egy saját diákot töröl → eltűnik a Sportolók listából.
3. **Nevezhető lista:** az archivált diák már nem jelenik meg a nevezésnél a „Nevezhető" oldalon.
4. **Előzmény:** ha az archivált diáknak volt eredménye/nevezése, az a verseny eredmény-/nevezési nézetében és exportban továbbra is látszik; a meglévő nevezései érintetlenek.
5. **Idegen iskola:** másik iskola diákjának törlési kísérlete (id-manipulációval) → „Jogosulatlan hozzáférés".
6. **Admin:** admin/versenykezelő továbbra is törölhet (most már soft delete-tel), és az audit log (`vitarex_log`) rögzíti.

- [ ] **Step 2: Az audit log ellenőrzése (opcionális)**

A `vitarex_log` táblában a törlés után megjelenik egy `datalist_delete` bejegyzés `mode: soft` móddal.

---

## Self-Review jegyzet

- **Spec-lefedettség:** iskolaigazgató jog (Task 2), DB migráció (Task 1), `$soft_delete`+`checkDelete`+`getFilters`+`addActionButtons` (Task 3), élő-diák lekérdezések szűrése (Task 4). A spec minden pontja le van fedve. A `results_dashboard.php` szándékosan a NEM-szűrt (történeti) csoportban van.
- **Konzisztencia:** az `is_deleted`/`deleted_at` oszlopnevek (Task 1) egyeznek a használati helyekkel (Task 3, 4); a `$soft_delete` a base `VESPA_Datalist::delete()` által várt property; a `vespa_get_my_school_id()` és a `VESPA_Roles` szerep-konstansok léteznek.
- **Tulajdonlás:** a `checkDelete` a valódi `$id`-vel tölti be a diákot és a `school_id`-t a saját iskolához hasonlítja; az `addActionButtons` a sor valódi id-jával ellenőriz.
