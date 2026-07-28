# Beszámoló kérdőív: kitöltöttség-jelzés és megjegyzés kiválasztás nélkül — tervezés

Dátum: 2026-07-28

Ez az „A" alprojekt egy öt tételből álló igénylistából. A lista a következő
független alprojektekre bomlik, ebben a javasolt sorrendben:

| # | Alprojekt | Állapot |
|---|---|---|
| A | Kérdőív-páros: kitöltöttség-jelzés + megjegyzés kiválasztás nélkül | **ez a dokumentum** |
| B | Diák eredmények exportálása a sportolói listáról | később |
| C | Szintidő kezelése futószámoknál, feltételes nevezés | később |
| D | Verseny létrehozása kizárólag legördülő mezőkből | később |

A 4. és az 5. eredeti igénypont azért került egyetlen alprojektbe, mert
ugyanazt a két fájlt írja át; külön futtatva a második felülírná az elsőt.

## A probléma

**Kitöltöttség.** Az adminisztrátorok és a verseny-sportigazgatók ma sehol nem
látják egy listában, mely versenyekhez készült el a beszámoló. Az információ
létezik — a `vespa_contest_has_answers()` már megvan
(`includes/Core/functions.php:145`) —, de csak a verseny részletei oldalon,
közvetve: attól függ, látszik-e a „Beszámoló rögzítése" gomb.

**Megjegyzés kiválasztás nélkül.** A 23 közös kérdésből 17-nek egyetlen
válaszlehetősége van, jellemzően szó szerint `válasz a megjegyzésben`. Ezeknél a rádiógomb
értelmetlen: a tartalom a megjegyzésbe kerül. A kitöltő mégis kénytelen
választani, mert a be nem jelölt rádiógombot a böngésző nem küldi el, és a
mentés definiálatlan indexre hivatkozna.

**Három hiba, ami menet közben derült ki.** Mindhármat ez az alprojekt
javítja, mert ugyanazt a mentési utat érinti:

1. A mentés **mindig `INSERT`-el**, sosem frissít
   (`includes/Datalist/datalist.questions_answered.php`), és az űrlap sosem
   tölti elő a korábbi válaszokat. Élesben duplikálódás még nem történt
   (0 verseny a 71-ből), mert a „Beszámoló rögzítése" gomb az első mentés
   után eltűnik — de a URL közvetlen megnyitásával kiváltható.
2. A `save_questions_answered` AJAX-végpontban **nincs se nonce-, se
   jogosultság-ellenőrzés**. A `VESPA_Datalist` ősosztály hookolja
   (`includes/Core/core.datalist.php:14`), a felülírt `save()` pedig egyiket
   sem végzi el. Ma bármely bejelentkezett felhasználó beszúrhat
   beszámoló-sorokat. Amíg ez csak beszúrás volt, kevésbé fájt; szerkeszthetővé
   téve felülírássá válik.

3. **A mentés ma kérdéseket veszít.** Az űrlap a mezőit a kérdés
   `ordernum`-ával nevezi el (`answer0`, `answer1`, `answer7` …), a mentés
   viszont `0`-tól `count(kérdés) - 1`-ig számlálva olvassa őket. Az
   `ordernum` értékek **nem folytonosak**: `0, 1, 7, 8 … 26, 28`, 23 kérdés
   mellett. Így a mentés az `answer2`–`answer6` nem létező mezőket olvassa
   (definiálatlan index, üres sor), az `answer23`–`answer26` és `answer28`
   mezőket pedig **soha nem olvassa el** — az azokra adott válasz némán
   elvész. Ez magyarázza, miért 17–18 sorosak a beszámolók 23 kérdés mellett.
   A javítás ugyanaz a lépés, ami a szerkeszthetőséghez amúgy is kell: a
   mentés a kérdéseken iterál, nem egy számlálón.

## Amit építünk

1. A beszámoló utólag szerkeszthető, előre kitöltött űrlappal, duplikálódás
   nélkül.
2. Egy kérdés megjegyzése kiválasztás nélkül is rögzíthető; ahol a kérdésnek
   csak egy válaszlehetősége van, ott rádiógomb sem jelenik meg.
3. A versenyek listája új oszlopot kap, amely három állapotban mutatja a
   beszámoló kitöltöttségét.

### Döntések

| Kérdés | Döntés |
|---|---|
| Az oszlop helye | A versenyek listája (mind a négy táblázat), nem a versenyszámoké |
| Szerkeszthetőség | A beszámoló utólag szerkeszthető, `versenyek_kezelese_kiiras_modositas_torles` jogosultsággal |
| Jelzés | Három állapot: nincs kitöltve / részleges számlálóval / kitöltve |
| Régi beszámolók | A részleges állapot tájékoztató és semleges; csak a nulla válaszos verseny kap figyelmeztető jelzést |
| Mikor megválaszolt egy kérdés | Ha van válasza **vagy** megjegyzése |

A „versenyszámok listája" megfogalmazás azért nem érvényesült szó szerint,
mert a beszámoló a versenyhez tartozik (`vespa_questions_answered.contest_id`),
nem versenyszámhoz. Versenyszám-szintű megjelenítésnél minden sorban ugyanaz
az érték állna.

## Adatmodell

### Egy új oszlop

```sql
ALTER TABLE vespa_questions_answered
  ADD COLUMN question_id int(11) NOT NULL DEFAULT 0,
  ADD KEY contest_question (contest_id, question_id);
```

A tábla ma a kérdés **szövegét** tárolja (`question varchar(200)`), nem az
azonosítóját. Szerkesztéskor viszont párosítani kell a meglévő sort az
aktuális kérdéshez, és a szöveg szerinti párosítás eltörik, amint valaki
átfogalmaz egy kérdést a közös kérdéskészletben.

A `question` oszlop **marad**: a beszámoló pillanatképe arról, mi volt a
kérdés szövege a kitöltéskor. Nem cseréljük le, csak kiegészítjük.

### Egyszeri feltöltés

A meglévő 539 sor `question_id`-ja szövegegyeztetéssel töltődik fel a
`vespa_contests_questions` táblából. Ami nem talál — azóta törölt vagy átírt
kérdés —, az `question_id = 0` értéken marad.

A migráció egy verziókapu mögött fut, a `vespa.szabadidos.install.php`
mintájára, de saját fájlban és saját option-nel (`vespa_kerdoiv_db_version`),
hogy a két migráció ne akadjon egymásba.

`dbDelta`-t itt **nem** használunk. A `dbDelta` teljes `CREATE TABLE`
utasítást vár, a `vespa_questions_answered` táblát viszont nem ez a plugin
hozza létre — a séma a dumpban él —, tehát a teljes definíciót itt
megismételni azt kockáztatná, hogy egy jövőbeli kézi séma-változás után a
`dbDelta` visszaírja a régi alakot. Helyette a migráció megnézi az
`information_schema`-ban, létezik-e már az oszlop, és csak akkor futtat egy
sima `ALTER TABLE`-t. Az oszlop-ellenőrzés és a verziókapu együtt teszi a
lépést idempotenssé.

### A `question_id = 0` sorok

Ezek egy azóta megszűnt vagy átírt kérdéshez tartozó, történelmi válaszok.
**Soha nem töröljük őket.** A szerkesztőben a lap alján, halványan, „korábbi
kérdés" jelöléssel, csak olvashatóan jelennek meg. A kitöltöttség
számításába nem számítanak bele — sem a nevezőbe, sem az osztóba.

## Mentés

A `VESPA_Questions_Answered::save()` teljesen átíródik.

**Kapuőrzés a művelet előtt:**

```php
check_ajax_referer('vespa_nonce', 'nonce');
if (!current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) {
    wp_send_json_error(array('errors' => array(...)));
}
```

Ugyanez a jogosultság védi a `questions_answered_editor.php` sablon
betöltését is (`includes/Admin/menu.masterdata.php` `vespa_menu_contests()`
`question` ága), különben a link látszik, de a mentés elszáll.

Ez **szűkítés** a mai állapothoz képest: a Versenyek menüt ma a jóval tágabb
`versenyek_megtekintese` engedi be, és a mentés semmit nem ellenőriz. A
szűkítés viszont senkit nem zár ki, aki ma valóban beszámolót ír: a versenyt
létrehozó szerepek — diák sportigazgató, FODISZ sportigazgató, megyei
versenyigazgató, megyei vezető, adminisztrátor — mind rendelkeznek ezzel a
jogosultsággal (`includes/Core/vespa_roles.php:149-209`). Csak a két tisztán
megtekintő szerep veszít el egy képességet, amivel eddig sem kellett volna
rendelkeznie: felettes szerv és tankerületi igazgató.

**A művelet párosít, nem szúr be vakon.** Betöltjük a verseny meglévő sorait
`question_id` szerint indexelve, majd az **aktuális kérdéseken** végigmenve —
nem egy `0..count-1` számlálón, ami ma kérdéseket veszít. Az űrlapmezők
nevében a kérdés `ordernum`-a áll (`answer7`, `qnote7`), tehát a mentés is ezt
használja kulcsként a `$_POST`-ban:

| eset | művelet |
|---|---|
| van sor, és van válasz vagy megjegyzés | `UPDATE` |
| nincs sor, és van válasz vagy megjegyzés | `INSERT` |
| van sor, de mindkét mező üresre szerkesztve | `DELETE` |
| nincs sor, és mindkét mező üres | nincs művelet |

Üres sort tehát nem tárolunk. Ma minden kérdéshez keletkezik sor akkor is, ha
semmit nem írtak bele — ettől lenne a számláló hazug. Adat így sem vész el:
üres sorban nincs adat.

A `question_id = 0` történelmi sorokat a ciklus nem érinti.

Ez a duplikálódást is megszünteti: nincs több feltétel nélküli `INSERT`.

## Szerkesztő űrlap

`templates/questions_answered_editor.php` változásai:

- **Előre kitöltés.** A rádiógomb a mentett válasszal bejelölve, a textarea a
  mentett megjegyzéssel. Ma egyik sem történik meg — az űrlap mindig üresen
  renderelődik, ami szerkeszthetővé téve önmagában adatvesztés-forrás lenne.
- **Egyetlen válaszlehetőségnél nincs rádiógomb.** A szabály a lehetőségek
  *számára* néz, nem a `válasz a megjegyzésben` szövegre — így nem drótozunk
  be egy varázsszöveget, és a jövőben felvett hasonló kérdésekre is működik.
  Ilyenkor csak a megjegyzés mező jelenik meg, teljes szélességben.
- **Nincs kötelező kiválasztás.** A ma kikommentelt szerveroldali ellenőrzés
  (`datalist.questions_answered.php:14-19`) véglegesen kikerül — a holt kódot
  töröljük, nem hagyjuk ott félreérthetően.
- **`isset()` védelem.** A be nem jelölt rádiógombot a böngésző nem küldi el;
  a mentés ma definiálatlan indexre hivatkozna. Üres string kerül a helyére.
- **Történelmi blokk.** A `question_id = 0` sorok a lap alján, csak
  olvashatóan.

`templates/contest_view.php:98` — a gomb mindig látszik, felirata az
állapottól függ: „Beszámoló rögzítése", ha még nincs egyetlen sor sem,
„Beszámoló szerkesztése", ha van. A letöltés-menü „Beszámoló" tétele
(`:110`) változatlanul a `vespa_contest_has_answers()`-re épül.

## Kitöltöttség-jelzés

### Számítás

- **Osztó:** az aktuális kérdések száma (`vespa_contests_questions`).
- **Nevező:** hány aktuális kérdéshez van a versenynek sora, amelyben az
  `answer` vagy a `qnote` nem üres.
- A párosítás `question_id` szerint történik, így az átfogalmazott kérdés nem
  esik ki.

| állapot | feltétel | megjelenés |
|---|---|---|
| nincs | nevező `= 0` | **Nincs kitöltve**, piros |
| részleges | `0 <` nevező `<` osztó | `17/23`, semleges szürke |
| kész | nevező `=` osztó | **Kitöltve**, zöld |

A részleges állapot szándékosan semleges: a kérdéskészlet 23 kérdésre nőtt,
míg a régi beszámolók 17–18 sorosak, tehát szigorú mérce mellett
gyakorlatilag minden régi verseny „részleges" lenne. Az egyetlen, ami
figyelmet kér, a nulla válaszos beszámoló.

### Elhelyezés

`templates/contest_list.php` mind a négy táblázata — Országos (`:608`),
Megyei (`:735`), Regionális (`:834`), Szabadidősport (`:918`) — új
`<th>Beszámoló</th>` oszlopot kap a „Fogy. csoport" után, a „Műveletek" elé.
A „Műveletek" oszlop `class="no-export"` osztályt visel; az új oszlop **nem**,
tehát a lista exportjába bekerül.

### Teljesítmény

A lista ma soronként külön lekérdezést futtat a fogyatékossági csoportokért
(`contest_list.php:629`). Ehhez nem nyúlunk, de újabb N lekérdezést sem
teszünk hozzá: egyetlen aggregáló lekérdezés adja a
`contest_id → megválaszolt kérdések száma` térképet, még a táblázatok
kirajzolása előtt.

### Új függvények

`includes/Core/functions.php`:

- `vespa_contest_answer_counts($contest_ids = null)` → `array(contest_id => int)`,
  egyetlen lekérdezésből. `null` esetén minden versenyre.
- `vespa_contest_question_count()` → `int`, az aktuális kérdések száma.

`includes/Core/vespa.kerdoiv.php` (**új**, tiszta, WordPress-független):

- `vespa_kerdoiv_allapot($megvalaszolt, $osszes)` → `'nincs' | 'reszleges' | 'kesz'`
- `vespa_kerdoiv_cimke($megvalaszolt, $osszes)` → megjelenítendő szöveg
- `vespa_kerdoiv_kerdes_megvalaszolt($answer, $qnote)` → `bool`

A meglévő `vespa_contest_has_answers()` **marad**, változatlan jelentéssel
(„van legalább egy sor"). A letöltés-menü ezt használja tovább.

## Peremesetek

**Nincs egyetlen kérdés sem.** Ha a `vespa_contests_questions` üres, az osztó
0. Ilyenkor az oszlop `—` jelet mutat, nem osztunk nullával, és a „Beszámoló
rögzítése" gomb sem jelenik meg.

**Verseny nélküli beszámoló-sor.** A tábla nem tartalmaz idegenkulcsot; egy
törölt versenyhez tartozó sor árván maradhat. Az aggregáló lekérdezés a
versenyekből indul ki, tehát az árva sor egyszerűen nem jelenik meg. Nem
takarítunk — ez nem ennek az alprojektnek a dolga.

**Egyidejű szerkesztés.** Két felhasználó egyszerre szerkeszti ugyanazt a
beszámolót: az utolsó mentés nyer. Zárolást nem építünk — a beszámolót
gyakorlatilag egy ember tölti ki, és a zárolás aránytalan lenne.

**Kérdés törlése a közös készletből.** A hozzá tartozó válaszok
`question_id`-ja továbbra is a törölt kérdésre mutat, tehát a párosítás nem
találja meg őket, és a történelmi blokkba kerülnek. Ez a szándékolt
viselkedés.

## Ellenőrzés

Nincs helyi WordPress-környezet, ezért a `tests/test-szabadidos-fields.php`
mintáját követjük: a WordPress nélkül futtatható logika külön, tiszta
függvényekbe kerül, és azokra írunk tesztet.

Tesztelhető egységek (`tests/test-kerdoiv.php`, **új**):

- `vespa_kerdoiv_allapot()` a három állapotra, a határokra (`0/0`, `0/23`,
  `1/23`, `22/23`, `23/23`)
- `vespa_kerdoiv_cimke()` szövegei, beleértve a `0/0` esetet
- `vespa_kerdoiv_kerdes_megvalaszolt()` a négy kombinációra (van/nincs
  válasz × van/nincs megjegyzés), whitespace-only bemenettel is

A DB-hez, AJAX-hoz és sablonhoz kötött rész telepítés utáni kézi
ellenőrzéssel fedett; a lépéssoros listát a megvalósítási terv tartalmazza.

## Érintett fájlok

| Fájl | Változás |
|---|---|
| `includes/Core/vespa.kerdoiv.php` | **új** — tiszta állapotlogika |
| `includes/Core/vespa.kerdoiv.install.php` | **új** — `question_id` oszlop, egyszeri feltöltés, verziókapu |
| `tests/test-kerdoiv.php` | **új** — a tiszta logika unit tesztjei |
| `includes/Core/functions.php` | *módosul* — aggregáló számláló és kérdésszám helperek |
| `includes/Datalist/datalist.questions_answered.php` | *módosul* — nonce + jogosultság, párosító mentés, holt kód törlése |
| `templates/questions_answered_editor.php` | *módosul* — előre kitöltés, egyopciós kérdés, történelmi blokk |
| `templates/contest_view.php` | *módosul* — a gomb mindig látszik, állapotfüggő felirattal |
| `templates/contest_list.php` | *módosul* — „Beszámoló" oszlop mind a négy táblázatban |
| `includes/Admin/menu.masterdata.php` | *módosul* — a `question` ág jogosultság-ellenőrzése |

A `vitarex-vespa-plugin.php` nem igényel módosítást: az `includes/Core`
könyvtár minden PHP fájlját `glob`-bal tölti be.
