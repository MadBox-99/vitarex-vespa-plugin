# Riport-szűrők összehangolása felület és backend között — tervezési dokumentum

Dátum: 2026-08-03

Forrás: a `2026-08-03-riport-szezon-naptari-ev` ág záró code review-ja két meglévő
hibát tárt fel, a rákövetkező vizsgálat pedig kiderítette, hogy mindkettő egy
tágabb probléma tünete.

## A gyökérok

A riport-felület URL-építője (`templates/riports_dashboard.php`, `getSubmitUri`)
és a backend paraméter-olvasói (`includes/Export/download_riports.php`) **egymástól
függetlenül változtak, és szétcsúsztak**. A szűrőblokkok kézzel másolódtak
függvényről függvényre; két másolat helyes maradt (`vespa_download_riport_verseny_diak()`
és a nemrég javított `vespa_download_riport_szezon_riport()`), a többiben viszont

- az egyik másolatban **elgépelt változónév** maradt (`$schoolDistrict` a
  `$schoolDistrictId` helyett),
- egy másik másolat **el sem készült** (a tanév-riport három szűrője),
- és van, ahol a **felület nem küldi** azt, amit a backend olvas.

Minden ilyen eltérés vagy némán figyelmen kívül hagyott szűrőt jelent (a
felhasználó szűkít, mégis szűretlen számokat kap, hibajelzés nélkül), vagy
definiálatlan `$_GET` indexet, ami PHP-figyelmeztetést ír a válaszba. Az utóbbi
csak bekapcsolt hibamegjelenítésnél látszik, de akkor a **binárisan írt XLSX-et
is elrontja** — ugyanaz a hibaosztály, ami ellen a `download_riports.php:51-52`
kommentje már véd.

## Az eltérések teljes mátrixa

| Riport | Backend olvassa, felület nem küldi | Felület mutatja/küldi, backend nem használja |
|---|---|---|
| `legnepszerubb_sportag` | `schoolDistrict`, `gender`, `disabilityGroupId` | Nem, Fogy. csoport, Tankerület, Intézmény |
| `tanev_diakolimpia_diakok` | `dateFrom`, `dateTo` | `institutionId`, `gender`, `disabilityGroupId` |
| `iskola_sportoltatott_diakok` | `series` | `institutionId`, `gender`, `disabilityGroupId` |
| `verseny_versenyszam` | `series` | — |
| `tanev_diakolimpia_versenyszam` | `dateFrom`, `dateTo` | — |
| `tanev_diakolimpia_versenyszam_sportag` | `dateFrom`, `dateTo` | — |
| `tanev_versenyen_indult_iskolak` | — | — |
| `verseny_diak` | — | — |
| `szezon_riport` | — | — |

A „backend olvassa, felület nem küldi" oszlop oka a **közösen kiszolgált
függvények**: `vespa_download_riport_verseny_versenyszam()` három, a
`vespa_download_riport_tanev()` két riporttípust szolgál ki, és mindkettő
feltétel nélkül kiolvassa az összes paramétert, akkor is, ha az adott típushoz
nem tartozik.

## Vezérelv

**Ahol a felület mutat egy szűrőt, ott azt implementáljuk.** Mezőt nem veszünk
el, mert az képességvesztés lenne: minden mező szándékosan került a felületre, és
mindegyik értelmes a saját riportjához.

## Design

### 1. Két új tiszta helper

A `includes/Core/vespa.riport.periodus.php` mintájára, ugyanabban a fájlban —
tiszta, adatbázistól független, ezért WordPress nélkül unit-tesztelhető:

```php
/**
 * @return array {sql: string, params: array}
 */
function vespa_riport_intezmeny_szuro($schoolDistrictId, $institutionId)
function vespa_riport_sportolo_szuro($disabilityGroupId, $gender)
```

- `vespa_riport_intezmeny_szuro()` a **`vi`** (`vespa_institutions`) táblára szűr:
  `AND vi.school_district_id=%d`, majd `AND vi.institution_id=%d`. Csak a pozitív
  egész értékek szűrnek (`vespa_riport_pozitiv_egesz()`).
- `vespa_riport_sportolo_szuro()` a **`va`** (`vespa_athletes`) táblára szűr:
  `AND va.disability_type=%d`, majd `AND va.gender=%s`. A nem csak akkor kerül be,
  ha az értéke pontosan `'nő'` vagy `'férfi'` — ez a meglévő fehérlista, marad.
- A paraméterek sorrendje mindkettőnél kötött, és követi az SQL-töredék
  helyőrzőinek sorrendjét.

**A két helper szétválasztása nem esztétikai.** A `vespa_download_riport_tanev()`
második lekérdezése (az összes intézményt listázó `$allSchool`) kizárólag a `vi`
táblát ismeri — egy összevont helper ott `va.gender`-re hivatkozó, hibás SQL-t
adna. A vágás pontosan a query-aliasok mentén fut.

Ezzel a gyökérok megszűnik: a szűrőlogikából egy példány marad, így nincs mit
elgépelni és nincs mit kihagyni.

### 2. Minden `$_GET` olvasás `isset`-védetté válik

A `download_riports.php` összes védelem nélküli olvasása megkapja a
`isset($_GET['x']) ? $_GET['x'] : <alapérték>` alakot, a `szezon_riport`
(`:53-59`) mintájára. Érintett sorok a jelenlegi állapotban: `:346-349`,
`:515-518`, `:625-631`, `:804-808`, `:963-968`.

Ez egy csapásra kiüríti a mátrix „backend olvassa, felület nem küldi" oszlopát:
a hiányzó paraméter alapértékre esik, ami minden érintett helyen „nincs szűrés"-t
jelent — pontosan a mai tényleges viselkedés, csak figyelmeztetés nélkül.

### 3. A hívók átállítása

| Függvény | Változás |
|---|---|
| `vespa_download_riport_szezon_riport()` | a meglévő négy szűrőblokk helyére a két helper |
| `vespa_download_riport_verseny_versenyszam()` | csak `isset`-védelem (nincs résztvevő-szűrője) |
| `vespa_download_riport_versenyen_resztvevo_iskolak_szama()` | `isset`-védelem + `vespa_riport_intezmeny_szuro($schoolDistrict, 0)` |
| `vespa_download_riport_verseny_diak()` | `isset`-védelem + a két helper (ez az utolsó kézi másolat) |
| `vespa_download_riport_tanev()` | `isset`-védelem + **a három hiányzó szűrő pótlása** a két helperrel |
| `vespa_download_riport_legnepszerubb_sportagak()` | `isset`-védelem + `institutionId` beolvasása + a két helper + a `die`-ág megszüntetése |

### 4. A tanév-riport hiányzó szűrői

A `vespa_download_riport_tanev()` fő lekérdezése megkapja mindkét helper
töredékét, tehát az `institutionId`, `disabilityGroupId` és `gender` végre
ténylegesen szűr.

**Az intézmény-szűrőt a második, `$allSchool` lekérdezésre is alkalmazni kell.**
Ez a lekérdezés listázza az összes intézményt, hogy a riportban a 0 diákot
indító iskolák is megjelenjenek. Ha egy intézményt kiválasztunk, de ez a
lekérdezés szűretlen marad, a riport egyetlen adatsor mellett több száz nullás
sort tartalmazna. Ide a `vespa_riport_intezmeny_szuro()` kerül; a
`vespa_riport_sportolo_szuro()` **nem** — a nem és a fogyatékossági csoport a
diák tulajdonsága, nem az intézményé, ezért csak a darabszámokat befolyásolja,
azt nem, hogy melyik iskola szerepel a listában.

Az `$allSchool` meglévő `vi.ins_state` szűrése változatlan marad (a helper nem
kezel megyét).

### 5. A „Legnépszerűbb sportágak" négy javítása

1. **A felület elküldi a hiányzó paramétereket.** A `getSubmitUri`
   `legnepszerubb_sportag` ága kiegészül a `schoolDistrict`, `institutionId`,
   `disabilityGroupId` és `gender` paraméterekkel — ugyanazzal az alakkal, ahogy
   az `iskola_sportoltatott_diakok` ága már ma is teszi.
2. **Az elgépelt változónév megszűnik.** A `:1025-1027` `$schoolDistrict`
   helyett a helper a helyesen beolvasott értéket kapja.
3. **Az intézmény-szűrés bekerül.** A lekérdezés már ma is join-olja a `vi`
   táblát (`:1007`), tehát a helper töredéke közvetlenül használható.
4. **Az `else die;` (`:982`) megszűnik.** A szűrés-felirat if-lánca a
   `szezon_riport`-nál már bevált négyágú alakra áll át:

   | `filter` | Felirat |
   |---|---|
   | `'country'` | `Legnépszerűbb országos sportágak` |
   | numerikus > 0 | `Legnépszerűbb megyei sportágak - <megye neve>` |
   | numerikus == 0 | `Legnépszerűbb megyei sportágak - összes` |
   | egyéb (`'all'`) | `Összes verseny` |

   Ma a `filter=0` („Összes megye") egyik ágra sem illik, ezért a függvény
   **`die`-ol: a riport üres választ ad, fájl nélkül.** Ez a felületről közvetlenül
   előidézhető.

   A felirathoz tartozó SQL-ág is kiegészül: a `filter == 0` mostantól
   `AND vc.contest_type=3` feltételt kap, különben a „megyei — összes" felirat
   olyan adat fölött állna, amiben az országos és a szabadidős versenyek is benne
   vannak. Ez ugyanaz a defektus, amit a `szezon_riport`-nál a `:104-108`
   kommentje dokumentál.

**Szándékosan nem változik:** az `'all'` ág továbbra sem tesz `contest_type`
megkötést ennél a riportnál, tehát a szabadidős versenyeket is beleszámolja. A
`szezon_riport`-nál az `'all'` szándékosan az 1,2,3 típusokat jelenti, mert az a
riport a diákolimpiáról szól — a sportág-népszerűségnél viszont az „Összes
verseny" tényleg mindent jelent. Ezt a különbséget nem egységesítjük, mert nem
szűrő-szétcsúszás, hanem riportonként eltérő, szándékos jelentés.

### 6. Amit nem érintünk

- A `:370`, `:540`, `:650` `else die;` ágak maradnak. Ezek csak akkor futnának,
  ha a `filter` nem `'all'`, nem `'country'` és nem numerikus — a felület viszont
  kizárólag ezt a három alakot küldi, tehát a felületről elérhetetlenek. A
  `:982`-től eltérően nem okoznak valós hibát.
- A riportok jelentése, oszlopai és számítási logikája.
- A `sport` / `sportEventId` szűrők, amelyek mindenhol helyesen működnek.

## Tesztelés

Új esetek a `tests/test-riport-periodus.php`-ban (a fájl már a riport-helperek
tesztje), vagy külön `tests/test-riport-szurok.php`-ban, a projekt bevált
`allit()` mintájával, WordPress nélkül futtatva:

- `vespa_riport_intezmeny_szuro()` négy kombinációja (egyik / másik / mindkettő /
  egyik sem), az SQL-töredékre, a paramétersorrendre és az érvénytelen
  bemenetekre.
- `vespa_riport_sportolo_szuro()` ugyanígy, plusz a nem fehérlistája: `'nő'` és
  `'férfi'` szűr, minden más (`'összes'`, `''`, `'FÉRFI'`, tetszőleges szöveg)
  nem szűr és nem is kerül paraméterként a lekérdezésbe.

**A riportfüggvények kimenete továbbra sem tesztelhető automatikusan** — nincs
helyi WordPress. A `php -l` és a kód-átolvasás az elérhető ellenőrzés, a tényleges
számokat kézi QA igazolja élesben. A javítás után viszont minden mutatott szűrőnek
**látható** hatása lesz, ami lényegesen könnyebben ellenőrizhető kézzel, mint a
mai néma viselkedés.

## Kockázatok

- **A számok meg fognak változni** azoknál a riportoknál, ahol eddig némán
  figyelmen kívül maradt egy beállított szűrő. Ez a javítás lényege, de a kézi
  QA-nál tudni kell, hogy az eltérés nem regresszió: eddig kaptunk rosszat.
- **Közös backend függvények.** A `verseny_versenyszam` és a `tanev` függvény
  intervallumos és tanév-alapú riportokat is kiszolgál; a `verseny_diak` és a
  `legnepszerubb_sportagak` viszont csak egyet-egyet. A módosításnak minden
  esetben a típus szerinti ágat kell eltalálnia.
