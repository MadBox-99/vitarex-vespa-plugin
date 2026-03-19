# Changelog

## [2.1.0] - 2026-03-19

### Javítások
- **PDF letöltés (versenykiírás, orvosi igazolás, beszámoló):** A PDF fájlok nem nyíltak meg letöltés után, mert a WordPress output buffer belekerült a PDF tartalmába és elrontotta azt. Mostantól a kimenet puffere tisztítva van a letöltés előtt, és a böngésző mindig letöltésként kezeli a fájlt (`Content-Disposition: attachment`).

### Új funkciók
- **Sportoló nemének megjelenítése a nevezettek listájában:** A verseny nevezettjeinek listájában mostantól látható a sportoló neme (férfi/nő) mind az admin, mind a pedagógus nézetben.
- **AJAX nevezettek lista javítás:** A nevezettek lekérdezés hibás JOIN-ja javítva (`event_id` -> `contest_event_id`), és a nem is megjelenik a sportoló neve mellett.

## [2.0.0]

- Alap verzió.
