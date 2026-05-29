# Changelog

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
