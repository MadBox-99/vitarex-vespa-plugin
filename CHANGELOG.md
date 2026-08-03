# Changelog

## [2.3.22] - 2026-08-03

### Javítások
- **A riportokban mutatott szűrők végre tényleg szűrnek:** Több riportnál a felület felkínált egy szűrőt (Intézmény, Nem, Fogyatékossági csoport, Tankerület), a riport viszont némán figyelmen kívül hagyta, és szűretlen számokat adott — hibajelzés nélkül. Érintett volt az „Adott tanévben FODISZ diákolimpián részt vett fogyatékkal élő diákok száma", az „Adott időszakban versenyen indult diákok száma iskolánként" és az „Adott időszakra a sportágak népszerűsége" riport. Ezek a riportok mostantól más — helyes — számokat adnak, ha élsz ezekkel a szűrőkkel.
- **A sportág-népszerűségi riport üres választ adott:** Az „Adott időszakra a sportágak népszerűsége" riport „Összes megye" szűréssel nem generált fájlt, csak egy üres választ. Mostantól lefut, és a megyei versenyekre szűr, ahogy a felirata ígéri.
- **Halott tankerület-szűrés:** Ugyanennél a riportnál a tankerület szerinti szűrés egy elgépelt változónév miatt soha nem érvényesült.
- **Hiányzó GET paraméterek:** A riportok több paramétert is ellenőrzés nélkül olvastak ki, ami PHP-figyelmeztetést írhatott a válaszba, és a binárisan írt XLSX-fájlt megsérthette. Mostantól minden paraméter olvasása védett.

## [2.3.21] - 2026-08-03

### Új funkciók
- **Szezon és naptári év külön szűrhető a riportokban:** A tanév-alapú riportoknál a versenysorozat eddig kötelező volt, a naptári év pedig csak azon belül szűkített — tiszta naptári éves kimutatás így nem volt készíthető. Mostantól a versenysorozat legördülőben van „Összes szezon (nincs szűrés)” opció, és a naptári év mező mind az öt tanév-riportnál megjelenik. Mind a négy kombináció használható: csak szezon, csak naptári év, mindkettő (a szezon adott évbe eső fele), vagy egyik sem. Az alapértelmezés továbbra is a legutóbbi szezon.
- **Egyértelmű időszak-felirat az XLSX fejlécében:** Minden érintett riport fejléce megmondja, mi volt beállítva — „2025/2026”, „2025/2026 — naptári év: 2025”, „Naptári év: 2025” vagy „Nincs időszakszűrés (összes verseny)”. Eddig a szezon és a naptári év összemosódott a fájlban.

### Javítások
- **Szezon riport szezon nélkül:** A Szezon riport eddig némán megszakadt (üres válasz), ha nem volt kiválasztva versenysorozat. Mostantól szezon nélkül is lefut.
- **Versenysorozat prepared paraméterként:** A „Tanévben versenyen indult iskolák” riport a kiválasztott versenysorozat azonosítóját közvetlenül, előkészített paraméter nélkül fűzte a lekérdezésbe.
- **Sérült XLSX szűrő nélküli riportnál:** Ha egy riport minden szűrője „összes” állásban volt, az adatbázis-réteg figyelmeztetést írhatott a válaszba, ami a bináris XLSX-fájlt megsértette. Érintett volt a „Tanévi diákolimpia diákok” riport intézmény-listája is.

## [2.3.18] - 2026-08-03

### Javítások
- **Verseny-dokumentumok letöltése jogosultsághoz kötve:** A `?download_contest_docs=` végpont eddig semmilyen jogosultságot nem ellenőrzött (a helyén egy `TODO` állt), és `init`-en fut, ezért a linket ismerve bejelentkezés nélkül is letölthető volt a beszámoló, a sportolói logisztika, a nevezettek email listája és a nevezési lista. Mostantól minden dokumentumtípusnak van szabálya, jogosulatlan hívásra 403 a válasz.
- **Beszámoló láthatósága:** A beszámoló PDF-je és a versenylista „Beszámoló" oszlopa csak a versenyt kezelő szerepeknek jelenik meg (rendszergazda, FOVESZ/FODISZ sportigazgató, diák sportigazgató, megyei versenyigazgató, megyei vezető) — ugyanannak a körnek, amelyik rögzíteni is tudja. A testnevelő és az iskolaigazgató eddig látta az oszlopot és letölthette a PDF-et.
- **A felület és a végpont közös szabályon:** A verseny adatlapján a „Dokumentum letöltése" legördülő ugyanazt a döntést kérdezi, mint a letöltő végpont, így a kettő nem tud elcsúszni. A szabály tiszta függvényben van, 40 unit teszttel. Senki nem veszít olyan hozzáférést, ami eddig a felületen elérhető volt.

## [2.3.17] - 2026-08-03

### Javítások
- **Verseny nevének szórendje:** A generált versenynév típusonként más sorrendet követ, mert magyarul csak így helyes — megyei: „Nógrád megyei Atlétika Diákolimpia”, országos: „Atlétika Diákolimpia Országos Döntő”, regionális: „Atlétika Diákolimpia Regionális”, szabadidősport: csak a sportág neve. A „Döntő” szó nagy kezdőbetűs.
- **Döntő jelölőnégyzet megyei versenynél:** A megyei verseny nem döntő, ezért a jelölőnégyzet megszűnt. A „Döntő” szót továbbra is (és kizárólag) az országos verseny kapja meg automatikusan.
- **Szabadidősport verseny átminősítése:** Egy tévesen szabadidősportként felvett verseny nem volt átminősíthető megyeivé: a típus váltásakor a lebonyolítás és a sorozat szabadidősporton maradt, és a mentés „Szabadidő versenytípusnak a lebonyolítása is csak szabadidősport lehet!” hibával elszállt. A típusváltás mostantól mindkét irányban szinkronizál.
- **Súgószöveg a Megnevezés mezőnél:** Új verseny felvitelekor a mező alatt megjelenik, hogy a megnevezés automatikusan kitöltésre kerül, és csak eltérő név esetén kell kézzel beírni.
- **Letöltött fájlok neve:** Az Excel- és PDF-letöltések a verseny azonosítója helyett a verseny nevét kapják (pl. „Nógrád megyei Atlétika Diákolimpia_sportolói logisztika.xlsx”). Érintett: kiírás, beszámoló, orvosi engedély, sportolói logisztika, nevezettek email lista, nevezési lista, szabadidős nevezők.

## [2.3.16] - 2026-07-28

### Új funkciók
- **Verseny nevének automatikus összeállítása:** Új verseny felvitelekor a versenynév a kiválasztott értékekből áll össze (megye / verseny típusa / sportág / döntő), így egységes elnevezést kapnak a kiírások. A mező továbbra is kézzel felülírható.

### Javítások
- **Megyei sportág-szűrő:** A versenylista megyei táblázatának sportág-szűrője az országos legördülő értékét olvasta, ezért rossz (vagy üres) találatokat adott — mostantól a saját szűrőjének értékét használja.

## [2.3.14] - 2026-07-28

### Új funkciók
- **Szintidő-nyilvántartás:** Új `vespa_athlete_qualifying_times` tábla, ahol sportolónként és versenyszámonként rögzíthető a szintidő (a sportoló-szerkesztőben). A versenyszám-felvitelnél megadható egy `min_qualifying_seconds` küszöb, és ha be van állítva, a nevezés csak a küszöböt teljesítő sportolóknál engedélyezett. A szintidő-végpontok iskolához vannak kötve (más iskola sportolójának ideje nem írható/olvasható), a beágyazott JSON `JSON_HEX_TAG`-gel készül.
- **Sportolói eredmények XLSX exportja:** A sportolói listáról egy kattintással letölthetők a sportoló eredményei Excel-ben.
- **Rendező és Helyszín legördülő:** A verseny-szerkesztőben a Rendező és a Helyszín mező a már meglévő értékekből választható, de szabad szöveggel bővíthető, így csökken az elgépelésből adódó duplikáció.

### Javítások
- **Export intézmény-JOIN:** A diákexport `LEFT JOIN`-ra vált az intézménynél, így hibás vagy hiányzó `school_id` esetén sem esik ki a diák a listából.

### Adatbázis migráció
- A szintidő-séma (`vespa_athlete_qualifying_times` tábla + `vespa_constest_events.min_qualifying_seconds` oszlop) `init`-en, a `vespa_szintido_db_version` option-kapuval jön létre — élesítés után egyszer be kell töltődnie a wp-adminnak.

## [2.3.13] - 2026-07-28

### Új funkciók
- **Szerkeszthető beszámoló (kérdőív):** A beszámoló utólag is módosítható, a korábbi válaszokkal előre kitöltve; az egyopciós kérdések válaszai megmaradnak, a rádiógombos válasz törölhető.
- **Kitöltöttség-jelzés a versenylistában:** Új oszlop mutatja, hogy az adott verseny beszámolója mennyire van kitöltve (aggregáló számláló helperekkel, unit tesztekkel).
- **`question_id` a beszámoló-válaszokban:** A válaszok mostantól kérdés-azonosítóhoz kötődnek, nem a kérdés szövegéhez. A visszatöltés (backfill) csak egyedi kérdésszövegekre fut, a duplikált szövegű kérdések tudatosan 0-n maradnak.

### Javítások
- **Beszámoló-mentés jogosultság:** A párosító típusú kérdések mentése nonce-t és jogosultság-ellenőrzést kapott, a mentés eredményét a felület ellenőrzi.
- **Beszámoló PDF sorrend:** A `NULL` `ordernum`-ú kérdések a PDF végére kerülnek, nem az elejére.

## [2.3.12] - 2026-07-28

### Új funkciók
- **Szabadidős nevezési mezők (versenyenként szabadon bővíthető mezőépítő):** Az adminban versenyenként vehetők fel egyedi nevezési mezők (soronkénti AJAX mentéssel, sorrendezéssel), amelyek megjelennek a front-end nevezési űrlapon; a válaszok a nevezéssel együtt mentődnek, és láthatók az admin nevezői listában és az exportban. Két új tábla (db verzió 3), tiszta logika + unit tesztek.

### Javítások
- **Mezőszerkesztő adatvesztési hibák:** Code review alapján javítva a szerkesztés visszaírása, a piszkozat-sor duplikációja, a nyilakkal való mozgatás széle, valamint a `wpdb->insert` format tömbje a `field_save` végponton.

## [2.3.10] - 2026-07-21

### Új funkciók
- **Lejárat jelzése a nevezés-megnyitó admin listában:** Látszik, ha egy szabadidős verseny külső nevezési ablaka már lejárt.
- **Dátum a nevezők-legördülőben:** Az azonos nevű versenyek megkülönböztethetők a legördülőben megjelenő dátum alapján.

### Javítások
- **Regisztrációs dobbantó ablaka:** 1 óráról 10 percre csökkentve.

## [2.3.9] - 2026-07-21

### Felület
- **Tailwind stílus a szabadidős front-end nevezési felületen.**

## [2.3.8] - 2026-07-21

### Új funkciók
- **Front-end hozzáférés beállítása (5. csomag):** Új admin menü (`manage_options`), ahol pipa-listából jelölhetők a publikus, átirányítás alól mentesülő oldalak és a szabadidős landing page. Enélkül a `login.customiser.php` `redirect_to_admin()`-ja minden front-end kérést elterelt, így a `[vespa_szabadidos]` oldal senkinek nem volt elérhető, a szabadidős résztvevők pedig végtelen átirányítási hurokba kerültek.

### Javítások
- **Átirányítási hurok megszüntetése:** A szabadidős felhasználó a beállított nevezési oldalra kerül; a landing URL csak azonos hosztú lehet (`wp_validate_redirect` / `wp_safe_redirect`), az AJAX mentés hurokvédelmet kapott.

## [2.3.6] - 2026-07-20

A „Vespa fejlesztés, 2026.07.17." lista négy csomagja.

### Új funkciók
- **A1 — Excel-alapú tömeges sportoló-import:** Új `VESPA_Athlete_Importer` osztály (normalizálás, `parse()`, soronkénti `validate()`, tranzakciós `commit()` újra-deduplikálással), kétlépéses (előnézet → jóváhagyás) import UI és letölthető XLSX minta-sablon, jogosultság-ellenőrzéssel.
- **A2 — Bővített szűrés az eredménylistákban:** Szerveroldali szűrők a verseny eredmények AJAX-ban, szűrő-sor a verseny eredmények nézetben, versenyszám-szűrő a riport felületen (Vue), sportág + versenyszám szűrés a riport-exportokban, valamint sportoló-keresés az eredmény-rögzítő rácson.
- **A4 — Szabadidősport külső regisztráció (elkülönített alrendszer):** Új `szabadidos_resztvevo` szerep wp-admin izolációval, külső résztvevő regisztráció e-mailes megerősítéssel, `[vespa_szabadidos]` front-end (regisztráció/belépés + saját nézet), külső nevezés és visszavonás IDOR-védelemmel, admin menü a versenyek külső regisztrációra nyitásához/zárásához, külső nevezők listája és XLSX exportja. Három új tábla `init`-kor, `vespa_szabadidos_db_version` option-kapuval.
- **A5/A3/A6 — Szezon riport, kötelező mezők, arculat:** Szezon riport szűrő- és `GROUP BY`-javítások (determinisztikus vödrök, hiányzó „Összes"/„Összes megye" ág), `VespaContestType` konstansok, telefonszám mező a felhasználói profilon, kísérő/gépkocsivezető e-mail + telefon formátum-validáció (`vespa_validate_email`, `vespa_validate_phone`), FODISZ logó az admin menüben, a láblécben és a login képernyőn, közös `vespa-palette.css` a hardkódolt színek helyett.

### Javítások
- **Excel dátum-sorszám konverzió** az importban (`parse()` → `Y-m-d`).
- **`handleFields` szerepkör-forrás** javítása a saját profilon (`profile.php` regresszió).

## [2.3.5] - 2026-06-05

### Új funkciók
- **Színjelmagyarázat a versenylistában:** Minden szekció (Országos / Megyei / Regionális / Szabadidősport) fejlécében megjelenik a sorszínek jelmagyarázata (Nevezhető / Még nem nevezhető / Már nem nevezhető).

### Javítások
- **Státusz-szín logika:** A lejárt nevezési határidő is pirosat ad (nemcsak a lezajlott verseny), a `0000-00-00 00:00:00` alapértelmezés pedig nem számít valódi határidőnek.

## [2.3.3] - 2026-06-02

### Javítások
- **Testnevelő nem törölhet versenykiírást:** Sem a jogosultság-ellenőrzés (`VespaContest::can_delete()`), sem a felület nem engedi (a Törlés gomb el is tűnik), még a saját maga által létrehozott kiírásnál sem.
- **Riport „Összes" szűrő:** A verseny-diák riport „Összes" ága csak az országos (1) és megyei (3) versenyeket tartalmazza, így megegyezik az országos + összes megyei lekérdezés összegével.

## [2.3.2] - 2026-05-29

### Új funkciók
- **Sportoló soft delete tulajdonlás alapján:** A testnevelő és az iskolaigazgató törölheti a saját iskolája diákjait; a törlés csak `is_deleted`/`deleted_at` beállítás, az adat a táblában marad. Az archivált diákok kimaradnak az élő-diák listákból (nevezés, sportolói lista).
- **Iskolaigazgató `sportolok_listazasa` jog:** Az igazgató is eléri a Sportolók listát.

### Adatbázis migráció
- `database/changes.sql` — `is_deleted` és `deleted_at` oszlopok a `vespa_athletes` táblához.

## [2.3.0] - 2026-05-29

### Új funkciók
- **Pedagógusok a nevezésnél:** A verseny nevezési oldalán új „Pedagógusok" szekció, ahol tetszőleges számú pedagógus adható meg (teljes név, mobil, e-mail, születési hely, születési idő, iskola neve — mind a 6 mező kötelező). Új `vespa_contest_teachers` tábla, `save_teachers` AJAX végpont. **Sportolót csak akkor lehet nevezni, ha az iskola megadott legalább egy pedagógust** az adott versenyre (a már nevezett sportoló levétele nem blokkolt).
- **Háromállapotú színkódolás a verseny-listában:** A versenynév cellája mostantól három állapotot jelez — 🔴 piros: a verseny napja már elmúlt; 🟢 zöld: véglegesített és épp nyitva a nevezés; 🔵 kék (`#5bc0de`): létrehozva, de még nem nyitott (vagy a nevezés már lezárult, de a verseny napja még nem volt meg). A 4× ismétlődő inline feltétel egy `vespa_contest_status_color()` segédfüggvénybe szervezve.

### Fejlesztői / üzemeltetési
- **`build.sh` deploy script:** Telepíthető ZIP artifact készítése a pluginból (`build/vitarex-vespa-plugin-<verzió>.zip`), a dev/meta fájlok kihagyásával, a `lib/vendor` megtartásával. Opcionális `--upload` (scp, `deploy.env` alapján). Minden build automatikusan +1 patch verziót lép.
- **9425-ös „átmeneti diákadat-tároló" intézmény takarítása:** Dokumentált, ellenőrzéssel és figyelmeztetéssel a `database/changes.sql`-ben (a ~4307 árva, versenyhez nem kapcsolódó diák + az intézmény törlése).

### Adatbázis migráció
- `database/changes.sql` (2026.05.29) — új `vespa_contest_teachers` tábla; valamint a 9425-ös intézmény és diákjainak (opcionális, ellenőrzéshez kötött) törlése.

## [2.2.0] - 2026-04-29

### Javítások
- **Eltűnt versenyadatok visszahozása:** Azok a régi versenyek, amelyek hard-delete-tel eltűnt sportra (sport_id=23, "Mezei futás") vagy versenyszámra (sport_event_id=42) hivatkoznak, ismét megjelennek. A versenyek nézete (`contest_view_racelist`, `contest_view_entering`), a versenyszám táblázat (`ajax.contest_races`), a verseny eredmények nézet, a riportok és a nyilvános API mind `LEFT JOIN`-ra vált a `vespa_sports` és `vespa_sport_events` tábláknál, így a hiányzó kapcsolt rekord már nem ejti ki a verseny teljes tartalmát.
- **Hiányzó név fallback:** Ha egy sportág vagy versenyszám rekord (akár hard-delete-tel) eltűnt, a felület most "(törölt sportág #ID)" jelölést mutat üres cella helyett.

### Új funkciók
- **Soft delete a sportoknál és versenyszámoknál:** A `vespa_sports` és `vespa_sport_events` tábla mostantól `is_deleted` és `deleted_at` oszlopokkal rendelkezik. A törlés gomb csak `is_deleted=1`-re állítja a rekordot, az adat fizikailag a táblában marad. Az új versenyek létrehozási űrlapjai, riport-szűrők és a sport/versenyszám szerkesztők csak az aktív (nem törölt) sportokat listázzák, de a régi versenyek továbbra is fel tudják oldani a nevet.
- **Audit log a Datalist törlésekhez:** A `VESPA_Datalist::delete()` minden törlést bevezet a `vitarex_log` táblába (mód: hard/soft, ID, érintett rekord JSON-ben), így a jövőben rekonstruálható ki, mikor és mit törölt.

### Adatbázis migráció
- `database/changes.sql` (2026.04.29) — `is_deleted` és `deleted_at` oszlopok hozzáadása, valamint a régi sport (23) és versenyszám (42) visszaállítása "Mezei futás (régi)" néven, `is_deleted=1` állapotban. Opcionálisan tartalmaz egy `UPDATE` parancsot a contest 298 árva nevezéseinek (1011 → 1051) javítására.

## [2.1.0] - 2026-03-19

### Javítások
- **PDF letöltés (versenykiírás, orvosi igazolás, beszámoló):** A PDF fájlok nem nyíltak meg letöltés után, mert a WordPress output buffer belekerült a PDF tartalmába és elrontotta azt. Mostantól a kimenet puffere tisztítva van a letöltés előtt, és a böngésző mindig letöltésként kezeli a fájlt (`Content-Disposition: attachment`).
- **AJAX nevezettek lista JOIN javítás:** A nevezettek lekérdezés hibás JOIN-ja javítva (`event_id` -> `contest_event_id`), amely miatt a versenyszám adatok nem jelentek meg helyesen.

### Új funkciók
- **Sportoló nemének megjelenítése a nevezettek listájában:** A verseny nevezettjeinek listájában mostantól látható a sportoló neme (férfi/nő) mind az admin, mind a pedagógus nézetben.

### Felület javítások
- **Nevezettek lista oszlopfejlécek:** A "Nevezettek listája" altáblázathoz oszlopfejlécek kerültek (Név, Nem, Születési dátum, Megye, Intézmény).
- **Felesleges fogyatékossági csoport oszlop eltávolítva:** A fogyatékossági csoport oszlop eltávolítva az egyes sportoló sorokból, mivel már csoportfejlécként megjelenik felette.

## [2.0.0]

- Alap verzió.
