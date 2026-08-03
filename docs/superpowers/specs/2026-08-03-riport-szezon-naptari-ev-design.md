# Riport: szezon és naptári év külön kikapcsolható — tervezési dokumentum

Dátum: 2026-08-03

Forrás: felhasználói kérés — „a report generálásnál a szezon és a naptári évhez
lehetne olyan lehetőség hogy egyik év se, mert most összemossa a kettőt".

## Cél

A riport-felületen a **versenysorozat (tanév/szezon)** és a **naptári év** ma
nem független egymástól: a szezon kötelező, a naptári év pedig csak azon belül
szűkít. Emiatt tiszta naptári éves kimutatás nem készíthető, és a legenerált
XLSX-ből sem derül ki egyértelműen, melyik időszak-értelmezés volt érvényben.

A cél, hogy mindkét szűrő **külön-külön kikapcsolható** legyen, és a
kombinációjuk a fájl fejlécében is egyértelműen látszódjon.

## Hatókör

Az öt „tanév"-alapú riport:

| Riporttípus | Felületi név |
|---|---|
| `szezon_riport` | Szezon riport |
| `tanev_diakolimpia_diakok` | Adott tanévben FODISZ diákolimpián részt vett fogyatékkal élő diákok száma |
| `tanev_versenyen_indult_iskolak` | Adott tanévben versenyen indult iskolák száma |
| `tanev_diakolimpia_versenyszam` | Adott tanévben Fodisz diákolimpián versenyek száma |
| `tanev_diakolimpia_versenyszam_sportag` | Adott tanévben sportágankénti versenyek száma |

**Nem érintett** az a négy riport, amely `dateFrom`/`dateTo` intervallummal
dolgozik: `verseny_versenyszam`, `verseny_diak`, `legnepszerubb_sportag`,
`iskola_sportoltatott_diakok`. Ezek ága érintetlen marad — fontos, mert két
backend függvény *közösen* szolgálja ki az intervallumos és a tanév-alapú
riportokat.

## Jelenlegi állapot

### Felület — `templates/riports_dashboard.php`

- `getSeriesList()` (`:273-275`) a nyers szezonlistát adja vissza, **nincs
  benne 0 értékű „összes" elem**, ellentétben a többi legördülővel
  (`getStateList`, `getSchoolDistrictList`, `getInstitutionList`,
  `getDisabilityList`, `getSportList`), ahol van.
- `defaultRiportState.series` a `mounted()`-ben (`:204`) a **legutolsó** szezonra
  áll be.
- A **Naptári év** mező (`:121-130`) csak a `szezon_riport` esetén jelenik meg
  (`showedInputs.year`, `:316`). `getYearList()` (`:287-294`) a `0` (Összes) után
  2023-tól a jelenlegi év + 1-ig sorolja fel az éveket.
- A `getSubmitUri` (`:236-260`) csak a `szezon_riport` ágon küld `&year=`-t.

### Backend — `includes/Export/download_riports.php`

Négy függvény érintett (a dispatcher: `:18-33`):

| Függvény | Sor | Kiszolgált riporttípusok |
|---|---|---|
| `vespa_download_riport_szezon_riport()` | `:39` | `szezon_riport` |
| `vespa_download_riport_verseny_versenyszam()` | `:341` | `tanev_diakolimpia_versenyszam`, `tanev_diakolimpia_versenyszam_sportag` (+ az intervallumos `verseny_versenyszam`) |
| `vespa_download_riport_versenyen_resztvevo_iskolak_szama()` | `:497` | `tanev_versenyen_indult_iskolak` |
| `vespa_download_riport_tanev($type)` | `:771` | `tanev_diakolimpia_diakok` (+ az intervallumos `iskola_sportoltatott_diakok`) |

Jelenlegi viselkedés:

- `szezon_riport`: ha a `series` nem pozitív, a függvény **`die`-ol** (`:90`) —
  szezon nélkül nincs riport. A naptári év `AND YEAR(vc.start_at)=%d`-ként
  kerül rá a szezon-feltételre (`:152-155`), tehát mindig metszet.
- `tanev_diakolimpia_versenyszam`: `AND vc.contest_series=%d AND
  vc.contest_type IN (1,2,3)` (`:406`).
- `tanev_diakolimpia_versenyszam_sportag`: `AND (vc.contest_series=%d OR
  vc.contest_type=4)` (`:412`) — a **szabadidős versenyek szezontól függetlenül**
  benne vannak.
- `tanev_diakolimpia_diakok`: `AND vc.contest_series=%d` (`:835`).
- `tanev_versenyen_indult_iskolak`: `WHERE vc.contest_series = $seriesId`
  (`:553`) — **nyersen interpolált** GET paraméter, nem prepared.

## Design

### 1. Felület és viselkedés

**Versenysorozat** legördülő: új első elem `{series_id: 0, series_name: 'Összes
szezon (nincs szűrés)'}`, a többi „összes"-elem mintájára. Az alapértelmezés
**változatlanul a legutóbbi szezon** — aki eddig használta a felületet, ugyanazt
kapja, az „egyik sem" tudatos választás.

**Naptári év** legördülő: mind az öt riportnál megjelenik (eddig csak a
`szezon_riport`-nál). `0` = Összes, alapértelmezés `0`. A lista változatlan
(2023 – jelenlegi év + 1).

A négy lehetséges kombináció:

| Versenysorozat | Naptári év | Eredmény |
|---|---|---|
| konkrét szezon | Összes | a teljes szezon (a mai viselkedés) |
| konkrét szezon | konkrét év | a szezon adott naptári évbe eső fele (a mai viselkedés) |
| Összes szezon | konkrét év | **tiszta naptári év**, szezonhatároktól függetlenül |
| Összes szezon | Összes | **nincs időszakszűrés** — minden verseny |

A naptári év jelentése változatlan: a verseny **kezdőnapja** esik az adott évbe
(`YEAR(vc.start_at)`).

**XLSX fejléc:** minden érintett riport ugyanazt az „Időszak" feliratot kapja a
közös helpertől:

| Kombináció | Felirat |
|---|---|
| szezon | `2025/2026` |
| szezon + év | `2025/2026 — naptári év: 2025` |
| csak év | `Naptári év: 2025` |
| egyik sem | `Nincs időszakszűrés (összes verseny)` |

Ez a felirat szünteti meg az összemosást: a fájlból mindig visszaolvasható,
mi volt beállítva.

**Szélső eset:** a `tanev_diakolimpia_versenyszam_sportag` továbbra is
szezontól függetlenül beleszámolja a szabadidős (`contest_type=4`) versenyeket,
de a naptári év szűrés **ezekre is érvényes** — a `YEAR()` feltétel a teljes
szezon-kifejezésre `AND`-elődik, nem csak a diákolimpiai ágra.

### 2. Backend

#### Új fájl: `includes/Core/vespa.riport.periodus.php`

A `includes/Core/` alatti fájlokat a plugin automatikusan betölti
(`vitarex-vespa-plugin.php:51-60` könyvtár-bejárás), és a `Core` a
`$include_dirs` első eleme, tehát az `Export` előtt töltődik be.

Két **tiszta** (adatbázist nem érintő, ezért WordPress nélkül tesztelhető)
függvény, a `vespa.docs_access.php` / `vespa.filename.php` mintájára:

```php
/**
 * @param mixed $seriesId  GET-ből jövő nyers érték; csak a pozitív egész számít
 * @param mixed $year      GET-ből jövő nyers érték; csak a pozitív egész számít
 * @param bool  $szabadidosKivetel  ha true, a szezon-feltétel a szabadidős
 *                                  versenyeket szezontól függetlenül beengedi
 * @return array{sql: string, params: array}
 */
function vespa_riport_periodus_szuro($seriesId, $year, $szabadidosKivetel = false)
```

Viselkedés:

- `$seriesId > 0` esetén hozzáfűzi a ` AND vc.contest_series=%d` töredéket,
  illetve `$szabadidosKivetel === true` esetén a
  ` AND (vc.contest_series=%d OR vc.contest_type=4)` alakot, és a paramétert.
- `$year > 0` esetén hozzáfűzi a ` AND YEAR(vc.start_at)=%d` töredéket és a
  paramétert.
- Ha egyik sem pozitív: `['sql' => '', 'params' => []]`.
- A paraméterek sorrendje kötött: **előbb a szezon, utána az év** — a hívó ebben
  a sorrendben fűzi a saját `$params` tömbjéhez.
- A `vc` tábla-alias fix. Mind a négy érintett lekérdezés így aliasolja a
  `vespa_contests` táblát, ezért paraméterezni fölösleges lenne.

```php
/**
 * @param string|null $seriesName  a szezon neve, vagy null/'' ha nincs szezonszűrés
 * @param mixed       $year
 * @return string
 */
function vespa_riport_periodus_felirat($seriesName, $year)
```

A szezon nevét nem ez a függvény kérdezi le, így tiszta marad. A négy
visszaadott szöveget lásd a fenti táblázatban.

Mivel a szezon nevére mind a négy riportfüggvénynek szüksége van a fejléchez, a
lekérdezés egy harmadik, **nem tiszta** helperbe kerül ugyanebbe a fájlba —
`vespa_riport_szezon_neve($seriesId): string`, ami üres szöveget ad, ha nincs
szezonszűrés vagy a sorozat nem található. A `$wpdb`-t használja, ezért nincs
unit tesztje; a szerepe a négyszeres másolat elkerülése.

#### Prepared-statement burkoló

Ha se szezon, se naptári év, se megye-szűrés nincs kiválasztva, a `$params` tömb
**üresen maradhat**. A `$wpdb->prepare($sql)` placeholder nélküli lekérdezésre
`_doing_it_wrong` figyelmeztetést vált ki. `WP_DEBUG_DISPLAY` mellett ez a
szöveg **beleíródik a válasz törzsébe**, és mivel a riport bináris XLSX-et ír a
`php://output`-ra, a letöltött fájl megsérül. Pontosan ez ellen véd már ma is a
`download_riports.php:51-52` kommentje a hiányzó GET paraméterek `isset`-elésénél.
Ez a helyzet ma a tanév-riportokban nem állhat elő, mert a szezon kötelező — a
változtatás után viszont igen.

Ugyanez a hiba **ma is jelen van** a `vespa_download_riport_tanev()` második
lekérdezésében (`:879`, az összes intézményt listázó `$allSchool`): ha se
tankerület, se megye nincs szűrve, a `$params` üres. Mivel a burkoló amúgy is
bekerül, ez a call-site is átáll rá.

Ezért bekerül egy harmadik helper ugyanabba a fájlba:

```php
function vespa_riport_get_results($sql, $params)
{
    global $wpdb;
    return $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql);
}
```

Ez nem tiszta függvény (a `$wpdb`-t használja), ezért nem unit-tesztelt; a
szerepe kizárólag az, hogy egyetlen call-site se maradjon ki. Az érintett
`$wpdb->get_results($wpdb->prepare($sql, ...$params))` hívások
(`:163`, `:445`, `:572`, `:866`, `:879`) erre cserélődnek. A `:697-698` és a
`:1016` hívások az intervallumos riportokhoz tartoznak, ahol a `$params` sosem
üres — ezek érintetlenek maradnak. A `:277` hívás a `szezon_riport`-on belüli
`$sqlContests` lekérdezés; ez NEM intervallumos riport, és szintén a helperre
áll át.

#### A négy riportfüggvény módosítása

Mindegyik a saját, kézzel írt szezon-feltétele helyett a helpert hívja, és a
fejlécbe a helper feliratát írja.

- **`vespa_download_riport_szezon_riport()`** — a `:76-90` blokk `else die;`
  ága megszűnik. A fejléc-írás (Tanév/szezon sor, Szűrés sor) kikerül a
  feltételes blokkból, hogy szezon nélkül is lefusson. A `:152-155` külön
  év-feltétel helyére a helper `sql`/`params` kerül.
- **`vespa_download_riport_verseny_versenyszam()`** — a `:404-415` `if/else if`
  ágaiban a `contest_series` feltételek a helperre cserélődnek
  (`$szabadidosKivetel = true` a `..._sportag` típusnál). A
  `contest_type IN (1,2,3)` megkötés a `tanev_diakolimpia_versenyszam` ágon
  **változatlanul marad**, mert az nem időszak-, hanem versenytípus-szűrés. Az
  `else` ág (intervallumos `verseny_versenyszam`) érintetlen.
- **`vespa_download_riport_versenyen_resztvevo_iskolak_szama()`** — a `:553`
  nyers `WHERE vc.contest_series = $seriesId` helyére `WHERE 1` + a helper
  töredéke kerül, prepared paraméterrel.
- **`vespa_download_riport_tanev($type)`** — a `:833-839` `tanev_diakolimpia_diakok`
  ága a helpert hívja; az `else` ág (intervallumos `iskola_sportoltatott_diakok`)
  érintetlen. A `:812-818` fejléc-blokk a helper feliratát írja.

**Nem része a hatókörnek** a `$_GET` paraméterek hiányzó `isset`-védelme a
`szezon_riport`-on kívüli függvényekben, sem a `verseny_diak` / `legnepszerubb_sportagak`
függvények nyers paraméterolvasása. Ezek meglévő, a mostani változtatástól
független problémák.

### 3. Tesztelés

Új `tests/test-riport-periodus.php` a meglévő tesztek mintájára (`allit()`
segédfüggvény, `require_once` a tesztelt fájlra, WordPress nélkül futtatható:
`php tests/test-riport-periodus.php`).

Lefedendő esetek:

- `vespa_riport_periodus_szuro()` mind a négy kombinációja — a visszaadott SQL
  töredék és a `params` tömb **tartalma és sorrendje**.
- A `$szabadidosKivetel = true` variáns mindkét szezon-állapotban (van szezon /
  nincs szezon).
- Nem numerikus és negatív bemenetek (`''`, `'abc'`, `-1`, `null`) — ezek
  „nincs szűrés"-ként viselkedjenek, ne kerüljön töredék az SQL-be.
- `vespa_riport_periodus_felirat()` mind a négy kimenete.

**A riportok tényleges XLSX-kimenetét kézzel kell ellenőrizni** — a projektben
nincs helyi WordPress fejlesztői környezet, a plugin `build.sh`-val ZIP-be
csomagolva megy éles szerverre. Manuális ellenőrzési lista: mind az öt riport, a
négy időszak-kombinációval, plusz annak igazolása, hogy a négy intervallumos
riport kimenete változatlan.

## Kockázatok

- **Teljes adathalmaz lekérése.** Az „Összes szezon + Összes év" kombináció
  szűrő nélküli lekérdezést jelent, ami nagy adatbázison lassú lehet. Mivel az
  alapértelmezés a legutóbbi szezon marad, ez csak tudatos választásból
  következhet be; külön korlátozást nem építünk be (YAGNI).
- **Közös backend függvények.** A `verseny_versenyszam` és a `tanev` függvény
  intervallumos riportokat is kiszolgál. A módosítás kizárólag a tanév-alapú
  ágakat érinti; a kézi ellenőrzésnek az intervallumos riportok változatlanságát
  is igazolnia kell.
