# Changelog

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
