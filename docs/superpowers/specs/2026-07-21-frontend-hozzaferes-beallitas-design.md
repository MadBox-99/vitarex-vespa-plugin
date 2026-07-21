# Front-end hozzáférés beállítása (terv)

Dátum: 2026-07-21
Állapot: jóváhagyott terv, implementációra vár

## Kiindulás

A `VESPA_LoginCustomiser::redirect_to_admin()` (`includes/Admin/login.customiser.php`)
a `template_redirect` hookon **minden** front-end kérést elterel: bejelentkezett
felhasználót a `wp-admin`-ba, kijelentkezettet a login oldalra. A VESPA-nak
emiatt jelenleg nincs publikusan elérhető front-end oldala.

Ez két konkrét hibát okoz.

**1. Az A4 csomag nem működik.** A `[vespa_szabadidos]` shortcode helyesen
renderel, de az őt tartalmazó oldal senkinek nem érhető el — a redirect előbb
elviszi a látogatót. Élesben ellenőrizve: a `/proba` oldal (page-id 204) a
plugin saját `?mvk_download_document=1` kivétel-paraméterével rendben betölt és
a shortcode is lefut, e paraméter nélkül viszont átirányít. Ez bizonyítja, hogy
az átirányítást ez a függvény okozza, nem külső plugin.

**2. Végtelen átirányítási hurok a szabadidős résztvevőknél.** A két szabály
egymást zárja ki, kivezető ág nélkül:

- `login.customiser.php:36` — bejelentkezett felhasználó a front-enden → `wp-admin`
- `szabadidos.isolation.php:17` — szabadidős résztvevő a wp-adminban → `home_url('/')`
- a kezdőlap front-end → újra `wp-admin` → újra kezdőlap → hurok

Ez kódolvasásból következik; éles szabadidős fiókkal nem lett lefuttatva.

## Cél

Adminisztrátor által kezelhető felület, amelyen kijelölhető, mely WP-oldalak
érhetők el publikusan a redirect ellenére — és ezzel együtt a fenti hurok
megszüntetése.

## Nem cél (ebben a körben)

- Bejelentkezés utáni átirányítás szerepenkénti beállítása
- Login oldal testreszabása (logó, CSS, egyedi login URL)
- „Minden oldal publikus" főkapcsoló. Kényelmes lenne, de egyetlen
  kattintással kinyitná az egész rendszert, és a feladatot a pipa-lista is
  megoldja.

## Adatmodell

Egyetlen WP option, `vespa_frontend_access`:

```php
array(
    'public_page_ids'            => array(204, ...), // publikusra jelölt oldalak
    'szabadidos_landing_page_id' => 204,             // 0 = kezdőlap
)
```

Mentéskor sanitizálás: `intval`, majd szűrés arra, hogy az ID létező,
`publish` státuszú, `page` típusú bejegyzés legyen.

## Komponensek

| Fájl | Szerep |
|---|---|
| `includes/Core/vespa.frontend.access.php` | *új* — döntési logika helperei |
| `includes/Admin/frontend.access.settings.php` | *új* — admin menü, oldal, AJAX mentés |
| `templates/frontend_access_settings.php` | *új* — pipa-lista, legördülő, Mentés gomb |
| `includes/Admin/login.customiser.php` | *módosul* — kivételek a redirectben |
| `includes/Admin/szabadidos.isolation.php` | *módosul* — a beállított oldalra irányít |

A döntési logika azért kerül külön Core fájlba, mert két helyről kell (a
redirect és az izoláció is használja), így az admin felülettől függetlenül
olvasható és módosítható marad.

A megvalósítás a plugin meglévő mintáját követi: `add_menu_page` +
`vespa_load_template()` + nonce-olt AJAX mentés a `vespa-ajax-form.js`-sel,
ahogy a `szabadidos.admin.php` teszi.

## Döntési logika

Helper: `vespa_frontend_is_public_request()` — igaz, ha `is_singular()` és az
aktuális bejegyzés ID-ja szerepel a `public_page_ids` listában.

A `redirect_to_admin()` új sorrendje:

1. meglévő `mvk_download_document` / `mvk_export_voluntaries` /
   `mvk_import_voluntaries` kivételek — változatlanul
2. ha `is_admin()` → nincs teendő
3. ha publikus oldal → átengedjük, bejelentkezve és anélkül egyaránt
   (így az adminisztrátor is meg tudja nézni a nevezési oldalt)
4. ha bejelentkezett szabadidős résztvevő → átengedjük; őt soha nem küldjük
   `wp-admin`-ba. Ez a lépés töri meg a hurkot.
5. egyébként a mai viselkedés: bejelentkezve `wp-admin`, kijelentkezve login

A 4. lépés szándékosan a teljes front-endre szól, nem csak a publikus oldalakra.
A szigorúbb változat — a résztvevőt nem publikus oldalról a landing page-re
irányítani — új hurok-kockázatot vinne be: ha a landing page értéke `0`
(kezdőlap), és a kezdőlap nincs bepipálva publikusnak, a szabály önmagát hívná
körbe. A megengedő változatnál ilyen függőség nincs, a hurokmentesség pedig nem
a konfigurációtól függ. A kockázat cserébe alacsony: a front-enden csak a
téma oldalai vannak, minden VESPA-adat a `wp-admin`-ban él, ahonnan a résztvevő
továbbra is ki van zárva.

A `szabadidos.isolation.php` a wp-admin próbálkozást a beállított
`szabadidos_landing_page_id` oldalra irányítja. Ha nincs beállítva, vagy az
oldal időközben megszűnt, `home_url('/')`-re esik vissza.

## Élek és hibakezelés

**A landing page-nek publikusnak kell lennie.** Ha mentéskor a kiválasztott
szabadidős landing page nincs bepipálva a publikus listában, a mentés hibát ad
és nem megy végbe. Nem a hurok miatt: a résztvevő-átengedés (lásd a döntési
logika 4. lépését) attól függetlenül hurokmentes. A valódi ok, hogy a landing
page egyben a regisztrációs oldal, és aki még nem regisztrált, az anonim
látogató — rá az átengedés nem vonatkozik. Nem publikus oldal esetén tehát
soha senki nem tudna regisztrálni.

További esetek:

- A landing page utólagos törlése vagy piszkozatba tétele: a rendszer csendben
  `home_url('/')`-re esik vissza, a beállító oldal pedig figyelmeztetést mutat.
- A listában csak `publish` státuszú `page` jelenik meg.
- Mentés: `check_ajax_referer` nonce + `current_user_can('manage_options')`
  ellenőrzés. A beállító oldal megjelenítése szintén `manage_options`, mert ez
  hozzáférési határt állít.

## Verifikáció

A pluginban nincs teszt-infrastruktúra (nincs `tests/`, `phpunit.xml`,
`composer.json`), ezért a verifikáció kézi ellenőrzőlista, élesítés után
böngészőből lefuttatva:

1. Kijelentkezve: publikusra jelölt oldal betölt; nem publikus oldal továbbra is
   a login oldalra irányít.
2. Adminként: publikus oldal betölt, nem pattan a `wp-admin`-ba.
3. Szabadidős fiókkal: belépés után a landing page jön; `wp-admin` megnyitása a
   landing page-re irányít; **nincs átirányítási hurok**.
4. Adminként a `/wp-admin/` és a meglévő VESPA oldalak változatlanul működnek.
5. Mentés-validáció: nem publikus oldal landing page-nek választása hibát ad.

## Kapcsolódó, külön munka

Ugyanebben a munkamenetben, de ettől függetlenül javítva lett a klasszikus
szerkesztő összeomlása (`Cannot read properties of undefined (reading
'getSelection')`): a `plugin.assets.php` minden admin oldalon betöltött egy
CDN-es TinyMCE 6.8.3-at, ami elrontotta a `window.tinymce.baseURL`-t, így a WP
beépített TinyMCE 4.9.11-e a témáját 404-es útvonalról kérte. A javítás a CDN-es
TinyMCE elhagyása és a `wp_editor()` használata. Ez a változtatás még nincs
kitelepítve.
