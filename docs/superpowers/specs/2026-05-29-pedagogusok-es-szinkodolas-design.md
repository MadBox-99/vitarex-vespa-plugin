# Pedagógusok a nevezésnél + háromállapotú színkódolás — tervezési dokumentum

Dátum: 2026-05-29

Két, egymástól független fejlesztés a VESPA pluginban.

---

## 1. funkció — Pedagógusok megadása a verseny-nevezésnél

### Cél

A verseny nevezési oldalán (testnevelő/iskolaigazgató nézet) a kísérők mellett a
pedagógusokat is meg lehessen adni, **tetszőleges számban**. Amíg az adott
iskola az adott versenyre nem adott meg legalább egy pedagógust, **ne lehessen
sportolót nevezni**.

### Adattárolás

Új tábla: **`vespa_contest_teachers`** — pedagógusonként egy sor.

| oszlop | típus | megjegyzés |
|---|---|---|
| `id` | BIGINT AUTO_INCREMENT PK | |
| `contest_id` | INT NOT NULL | a verseny azonosítója |
| `school_id` | INT NOT NULL | az iskola azonosítója |
| `teljes_nev` | VARCHAR(200) NOT NULL | |
| `mobil` | VARCHAR(50) NOT NULL | |
| `email` | VARCHAR(200) NOT NULL | |
| `szuletesi_hely` | VARCHAR(200) NOT NULL | |
| `szuletesi_ido` | DATE NOT NULL | |
| `iskola_neve` | VARCHAR(255) NOT NULL | |

Index: `(contest_id, school_id)`.

A séma a `database/changes.sql` végére kerül dátumozott bejegyzésként (a plugin
kézzel futtatott SQL changelogot használ, nincs automatikus migráció).

**Indok a külön táblára (nem az `escort_data` blobba):** a meglévő kísérő-űrlap
mentéskor a teljes `escort_data` tömböt újraépíti, ezért egy parallel űrlap
felülírná a pedagógusokat. A külön tábla ezt kizárja, a nevezés-feltétel
lekérdezése pedig egy egyszerű `COUNT(*)`.

### UI

Hely: [templates/contest_view_entering.php](../../../templates/contest_view_entering.php),
a „Versenyszámok" blokk **elé** kerül egy új **„Pedagógusok"** szekció.

- Soronként 6 mező: teljes név, mobil, email, születési hely, születési idő
  (date input), iskola neve.
- **„➕ Pedagógus hozzáadása"** gomb új üres sort fűz a listához; soronként
  törlés gomb.
- Betöltéskor a `vespa_contest_teachers`-ből az iskola+verseny pedagógusai
  feltöltik a sorokat. Ha még nincs egy sem, egy üres sor jelenik meg.
- Mentés: „Pedagógusok mentése" gomb → AJAX.

### Mentés (AJAX)

Új action: **`save_teachers`** (új fájl: `includes/Ajax/ajax.save_teachers.php`).
A fájl automatikusan betöltődik — a plugin a `vitarex-vespa-plugin.php`-ben
`glob`-bal beolvas minden `includes/Ajax/*.php` fájlt, így külön regisztráció
nem kell, csak a fájl `add_action('wp_ajax_save_teachers', ...)` hívása.

- Jogosultság: csak `TESTNEVELO` szerep és csak a saját iskolájára
  (`vespa_get_my_school_id()`), a `save_escorts` mintájára.
- Validáció: **mind a 6 mező kötelező** minden beküldött sorban. Üres sorokat
  (minden mező üres) figyelmen kívül hagyunk. Részben kitöltött sor → hiba.
- Mentési stratégia: a tranzakció egyszerűségéért az adott `contest_id`+`school_id`
  meglévő pedagógus-sorait töröljük, majd a beküldött (érvényes) sorokat
  beszúrjuk. Így a kliens mindig a teljes aktuális listát küldi.
- Válasz a `save_escorts`-éhoz hasonló JSON (siker/hibák), a hibákat a meglévő
  `.ajax-form` hibamegjelenítő logika kezeli.

### Nevezés-blokkolás

A sportoló-nevezést (és **csak** azt) blokkoljuk, amíg nincs pedagógus.

- **Backend (kötelező védelem):**
  [includes/Ajax/contest.signup.php](../../../includes/Ajax/contest.signup.php)
  `athletes_signup()` metódusában, az „add" ág **előtt** (a meglévő dátum- és
  létszám-ellenőrzések mellé) új ellenőrzés: ha
  `SELECT COUNT(*) FROM vespa_contest_teachers WHERE contest_id=%d AND school_id=%d`
  nulla, akkor `success=false`, üzenet: *„Előbb add meg legalább egy pedagógust
  a nevezéshez!"*. A „remove" (levétel) ágat **nem** blokkoljuk.
- **Frontend (kényelmi):** a nevezési oldalon, ha nincs pedagógus, a „Nevezés"
  gombok / `.athlete-entry` linkek kattintásakor a fenti üzenet jelenik meg
  (a backend hibaüzenet amúgy is megjelenik `alert`-ben — lásd
  [js/vespa-admin.js](../../../js/vespa-admin.js) `athlete-entry` kezelő). Külön
  frontend állapot opcionális; a backend a biztosíték.

### Kihatás

- Csak a `is_final=1` versenyek nevezési nézetét érinti (a nem véglegesített
  verseny a `contest_view_add_events.php`-t tölti be, ott nincs nevezés).
- A kísérő-űrlap (`save_escorts`, `escort_data`) változatlan marad.

---

## 2. funkció — Háromállapotú színkódolás a verseny-listában

### Cél

A [templates/contest_list.php](../../../templates/contest_list.php) verseny-tábláiban
a versenynév cellájának háttérszíne három állapotot jelezzen:

- **Piros (`#ec5a64`)** — a verseny napja már **elmúlt** (`end_at < most`).
- **Zöld (`#63c27c`)** — `is_final=1` és épp **nyitva a nevezés**
  (`school_entry_start_at <= most <= school_entry_end_at`).
- **Kék (`#5bc0de`)** — minden más, jövőbeli állapot: nincs véglegesítve, **vagy**
  a nevezés még nem indult, **vagy** a nevezés már lezárult, de a verseny napja
  még nem volt meg.

### Megvalósítás

Új segédfüggvény az `includes/Core/functions.php`-ban:

```php
function vespa_contest_status_color($contest) {
    $now = date('Y-m-d H:i:s');
    // PIROS – a verseny napja már elmúlt
    if ($contest->end_at < $now) {
        return '#ec5a64';
    }
    // ZÖLD – véglegesített és épp nyitva a nevezés
    if ($contest->is_final
        && $contest->school_entry_start_at <= $now
        && $contest->school_entry_end_at  >= $now) {
        return '#63c27c';
    }
    // KÉK – létrehozva, de még nem nyitott (vagy nevezés már lezárult, de a verseny még jövőbeli)
    return '#5bc0de';
}
```

A `contest_list.php`-ben a **4× ismétlődő** inline feltételt
(`Országos / Megyei / Regionális / Szabadidősport` táblák versenynév-cellája)
ezzel a függvényhívással váltjuk ki:

```php
<td style="background-color: <?php echo vespa_contest_status_color($race); ?>">
```

### A kiértékelés sorrendje fontos

1. ha `end_at < most` → piros (felülír mindent, a verseny lezajlott),
2. különben ha véglegesített és nyitva a nevezés → zöld,
3. különben → kék.

Így a „nevezés lezárult, de a verseny napja még nem volt meg" eset kékként
jelenik meg (a felhasználói döntés szerint), és csak a ténylegesen lezajlott
versenyek pirosak.

---

## Tesztelés / ellenőrzés

A plugin nem rendelkezik automatizált tesztkészlettel; az ellenőrzés manuális:

**1. funkció:**
- Pedagógus nélkül a sportoló-nevezés `alert`-tel elutasít (backend hiba).
- Egy pedagógus felvétele + mentés után a nevezés enged.
- Több pedagógus hozzáadása/törlése, újratöltés után helyesen visszatöltődik.
- Hiányos sor mentése hibát ad (mind a 6 mező kötelező).
- Csak a saját iskolára menthető (jogosultság).

**2. funkció:**
- Lejárt verseny (`end_at` múlt) → piros.
- Nyitott nevezésű, véglegesített verseny → zöld.
- Jövőbeli, nem véglegesített / még nem nyitott / nevezés lezárult de jövőbeli
  verseny → kék.
- Mind a 4 táblázat (Országos/Megyei/Regionális/Szabadidősport) egységesen
  ugyanazt a logikát használja.

## Érintett fájlok összefoglalva

- `database/changes.sql` — új `vespa_contest_teachers` tábla (dátumozott bejegyzés).
- `includes/Ajax/ajax.save_teachers.php` — **új**, `save_teachers` action + segédlekérdezés
  (automatikusan betöltődik a `glob`-os ajax-loader miatt, nincs külön regisztráció).
- `includes/Core/functions.php` — `vespa_contest_status_color()` segédfüggvény;
  esetleg pedagógus-lekérdező segédfüggvény (`vespa_get_teachers($school_id, $contest_id)`).
- `includes/Ajax/contest.signup.php` — pedagógus-feltétel az `athletes_signup`-ban.
- `templates/contest_view_entering.php` — új „Pedagógusok" szekció + űrlap.
- `templates/contest_list.php` — a 4 inline színfeltétel cseréje függvényhívásra.
- `js/vespa-admin.js` — dinamikus sorkezelés + `save_teachers` küldés (+ opc. frontend jelzés).
