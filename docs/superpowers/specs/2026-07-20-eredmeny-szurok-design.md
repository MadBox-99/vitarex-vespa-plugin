# A2 — Bővített szűrési funkciók az eredménylistákban (design)

**Dátum:** 2026-07-20
**Csomag:** 3. (A2)
**Forrás tétel:** „Vespa fejlesztés, 2026.07.17..md" — A2

## Cél

A meglévő eredménylisták szűrésének bővítése két irányban:
- **(a)** külön szűrés a gyermek *feltöltött eredményére* és magára *a gyermekre* (név szerint);
- **(b)** szűrés sportág–versenyszám szerint.

A szűrők a jelenlegi listanézetekbe épülnek be, nem új felületként.

## Hatókör — három nézet, eltérő mechanizmus

| Nézet | Fájl | (b) sportág–versenyszám | Gyermek név | Van-e eredmény | Helyezés | Eredmény-érték |
|---|---|---|---|---|---|---|
| Verseny eredmények (fő) | `templates/results_dashboard.php` + `includes/Ajax/ajax.contest_results.php` | ✅ versenyen belül | ✅ | ✅ | ✅ | ✅ (szöveg-keresés) |
| Riport | `templates/riports_dashboard.php` + `includes/Export/download_riports.php` | ✅ versenyszám legördülő | — | — | — | — |
| Verseny szerinti rács | `templates/contest_results.php` | — (eleve 1 versenyszám) | ✅ | ✅ (megjelent) | rendezés már van | — |

**Indoklás a kihagyásokra:** a riport aggregált statisztika/export, ezért csak az A2(b) sportág–versenyszám értelmezhető benne. A rács eleve egyetlen versenyszámé, ezért ott nincs versenyszám-szűrő.

## Adatmodell (adott, nem változik)

- Eredmények: `vespa_constest_events_results.result` — **JSON-tömb**, `athlete_id` kulccsal (`[{"athlete_id":"41233","megjelent":"true","helyezes":"2","eredmeny":"14.84"}, ...]`). A tábla-szintű `athlete_id` oszlop használaton kívül (0). A soronkénti szűrés emiatt PHP-ban, `json_decode` után történik — nem SQL-ben.
- Sportág: `vespa_sports` (`sport_id`, `sport_name`).
- Versenyszám: `vespa_sport_events` (`sport_event_id`, `sport_id`, `sport_event_name`).
- Verseny↔sportág+versenyszám kötés: `vespa_constest_events` (`id` = contest_event_id, `contest_id`, `sport_id`, `event_id`).
- Sportoló: `vespa_athletes` (`athlete_id`, `athlete_name`, ...).

## Architektúra-döntés

**A megközelítés:** a fő nézet szűrése **szerveroldali paraméterekkel a meglévő AJAX-ban**, a rendezés-mechanizmus mintájára. A JSON-blob érintetlen marad, nincs séma-migráció (a normalizálás — „C" opció — az A2 hatókörén kívül). A rács szűrése kliensoldali Vue-szűrés a már betöltött adaton (ott ez a természetes minta).

**Eredmény-érték szűrő:** egyszerű **részszöveg-egyezés** az `eredmeny` értékre (nem numerikus tól–ig), mert a mértékegység és a formátum versenyszámonként eltér (idő vs. táv vs. darab, pl. `1:23.45`), így a numerikus tartomány megbízhatatlan lenne. A **helyezés** viszont tiszta szám, arra numerikus tól–ig szűrő jön.

---

## 1. Fő „Verseny eredmények" nézet — szűrő-sor

**Hol:** `results_dashboard.php` jobb oszlopa (az AJAX-eredmények helye); backend a `vespa_get_contest_results` (`includes/Ajax/ajax.contest_results.php`) bővítése.

**Interakció:** sportág-fül → verseny kiválasztása → AJAX betölti az eredményeket. Az AJAX-válasz mostantól a **szűrő-sort is tartalmazza** (a kiválasztott verseny versenyszámaival feltöltve), fölötte a táblázattal. A szűrők ugyanúgy újrahívják az AJAX-ot, mint a rendezés — a szűrő és a rendezés együtt él.

**Szűrő-mezők (mind szerveroldalon, `json_decode` után alkalmazva a sor-építés előtt):**

| Mező | AJAX-paraméter | Vezérlő | Viselkedés |
|---|---|---|---|
| Versenyszám | `sport_event_id` (int) | legördülő: „Összes versenyszám" + a verseny eseményei | egy versenyszám-blokkra szűkít; váltáskor auto-submit |
| Gyermek neve | `athlete_name` (string) | szövegmező | soronkénti részszöveg-egyezés a névre (kis/nagybetű-független) |
| Van-e eredmény | `van_eredmeny` (`''`/`1`/`0`) | legördülő: Mind / Van eredmény / Nincs eredmény | „van" = a sorhoz van nem üres `helyezes` vagy nem üres `eredmeny` |
| Helyezés tól | `helyezes_tol` (int) | szám-mező | `helyezes >= tol` (üres = nyitott) |
| Helyezés ig | `helyezes_ig` (int) | szám-mező | `helyezes <= ig` (üres = nyitott) |
| Eredmény | `eredmeny_kereses` (string) | szövegmező | részszöveg-egyezés az `eredmeny` értékre |
| — | — | „Szűrés" + „Szűrők törlése" gomb | alkalmaz / mind alaphelyzetbe |

**Versenyszám-opciók forrása:** a `vespa_get_contest_results` a kiválasztott `contest_id`-hez lekérdezi a `vespa_constest_events` ⨝ `vespa_sport_events` sorokat, és ezekből építi a legördülő `<option>`-öket (kiválasztott érték megőrizve).

**Szűrés-logika (PHP, a JSON-tömb minden sorára):**
- `sport_event_id`: ha megadva, csak az az esemény-blokk jelenik meg (a `contest_event_id` → `vespa_constest_events.event_id` egyezés alapján).
- `athlete_name`: a sportoló nevének `mb_stripos`-alapú részszöveg-egyezése.
- `van_eredmeny=1`: `helyezes` vagy `eredmeny` nem üres; `=0`: mindkettő üres.
- `helyezes_tol`/`helyezes_ig`: numerikus tartomány a `helyezes`-re (nem numerikus/üres helyezés kiesik, ha bármely határ meg van adva).
- `eredmeny_kereses`: `mb_stripos` az `eredmeny` értékre.

**Üres állapot:** ha a szűrők után egy blokk/sor sem marad: „Nincs a szűrőknek megfelelő eredmény."

## 2. Riport generátor — versenyszám legördülő

**Hol:** `riports_dashboard.php` szűrő-űrlap (a meglévő Sport-választó mellé) + `download_riports.php` GET-lekérdezés.

**Kaszkád:** a Versenyszám legördülő a kiválasztott **Sport**-tól függ — sportág választásakor feltöltődik az adott sportág `vespa_sport_events` versenyszámaival (kliensoldalon, a Vue-adatba előre betöltött sportág→versenyszám listából). Alapérték „Összes versenyszám"; sportág nélkül üres/inaktív. A GET-paraméter neve `sport_event_id`.

**Backend:** a `download_riports.php`-ban a meglévő Sport-szűrő mellé egy `sport_event_id` feltétel: `AND vespa_constest_events.event_id = %d` (csak ha megadva és > 0), a meglévő `vespa_constest_events` join-nál. „Összes versenyszám" (0/üres) = a jelenlegi viselkedés változatlanul.

## 3. „Verseny szerinti" rács — keresés a rögzítő felületen

**Hol:** `contest_results.php` (Vue-alapú eredmény-rögzítő rács egy versenyszámra, fogyatékossági csoport + nem szerint csoportosítva).

**Szűrők (kliensoldali Vue-szűrés a már betöltött adaton):**

| Mező | Vezérlő | Viselkedés |
|---|---|---|
| Sportoló neve | szövegmező | a rács sorait szűri név szerint (részszöveg), a csoport-fejlécek megmaradnak |
| Van-e eredmény | legördülő: Mind / Megjelent / Nem jelent meg | a `megjelent` mező szerint szűr |

**Versenyszám-szűrő itt nincs** (a rács eleve egyetlen versenyszámé). A helyezés-rendezés már létezik, marad.

**Üres állapot:** ha a keresés semmit nem hagy: „Nincs a keresésnek megfelelő sportoló."

## Tesztelés / ellenőrzés

A projekt konvenciója szerint (nincs automata teszt-suite): **`php -l` + `grep` + manuális böngésző**.

**Statikus:** minden módosított PHP-fájlra `php -l`; `grep` az új szűrő-paraméterekre és a `$wpdb->prepare` használatra.

**Biztonság:**
- szabadszöveges mezők (`athlete_name`, `eredmeny_kereses`): `$wpdb->prepare` + wildcard-escape ott, ahol SQL-hez érnek; PHP-oldali szűrésnél a bemenet szanálva, a kimenet `esc_html`.
- numerikus mezők (`helyezes_tol/ig`, `sport_event_id`): `intval` / `%d`.
- nonce + capability a meglévő minta szerint (`eredmenyek_megtekintese` a fő nézetnél, `riportalas` a riportnál).

**Manuális forgatókönyvek (élesítés előtt):**
1. **Fő nézet:** verseny kiválasztása → versenyszám-szűrő egy blokkra szűkít; név-keresés kiszűr egy sportolót; „Nincs eredmény" szűrő; helyezés 1–3 (dobogós); eredmény részszöveg; „Szűrők törlése" visszaáll; szűrő + rendezés együtt működik.
2. **Riport:** sportág választása feltölti a versenyszám-legördülőt; versenyszám-szűréssel a helyes export; „Összes versenyszám" a régi viselkedést adja.
3. **Rács:** név-keresés és „Megjelent" szűrő a csoport-fejlécek megtartásával; üres állapot.

## Nem cél (YAGNI)

- Eredmények normalizálása külön táblába (JSON-blob marad).
- Numerikus eredmény-érték tartomány-szűrő (a mértékegység-heterogenitás miatt).
- Versenyeken átívelő eredmény-keresés a fő nézetben (a versenyszám-szűrő a kiválasztott versenyen belül szűkít).
- Gyermek/eredmény soronkénti szűrők a riportban (aggregált nézet).
