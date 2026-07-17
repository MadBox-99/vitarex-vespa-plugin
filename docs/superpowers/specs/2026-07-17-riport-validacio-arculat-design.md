# 1. csomag: Szezon riport javítás + kötelező mezők + arculat — tervezési dokumentum

Dátum: 2026-07-17

Forrás: „Vespa fejlesztés, 2026.07.17..md" — az **A5**, **A3** és **A6** tétel.

## Cél

Három, egymástól független, kis hatókörű tétel egy csomagban:

- **A5** — a szezon riport szűrési logikájának javítása, hogy az országos és a
  vármegyei versenyek adatait helyesen különítse el és jelenítse meg.
- **A3** — a testnevelők regisztrációs adatlapjának bővítése telefonszám
  mezővel; a nevezésnél a kísérő e-mail-címének és telefonszámának valódi
  (formátum-szintű) validálása.
- **A6** — egyedi VESPA háttér, a FODISZ logó elhelyezése a felület megfelelő
  pontjain, a felület vizuális igazítása az arculathoz.

## A teljes fejlesztési lista hatókör-bontása

A 2026.07.17-i lista 7 tétele nem egy projekt. A bontás (a brainstormingban
egyeztetve):

| Csomag | Tartalom | Állapot |
|---|---|---|
| **1. (ez a spec)** | A5 + A3 + A6 | tervezés kész |
| 2. | A1 — Excel-alapú tömeges diákimport | később, saját spec |
| 3. | A2 — bővített szűrés az eredménylistákban | később, saját spec |
| 4. | A4 — szabadidősport külső regisztráció + adatvédelmi elkülönítés | később, saját spec (a legnagyobb) |
| külön repó | B1 — FODISZ honlap megtekintés-kimutatás | később, **külön plugin** |

**A B1 nem ebben a repóban valósítható meg.** A FODISZ honlap külön WordPress
telepítés, külön adatbázissal (`fodisz_hu`, `wpkt_` prefix, The Events Calendar);
erre ma csak a `database/trigger.sql` cross-DB triggerei és a
`includes/Api/fodisz_api.php` kimenő feed-je hivatkozik. Ez a plugin nem kezel WP
bejegyzéseket, csak saját `vespa_*` táblákat, és a
`includes/Admin/login.customiser.php:21-41` minden publikus kérést a wp-adminra
irányít — ezért a plugin a FODISZ oldalra nem telepíthető, kilőné a nyilvános
honlapot. A megtekintés-számlálás csak ott futhat, ahol a bejegyzés
renderelődik. A kimutatás megjelenítése viszont jöhet VESPA admin menübe
(a két DB egy szerveren van, a trigger már ma is cross-DB olvas). Ezt a B1 saját
körében kell véglegesíteni.

## Háttér / jelenlegi állapot

### A5 — szezon riport

- `includes/Export/download_riports.php:39-252` — `vespa_download_riport_szezon_riport()`.
- A riport-felület (`templates/riports_dashboard.php:56-69`) `filter` GET
  paramétere **4 értéket** vehet fel: `'all'` (Összes), `'country'` (Csak
  országos), `0` (Összes megye — a `:246-248` szúrja be a lista élére), `>0`
  (konkrét `state_id`).
- A backend (`:95-100`) ebből **csak kettőt kezel**: `'country'` és `>0`.
  Hiányzik az `'all'` és a `0` ág → ezekben az esetekben **semmilyen
  `contest_type` feltétel nem kerül a lekérdezésbe**, így a regionális (2) és a
  szabadidős (4) versenyek is beleszámítanak. Következmény: az „Összes megye"
  pontosan ugyanazt adja, mint az „Összes", és a `:143` összesen nem egyezik a
  `:155-163` bontás összegével.
- A hiányzó ág a többi riportban **megvan** — `:507-509`
  (`versenyen_resztvevo_iskolak`), `:612-614` (`verseny_diak`), `:382-388`
  (`verseny_versenyszam`). A szezon riport és a `legnepszerubb_sportagak`
  (`:915-920`) az a kettő, ahonnan kimaradt.
- `:122` — `GROUP BY va.athlete_id` sportolónként **egyetlen sorra** redukál, a
  nem aggregált `vc.contest_type` / `vc.state_id` értéket pedig a MySQL
  nemdeterminisztikusan választja a csoportból (és csak `ONLY_FULL_GROUP_BY`
  nélkül fut le). Aki megyein **és** országoson is indult, önkényesen az egyik
  vödörbe kerül. Ugyanez érinti az iskolaszámokat (`:165-219`).
- `:165` — **ugyanazt a lekérdezést futtatja le még egyszer** (azonos `$sql`,
  azonos `$params`, mint `:124`), az eredmény szükségszerűen azonos.
- `:221` — `str_replace('GROUP BY va.athlete_id', 'GROUP BY vc.contest_id', $sql)`
  írja át a lekérdezést versenyszámlálásra. Ez **némán eltörik**, ha a GROUP BY
  szövege megváltozik.
- `:51-56` — nyers `$_GET` olvasás `isset` nélkül → PHP warning hiányzó
  paraméternél (a `year` az egyetlen kivétel, `:56`).
- `:59-69` — a „Szűrés:" fejlécsor számítása **ki van kommentezve**, így a
  generált XLSX-ből nem derül ki, milyen szűréssel készült. Más riportokban ez
  megvan (`:326-336`, `:579-582`).
- `:189`, `:203`, `:217` — mindhárom iskolaszám-blokk címkéje ugyanaz
  (`'Iskolák száma megye mind:'`), miközben a `B` oszlop Országos/Regionális/
  Megyei — copy-paste maradvány.
- `:53` + `:102-105` — az `institutionId` GET paramétert a felület elküldi
  (`riports_dashboard.php:235`) és a mezőt meg is jeleníti (`:296`), de a
  függvény **soha nem olvassa ki** → az intézmény-választás némán hatástalan.
- `contest_type` a pluginban **sehol nincs konstansként** — ~40 helyen magic
  number. A `includes/Core/vespa.model.contest.php` `VespaContest` osztálya nem
  tartalmaz típus-konstanst.

### A3 — kötelező mezők

**Kísérő adatai** (`includes/Ajax/ajax.save_escorts.php:57-142`):

- A kötelezőség **már ma működik**: `:83-103` mind a 4 mezőt (név, mobil,
  e-mail, allergia) megköveteli, ha a sorból bármelyik ki van töltve; `:128-134`
  legalább 1 kísérőt és 1 gépkocsivezetőt ír elő. A teljesen üres sor némán
  kimarad (5 fix sorból elég egyet kitölteni).
- **A formátum-ellenőrzés hiányzik**: az e-mail sehol nem megy át
  `is_email()`-en a szerveren, telefonszám-validáció az egész pluginban nincs.
  A `"asdf"` ma átmegy e-mail gyanánt.
- `:92-95` — nincs `sanitize_text_field`. Az adat `serialize()`-zel a
  `vespa_contests_escorts.escort_data` oszlopba kerül (`:170-188`) és
  visszarenderelődik.
- A hibakulcs formátuma `'"kiserok_nev['.$key.']"'` — **az idézőjelek a kulcs
  részei**, mert a `js/vespa-ajax-form.js:76-97` ebből épít
  `[name="kiserok_nev[0]"]` szelektort. Ez a konvenció kötelező, különben a hiba
  nem jelenik meg a mezőn.
- A kísérő-inputokon (`templates/contest_view_entering.php:389-404`) nincs
  `required`, nincs `type="email"`, és nincs kliens oldali validáció.

**Testnevelő telefonszáma** (`includes/Admin/user.fields.php`):

- **Nincs saját regisztrációs adatlap** — a plugin a WP natív `user-new.php` /
  `profile.php` űrlapját bővíti a `show_user_profile` / `edit_user_profile` /
  `user_new_form` hookokon.
- A plugin által kezelt user meta kulcsok teljes listája: **`school_id`,
  `state_id`, `school_district_id`**. **Telefonszám mező nem létezik sehol.**
- `:301-324` — `validate_extra()` a `user_profile_update_errors` hookon,
  szerepkör-függő kötelezőséggel. **`$_POST['role']`-ra épül** — de a `role`
  legördülő csak a `user-new.php`-n és a `user-edit.php`-n létezik. Saját profil
  mentésekor (`profile.php`) nincs `$_POST['role']` → a validáció némán kimarad
  és PHP warningot dob. Ez a meglévő `school_id` ellenőrzést is érinti.
- `:330-385` — `handleFields()` JS: szerepkör szerint mutogatja a mező-blokkokat
  (`#school`, `#state`, `#district`).

### A6 — arculat

- A felület **natív wp-admin** (`includes/Admin/menu.masterdata.php`: 6
  `add_menu_page` + 11 `add_submenu_page`), a tartalom a `templates/` sablonokból.
  Saját frontend nincs.
- `css/vespa-admin.css:164` — `/* arculat */` szekció: a `#e12d3b` márkapiros
  **ötször hardkódolva** (`:170, :178, :185, :190, :345`). **Nulla CSS változó**
  a projektben (se `:root`, se `--custom-property`).
- `css/vespa-admin.css:46-49` — `#wp-admin-bar-wp-logo` és `#wpfooter`
  **el van rejtve** → két szabad hely a márkázásnak.
- `images/FODISZ_fekvo_logo_color.jpg` (57,9 KB) — **létezik, de kizárólag PDF
  fejlécekben** használt (`includes/Export/download_contest_docs.php:97, :328`).
  A wp-adminban és a loginon sehol.
- `css/vespa-login.css` (26 sor) — csak a `.login h1 a` háttérképét állítja
  (`images/vespa_logo.png`, 360×260px) és elrejti a `#backtoblog`-ot.
  **Nincs `body` háttér.**
- **VESPA háttérkép nincs a repóban.** Rögzített színpaletta sincs.
- `includes/Admin/plugin.assets.php:7` — egyetlen hook, `admin_enqueue_scripts`.
  Frontend enqueue nincs (a frontend úgyis redirectel). A `vespa-admin.css`
  (`:18`) **előbb** töltődik be, mint a `bootstrap.min.css` (`:33`) → a Bootstrap
  felülírja azonos specificitásnál.

## Döntések (a brainstormingból)

1. **Megközelítés: „célzott alap"** — három apró, megosztott darabot vezetünk be
   (contest_type konstansok, validációs helperek, CSS paletta), de **csak ott
   használjuk, amit ebben a csomagban úgyis megfogunk**. A meglévő ~40
   `contest_type` magic numbert **nem írjuk át**. Indok: a helperek a 2. (A1) és
   3. (A2) csomaghoz úgyis kellenek, és most, ugyanannak a kódnak a
   szerkesztésekor gyakorlatilag ingyen vannak.
2. **A5 — „Összes" jelentése: `contest_type IN (1,2,3)`** (országos +
   regionális + megyei). A szabadidős (4) kimarad. Indok: a riport pontosan ezt
   a három vödröt jeleníti meg (`:155-163`), tehát amit kimutat, azt összegezze.
   (Eltér a `verseny_diak` riporttól, ami `IN (1,3)` — ott nincs regionális sor.)
3. **A5 — „Összes megye" (`filter == 0`): `contest_type = 3`.**
4. **A5 — dupla induló:** aki ugyanabban a szezonban több típuson is indult,
   **minden érintett vödörbe beleszámít**, az összesenben viszont **egyszer**.
   Következmény: a vödrök összege jogosan **lehet több** az összesennél; ezt a
   riportban jelezni kell.
5. **A3 — kísérő:** a valós hiány a **formátum-validáció**, nem a kötelezőség.
   Az üres sorok maradnak megengedettek (5 fix sor, elég egy).
6. **A3 — telefon mező: csak a testnevelőnek, kötelezően.**
7. **A3 — meglévő testnevelők:** **csak mentéskor kényszerítünk**. Nincs
   migráció, nincs belépéskori kényszer, nincs kizárás. A telefon nélküli user
   addig marad úgy, amíg valaki nem szerkeszti a profilját.
8. **A6 — FODISZ logó három helyen:** admin menü teteje + lábléc + login
   képernyő. **Az admin bar logó elhagyva** — az admin menü tetejétől kb. 40
   pixelre lenne, két logó közvetlenül egymás alatt.
9. **A6 — VESPA háttér:** login **és** wp-admin. Képfájl nincs, ezért CSS-ből
   generált, a meglévő `#e12d3b`-ből származtatott megjelenés. A wp-adminban ez
   **visszafogott árnyalat**, nem minta — a tartalom táblázat/űrlap/riport,
   erős háttér olvashatatlanná tenné.
10. **A6 — arculati anyagok:** nincsenek; a meglévőkből dolgozunk. Minden
    `:root` változóra kötve, hogy a valódi anyag megérkezésekor egy blokkban
    cserélhető legyen.

## Komponensek

### 0. Közös alap

#### 0.1 `VespaContestType` konstansok — `includes/Core/vespa.model.contest.php`

A meglévő `VespaContest` osztály mellé, ugyanabba a fájlba:

```php
class VespaContestType {
    const ORSZAGOS   = 1;
    const REGIONALIS = 2;
    const MEGYEI     = 3;
    const SZABADIDOS = 4;
}
```

A fájl a `Core` könyvtárban van, amit a `vitarex-vespa-plugin.php:51` **elsőként**
tölt be — így az `Export` és `Ajax` könyvtárból elérhető.

**Használat ebben a csomagban: kizárólag a szezon riportban.** A többi ~40
előfordulás (`contest_list.php`, `datalist.contests.php`, `ajax.contest_races.php`
stb.) **érintetlen marad**.

#### 0.2 Validációs helperek — `includes/Core/functions.php`

```php
function vespa_validate_email($value)  // WP is_email()-re épül
function vespa_validate_phone($value)  // megengedő magyar minta
```

A `vespa_validate_phone()` **szándékosan megengedő**. Konkrét szabály:

1. a szóköz, kötőjel, zárójel, pont és a `/` eltávolítása;
2. az eredmény elfogadható, ha `+` előjellel vagy számjeggyel kezdődik, és a
   maradék **csak számjegy**;
3. a számjegyek darabszáma **7 és 15 között** (a 15 az E.164 felső korlátja; a
   7 megenged egy rövid vezetékes számot körzet nélkül).

Így átmegy: `+36 30 123 4567`, `06-30/123-4567`, `0630 1234567`, `+43 664 …`
(külföldi kísérő). Elbukik: `"asdf"`, `"12"`, `"telefon: kérdezd Marit"`.

A cél a nyilvánvaló szemét kiszűrése, **nem** a formátum-rendészet — a szigorú
magyar minta valós, helyes számokat utasítana el.

Mindkettő üres értékre `false`-t ad; az „üres-e" és a „jó formátumú-e" kérdést a
hívó külön kezeli (a kísérőnél az üres sor legális, a testnevelő telefonjánál nem).

#### 0.3 CSS paletta — `css/vespa-admin.css`

`:root` blokk a fájl tetejére, a meglévő `#e12d3b`-ből származtatva:

```css
:root {
    --vespa-brand: #e12d3b;         /* a meglévő márkapiros, változatlanul */
    --vespa-brand-dark: #b02430;    /* ~20%-kal sötétebb, hover/aktív állapothoz */
    --vespa-dark: #1d2327;          /* a meglévő WP-menü sötét, változatlanul */
    --vespa-bg: #f0f0f1;            /* a WP alap admin háttér, kiindulásnak */
    --vespa-bg-image: none;         /* lásd 3.2 — CSS gradiens, url()-re cserélhető */
}
```

Az öt hardkódolt `#e12d3b` (`:170, :178, :185, :190, :345`) és a `#1d2327`
(`:190`) változóra cserélve. **Viselkedésváltozás nincs** — a `--vespa-brand`,
`--vespa-dark` és `--vespa-bg` értéke azonos a mai megjelenéssel; a
`--vespa-brand-dark` és a `--vespa-bg-image` új, csak a 3.2 pont használja.
A fenti értékek kiindulópontok, a megvalósításkor finomhangolhatók — a lényeg,
hogy **egy blokkban** legyenek.

> **Betöltési sorrend:** a `bootstrap.min.css` a `vespa-admin.css` **után**
> töltődik (`plugin.assets.php:18` vs `:33`), így azonos specificitásnál a
> Bootstrap nyer. Az új szabályoknál ezzel számolni kell (elég specifikus
> szelektor, vagy a sorrend megfordítása — utóbbi a meglévő megjelenést
> megváltoztathatja, ezért **nem** nyúlunk hozzá).

### 1. A5 — szezon riport — `includes/Export/download_riports.php:39-252`

#### 1.1 A szűrő négy ága (`:95-100`)

```php
if ($filter === 'country') {
    $sql .= " AND vc.contest_type = %d";
    $params[] = VespaContestType::ORSZAGOS;                 // 1
} elseif (is_numeric($filter) && $filter > 0) {
    $sql .= " AND vc.contest_type = %d AND vc.state_id = %d";
    $params[] = VespaContestType::MEGYEI;                   // 3
    $params[] = intval($filter);
} elseif (is_numeric($filter) && intval($filter) === 0) {   // "Összes megye"
    $sql .= " AND vc.contest_type = %d";
    $params[] = VespaContestType::MEGYEI;                   // 3
} else {                                                     // 'all'
    $sql .= " AND vc.contest_type IN (%d,%d,%d)";
    $params[] = VespaContestType::ORSZAGOS;
    $params[] = VespaContestType::REGIONALIS;
    $params[] = VespaContestType::MEGYEI;
}
```

Az ágak sorrendje kötött: a `0` ellenőrzésnek a `>0` **után** kell állnia.
A `===` a string-összehasonlításnál szándékos (`$_GET` mindig stringet ad).

#### 1.2 GROUP BY (`:122`)

```php
$sql .= " GROUP BY va.athlete_id, vc.contest_type";
```

Ezzel soronként egy **(diák, versenytípus)** pár keletkezik. Következmények:

| Riport-elem | Hely | Változás |
|---|---|---|
| Vödrök (országos/regionális/megyei) | `:155-163` | **Determinisztikus lesz**, a kód változatlan (`array_filter` a `contest_type`-ra). |
| „Fogyatékkal élő diák össz" | `:143` | `count($data)` → **egyedi `athlete_id` számolása**, különben a többtípusú diák duplán számít. |
| Nemek szerinti bontás | `:126-131` | Ugyanígy: `athlete_id` szerinti egyedivé tétel **előbb**, aztán nem szerinti számlálás. |
| Iskolaszám össz | `:167-176` | `array_unique($iskArr)` már ma dedupol `institution_id`-re → **változatlanul jó**. |
| Iskolaszám típusonként | `:179-219` | Típusra szűr, majd egyedi `institution_id` → **most már determinisztikus**. |
| Versenyszám (`:221-240`) | `:221` | **A `str_replace` keresési mintáját frissíteni kell** (lásd 1.3). |

A `riportPartDiakok()` (`:254-284`) **nem módosul**: egy vödrön belül minden
diák pontosan egyszer szerepel, a nem és fogyatékossági csoport szerinti
számlálás helyes marad.

#### 1.3 A `str_replace` csapda (`:221`)

```php
$sqlContests = str_replace(
    'GROUP BY va.athlete_id, vc.contest_type',   // az új GROUP BY-jal egyezően
    'GROUP BY vc.contest_id',
    $sql
);
```

Ha ez elmarad, a `str_replace` nem talál egyezést, a `$sqlContests` a diák
szerinti GROUP BY-jal fut, és a „Diákolimpia versenyek száma" **némán rossz
értéket ad** — kivétel és hibaüzenet nélkül. A megvalósítási tervnek ezt
külön ellenőriznie kell.

#### 1.4 A redundáns lekérdezés törlése (`:165`)

A `:165` sor (`$data = $wpdb->get_results($wpdb->prepare($sql, ...$params));`)
**törlendő** — azonos `$sql`-lel és `$params`-szal fut, mint a `:124`, az
eredmény szükségszerűen azonos. A `:167`-től a `:124`-es `$data` használható.

#### 1.5 `institutionId` szűrő bekötése (`:102-105` mellé)

```php
if (is_numeric($institutionId) && $institutionId > 0) {
    $sql .= " AND vi.institution_id = %d";
    $params[] = intval($institutionId);
}
```

A paramétert a felület már ma elküldi (`riports_dashboard.php:235`) és a mezőt
meg is jeleníti (`:296`) — csak a backend nem olvasta ki. Felületi változás
nem szükséges.

#### 1.6 Paraméter-olvasás (`:51-56`)

Mind az öt nyers `$_GET` olvasás `isset`-tel védve (a `year` mintájára), hogy
hiányzó paraméternél ne dobjon PHP warningot a letöltési válaszba (a warning a
binárisan írt XLSX-et is elronthatja).

#### 1.7 Kozmetika

- **„Szűrés:" fejlécsor** — a `:59-69` kikommentezett logika visszaírása a
  `VespaContestType` konstansokkal és mind a négy ágra (`all` / `country` /
  `0` / `>0`), majd kiírás a `:71-80`-as fejlécbe. Enélkül a generált XLSX-ből
  nem derül ki, milyen szűréssel készült.
- **Iskolaszám-címkék** — `:189`, `:203`, `:217`: az azonos
  `'Iskolák száma megye mind:'` helyett `'Iskolák száma országos:'` /
  `'... regionális:'` / `'... megyei:'`.
- **Átfedés-megjegyzés** — a fogyatékossági csoport bontás (`:148-163`) fölé
  egy sor, ami rögzíti, hogy a vödrök összege több lehet az összesennél, mert a
  több típuson induló diák minden érintett vödörben szerepel (4. döntés).
- A `riportPartDiakok()` `$megyei` lokális változóneve (`:255`) → semleges név
  (`$rows`), mert országos/regionális bontásnál is ez fut. Funkcionálisan nem
  hiba, de félrevezető.

### 2. A3 — kötelező mezők

#### 2.1 Kísérő formátum-validáció — `includes/Ajax/ajax.save_escorts.php:82-126`

A meglévő „mind a 4 mező kell" ág (`:83-97`) **mellé**, a `$has_escort = true`
ágon belül:

```php
if (!vespa_validate_email($_POST['kiserok_email'][$key])) {
    $errors_kiserok['"kiserok_email['.$key.']"'] = 'Érvénytelen e-mail cím';
}
if (!vespa_validate_phone($_POST['kiserok_mobil'][$key])) {
    $errors_kiserok['"kiserok_mobil['.$key.']"'] = 'Érvénytelen telefonszám';
}
```

Ugyanez a gépkocsivezetőkre (`:105-126`), `gepkocsivezeto_email` /
`gepkocsivezeto_mobil` kulccsal.

- **A hibakulcs formátuma kötött**: `'"mezőnév[index]"'`, az idézőjelek a kulcs
  részei — a `js/vespa-ajax-form.js:76-97` ebből épít `[name="..."]` szelektort.
- A `$has_escort` / `$has_driver` **így is `true` marad** hibás formátumnál. Ez
  szándékos: a `:128-134` „legalább egy kísérőt" hibája `empty($errors_*)`-ra
  van kötve, tehát a formátum-hiba nem íródik felül egy félrevezető
  „legalább egy…" üzenettel.
- A `:140` már ma minden hibánál `wp_send_json_error`-ral megszakít, mentés
  előtt — a hibás sor nem kerül a DB-be.

#### 2.2 Sanitizálás — `ajax.save_escorts.php:92-95` és `:115-118`

A négy mező `sanitize_text_field()`-en át kerül a `$data`-ba. Ma tisztítás
nélkül megy a `serialize()`-be és onnan vissza a felületre.

#### 2.3 Kliens oldal — `templates/contest_view_entering.php:389-440`

- `kiserok_email[]` és `gepkocsivezeto_email[]` → **`type="email"`**
- `kiserok_mobil[]` és `gepkocsivezeto_mobil[]` → **`type="tel"`**

**`required` NEM kerül rájuk.** Az 5 fix sorból elég egyet kitölteni; a
`required` a négy üres sor miatt **blokkolná a beküldést**, azaz a működő
folyamatot törné el. A sorok feltételesen kötelezők — ezt a HTML5 nem tudja
kifejezni, a kényszerítés marad szerver oldalon (ez a projekt bevett mintája is).

A `type="email"` üres, nem-`required` mezőn nem panaszkodik, kitöltöttön viszont
formátumot ellenőriz. A `type="tel"` formátumot **nem** validál (a HTML
specifikáció szerint), de mobilon számbillentyűzetet ad — a telefon
ellenőrzése kizárólag szerver oldali.

#### 2.4 Telefon mező — `includes/Admin/user.fields.php`

- **Megjelenítés**: új blokk a meglévő iskola/megye/tankerület mintájára
  (`:34-99`), de **egyszerű szöveges input**, autocomplete nélkül. Wrapper id:
  `#phone` (a `handleFields()` ezt mutogatja).
- **Meta kulcs: `phone`** — prefix nélkül, a meglévő `school_id` / `state_id` /
  `school_district_id` mintájával összhangban. *(A `vespa_phone`
  ütközésbiztosabb lenne más pluginokkal szemben, de kilógna a mintából; a
  konzisztencia mellett döntöttünk.)*
- **Mentés**: a `vespa_save_extra_user_profile_fields()`-be (`:105-122`), a
  meglévő jogosultság-ellenőrzések mögé, `sanitize_text_field()`-del.
- **JS**: a `handleFields()` (`:344-382`) a `#phone` blokkot a `testnevelo`
  szerepkörnél mutatja, egyébként rejti és üríti — a `schoolRoles` /
  `stateRoles` / `district` tömbök mintájára.

#### 2.5 A `validate_extra()` szerepkör-forrása — `user.fields.php:301-324`

**A `$_POST['role']` nem megbízható.** A `role` legördülő csak a
`user-new.php`-n és a `user-edit.php`-n létezik; saját profil mentésekor
(`profile.php`) nincs a POST-ban → a validáció némán kimarad, és PHP warningot
dob. Ez a **meglévő** `school_id` / `state_id` / `school_district_id`
ellenőrzést is érinti.

**Figyelem — a `$user` paraméter nem `WP_User`.** A WP core az
`user_profile_update_errors` hookot az `edit_user()`-ből
(`wp-admin/includes/user.php`) hívja, és a `$user` ott egy **`stdClass`**,
amit a POST-ból épít. Nincs rajta `->roles` tömb; a `->role` mező pedig **csak
akkor** kap értéket, ha `$_POST['role']` létezik — vagyis pont abban az esetben
nem, amiért az egészet javítjuk. `$update === true` esetén viszont a `->ID`
mindig ki van töltve, és ebből a valódi szerepkör lekérdezhető:

```php
$role = '';
if (isset($_POST['role']) && $_POST['role'] !== '') {
    $role = $_POST['role'];                      // user-new.php / user-edit.php
} elseif (!empty($user->ID)) {
    $u = get_userdata($user->ID);                // profile.php (saját profil)
    if ($u && !empty($u->roles)) {
        $role = $u->roles[0];
    }
}
```

A négy ellenőrzés (a meglévő három + az új telefon) ezt a `$role`-t használja.

Új ág:

```php
if ($role === VESPA_Roles::TESTNEVELO &&
    !(isset($_POST['phone']) && vespa_validate_phone($_POST['phone']))) {
    $errors->add('phone', "<strong>Hiba</strong>: Telefonszám megadása kötelező.");
}
```

**Ez a 7. döntés („csak mentéskor kényszerít") teljesítése**: a telefon nélküli
meglévő testnevelő érintetlen marad, de amint bárki (ő maga vagy egy admin)
menti a profilját, meg kell adnia a számot. A `$_POST['role']`-ra hagyatkozva
ez pont a leggyakoribb esetben (a testnevelő maga tölti ki) nem érvényesülne.

### 3. A6 — arculat

#### 3.1 FODISZ logó — három hely

| Hely | Megvalósítás | Megjegyzés |
|---|---|---|
| **Admin menü teteje** | `css/vespa-admin.css` — `#adminmenuwrap::before`, `background-image: url(../images/FODISZ_fekvo_logo_color.jpg)` | Tiszta CSS, PHP hook nélkül. Az összecsukott menüt (`.folded`) külön kezelni kell (kisebb méret vagy elrejtés). |
| **Lábléc** | `admin_footer_text` filter, új kód a `includes/Admin/plugin.assets.php`-ba | A `#wpfooter` ma rejtve (`vespa-admin.css:46-49`) → a rejtés feloldása FODISZ logóra + `VESPA <verzió>` szövegre. A verzió a `VITAREX_VESPA_VERSION` konstansból. |
| **Login képernyő** | `css/vespa-login.css` — a meglévő `.login h1 a` (VESPA logó) **alá** | A `#backtoblog` rejtése marad. |

**Az admin bar logó elhagyva** (8. döntés) — a `#wp-admin-bar-wp-logo` rejtése
(`vespa-admin.css:46`) **változatlan marad**.

#### 3.2 VESPA háttér

- **Login** (`css/vespa-login.css`): `body.login` háttere — lágy, márkaszínű
  gradiens a `--vespa-brand`-ből. Itt lehet határozottabb, nincs mit olvasni
  rajta.
- **wp-admin** (`css/vespa-admin.css`): `#wpcontent` háttere — **visszafogott
  árnyalat** (9. döntés). A tartalom táblázat, űrlap és riport; erős minta
  olvashatatlanná tenné. A `--vespa-bg` / `--vespa-bg-image` változókra kötve.

A `--vespa-bg-image` CSS gradienst tartalmaz, de **`url()`-re cserélhető**
anélkül, hogy a CSS többi részéhez hozzá kellene nyúlni — amikor megjön a
tényleges arculati anyag, ez az egy blokk módosul.

#### 3.3 Fájlméret

A `FODISZ_fekvo_logo_color.jpg` 57,9 KB, és mostantól **minden admin
oldalbetöltésnél** letöltődik (ma csak PDF-generáláskor olvassa a szerver).
Ha a megvalósításkor észrevehető lassulást okoz, a fejlécbe/láblécbe szánt
változatot át kell méretezni — a terv ezt külön lépésként rögzítse.

## Hibakezelés / biztonság

- **A5**: minden új szűrőérték `$wpdb->prepare()` `%d` placeholderen megy át;
  a `filter` sosem kerül közvetlenül a SQL-be. Az `isset`-védett paraméter-
  olvasás megakadályozza, hogy PHP warning kerüljön a bináris XLSX válaszba.
- **A3 / kísérő**: a validáció **szerver oldali és megkerülhetetlen**; a
  kliens oldali `type="email"` kényelmi funkció. A `sanitize_text_field()` XSS
  ellen véd a szerializált `escort_data` visszarenderelésekor. A meglévő
  jogosultság-ellenőrzés (`:67-70`: csak TESTNEVELO, csak a saját iskolájára)
  változatlan.
- **A3 / telefon**: a `vespa_save_extra_user_profile_fields()` meglévő
  `current_user_can('edit_user', $user_id)` ellenőrzése mögé kerül a mentés.
- **A6**: csak CSS és egy `admin_footer_text` filter — nincs adatkezelési vagy
  jogosultsági hatása.

**Ismert, e csomagon kívül hagyott biztonsági hiányosságok** (a 2-4. csomag
vagy külön kör tárgya, itt **szándékosan nem** javítjuk, hogy a hatókör ne
csússzon szét):

- `ajax.save_escorts.php` — **nincs nonce-ellenőrzés** (`check_ajax_referer`),
  pedig a `vespa_nonce` létezik (`vitarex-vespa-plugin.php:75`). Ez a plugin
  AJAX handlereinek nagy részére igaz, nem csak erre.
- `contest.signup.php:167-171` — dupla `prepare()` egy már behelyettesített
  stringen: a **normál** (nem szabadidősport) ág `AND a.school_id=%d`
  feltételébe a `$contest_id` kerül. **A 4. csomag (A4) tárgya.**
- `contest.signup.php:131-147` — a nevezés-törlés nem ellenőrzi az athlete
  tulajdonosát. **A 4. csomag (A4) tárgya.**
- `includes/Ajax/ajax.contest_results.php:57` — a `vespa_get_contest_results`
  handleren nincs jogosultság-ellenőrzés és nincs nonce. **A 3. csomag (A2)
  tárgya**, mert az érinti ezt a fájlt.

## Tesztelés (manuális — nincs automatizált tesztkészlet)

### A5

- **Szűrő-mátrix**: mind a négy `filter` értékre (`all`, `country`, `0`,
  konkrét `state_id`) letöltött XLSX. Ellenőrzés:
  - `country` → csak `contest_type=1` adatok
  - `0` („Összes megye") → **eltér** az `all`-tól, és csak `contest_type=3`
  - `all` → a szabadidős (4) versenyek **nem** szerepelnek
  - konkrét megye → csak az adott `state_id`
- **Konzisztencia**: az „Összes"-nél a három vödör (országos + regionális +
  megyei) összege = az összesen, **ha nincs több típuson induló diák**. Ha van,
  a vödrök összege több — ez a helyes viselkedés (4. döntés), és a riportban
  megjelenik a magyarázó megjegyzés.
- **Dupla induló**: egy diák, aki megyein és országoson is indult, **mindkét**
  vödörben megjelenik, az összesenben **egyszer**. Ez ma nem így van — ez a
  javítás lényege, célzottan tesztelendő (DB-ben előkészített eset).
- **Versenyszám-sor** (`:229-240`): a „Diákolimpia versenyek száma" a
  `str_replace` frissítése után **továbbra is helyes** — ez a néma törés
  kockázata. Külön ellenőrizendő, mert nem dob hibát, ha elromlik.
- **Intézmény-szűrő**: konkrét intézményt választva a riport **szűkül** (ma
  némán hatástalan).
- **„Szűrés:" sor**: megjelenik az XLSX-ben, és a tényleges szűrést írja le.
- **Hiányzó paraméter**: `?download_riports=szezon_riport&series=X` (a többi
  nélkül) → nincs PHP warning, az XLSX megnyitható.
- A `series` paraméter nélküli hívás továbbra is `die` (`:81`).

### A3

- Kísérő `"asdf"` e-mailel → **hibaüzenet a mezőn**, nincs mentés.
- Kísérő `"12"` telefonnal → hibaüzenet.
- Kísérő valós adatokkal (`+36 30 123 4567`, `nev@pelda.hu`) → **átmegy**.
- Külföldi / szokatlan, de valós formátumú szám → **átmegy** (a validáció
  megengedő).
- **Üres kísérő-sor változatlanul legális**: 1 kitöltött + 4 üres sor → mentés
  sikeres. Ez a `required` elhagyásának lényege — regressziós teszt.
- Gépkocsivezetőkre ugyanez.
- Testnevelő létrehozása (`user-new.php`) telefon nélkül → **hiba**.
- Testnevelő **saját profiljának** mentése (`profile.php`) telefon nélkül →
  **hiba**. *(Ma a `$_POST['role']` hiánya miatt átmenne — ez a 2.5 pont
  lényege.)*
- Nem-testnevelő szerepkör (pl. iskolaigazgató) mentése telefon nélkül →
  **átmegy**, és a mező rejtve.
- Meglévő, telefon nélküli testnevelő: a listákban, nevezésnél, riportban
  **változatlanul működik**, amíg a profilját nem menti.
- Szerepkör-váltás a `#role` legördülőn → a telefon blokk megjelenik/eltűnik.

### A6

- Login képernyő: VESPA logó + FODISZ logó + háttér, a bejelentkezés működik.
- wp-admin: FODISZ logó a menü tetején, lábléc logóval + verzióval, a tartalom
  **olvasható** marad a háttéren.
- **Összecsukott admin menü** (`.folded`): a logó nem torzul és nem lóg ki.
- Szűk képernyő (WP responsive admin, <782px): a menü- és lábléc-logó nem
  töri a felületet.
- A meglévő piros kiemelés (`#adminmenu` aktív elem, `.btn-primary`,
  checkbox, `tbl-header`) a változó-csere után **vizuálisan azonos**.

### Általános

- Minden módosított PHP fájl `php -l`-clean.

## Hatókörön kívül (YAGNI)

- **A meglévő ~40 `contest_type` magic number átírása.** Csak a szezon riport
  kap konstanst. A `contest_list.php` (~40 hely), `datalist.contests.php`,
  `ajax.contest_races.php`, `fodisz_api.php` stb. érintetlen.
- **A `legnepszerubb_sportagak` riport** (`download_riports.php:915-920`) —
  ugyanaz a hiányzó `filter == 0` ág, de a lista az A5-öt a **szezon riportra**
  szűkíti. Külön tétel.
- **A többi riport `GROUP BY` felülvizsgálata** — a `verseny_diak`,
  `versenyen_resztvevo_iskolak` stb. hasonló mintát használhat; nem vizsgáljuk.
- **Nonce-ellenőrzés bevezetése** az AJAX handlereken — rendszerszintű hiány,
  külön kör.
- **Teljes validációs réteg** minden AJAX handlerre. Csak a két helper készül
  el, és csak az A3 (+ később az A1) használja.
- **Design system / komplett CSS refaktor.** Csak `:root` paletta + a meglévő
  hardkódolt értékek cseréje.
- **A `bootstrap.min.css` betöltési sorrendjének javítása** — a meglévő
  megjelenést változtatná meg, kockázatos, nem szolgálja e három tételt.
- **Az `adminisztrator` szerepkör hiánya** (a konstans létezik, a
  `$custom_roles_array`-ből kimaradt → a role sosem jön létre, de három hely
  ellenőrzi). Valós hiba, de nem ehhez a csomaghoz tartozik.
- **A `vespa_contests_escorts.escort_data` szerializált tárolásának
  normalizálása.** A validáció a meglévő szerkezetre épül.
- **A halott legacy escort-út** (`includes/Ajax/ajax.contest_escorts.php` +
  `templates/contest_view_save.php:191-225`) — a már DROP-olt `escort_name` /
  `escort_phone` / `escort_email` oszlopokra épül, a jelenlegi sémán nem futhat
  le. Nem javítjuk és nem töröljük ebben a csomagban.

## Érintett fájlok

**Közös alap:**
- `includes/Core/vespa.model.contest.php` — `VespaContestType` konstansok
- `includes/Core/functions.php` — `vespa_validate_email()`, `vespa_validate_phone()`
- `css/vespa-admin.css` — `:root` paletta, a hardkódolt értékek cseréje

**A5:**
- `includes/Export/download_riports.php` — `vespa_download_riport_szezon_riport()`
  (`:39-252`): szűrő négy ága, GROUP BY, `str_replace` minta, redundáns
  lekérdezés törlése, `institutionId`, `isset`-védett paraméterek, „Szűrés:"
  fejléc, iskolaszám-címkék, átfedés-megjegyzés; `riportPartDiakok()`
  (`:254-284`): változónév

**A3:**
- `includes/Ajax/ajax.save_escorts.php` — formátum-validáció + `sanitize_text_field`
- `templates/contest_view_entering.php` — `type="email"` / `type="tel"` (`required` **nem**)
- `includes/Admin/user.fields.php` — telefon mező (megjelenítés, mentés, JS),
  `validate_extra()` szerepkör-forrás javítása

**A6:**
- `css/vespa-admin.css` — FODISZ logó a menü tetején, `#wpcontent` háttér
- `css/vespa-login.css` — FODISZ logó, `body.login` háttér
- `includes/Admin/plugin.assets.php` — `admin_footer_text` filter
