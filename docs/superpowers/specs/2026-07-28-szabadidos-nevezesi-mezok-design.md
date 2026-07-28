# Szabadidős nevezési mezők — tervezés

Dátum: 2026-07-28

## A probléma

A szabadidős (type-4) külső nevezésnél a résztvevő ma egyetlen gombnyomással
nevez egy versenyszámra. A rendszer semmilyen további adatot nem kér tőle.

A valós szabadidős eseményekhez viszont eseményspecifikus adat kell. Példa
(FODISZ-szal a Tó körül, 2026. szeptember 26., Pákozdi Pagony):

- kerékpártípus: hagyományos / e-bike / tandem / handbike / egyéb
- ajándék póló mérete: S, M, L, XL, XXL
- GDPR-nyilatkozat, felelősségvállalási nyilatkozat, képfelvétel-engedély
- főzőversenyre nevező közösség neve

Ezek eseményenként mások. Nem lehet őket kódba drótozni: minden új verseny új
igényt hoz. Kell egy admin felület, ahol a szervező versenyenként szabadon
összeállítja a bekérendő mezőket, és ez a beállítás azonnal, AJAX-szal
mentődik, hogy félbehagyott szerkesztésnél se vesszen el munka.

## Amit építünk

Versenyenként tetszőleges számú, tetszőleges típusú nevezési mező vehető fel
az adminban. A résztvevő ezeket a nevezéskor tölti ki, versenyenként egyszer.
A válaszok megjelennek az admin nevezőlistájában és az XLSX exportban.

### Döntések

| Kérdés | Döntés |
|---|---|
| Rendszer természete | Általános mezőépítő, nem előre bedrótozott blokkok |
| Admin felület helye | „Szabadidős külső nevezés" menüoldal (nem a versenyszerkesztő) |
| Mezők szintje | Versenyszint; a résztvevő versenyenként egyszer tölti ki |
| Mezőtípusok | egyválasztós, többválasztós, szöveg, hosszú szöveg, szám, dátum, nyilatkozat |
| Utólagos módosítás | Puha törlés; a beadott válaszok megmaradnak; hiányzó adat jelezve az adminban |
| Mentés módja | Soronkénti azonnali AJAX mentés |

A versenyszerkesztő azért esett ki, mert az „Új verseny felvétele" űrlapnak
mentés előtt nincs `contest_id`-ja, amihez a mezőket kötni lehetne. A
szabadidős admin oldalon mindig létező versenyről van szó.

## Adatmodell

Két új tábla. A plugin konvenciója szerint `$wpdb->prefix` NÉLKÜL, `dbDelta`-val
létrehozva, a `vespa_szabadidos_install()` verziókapuján keresztül
(`vespa_szabadidos_db_version` `2` → `3`).

```sql
CREATE TABLE vespa_szabadidos_fields (
  field_id      bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  contest_id    bigint(20) unsigned NOT NULL,
  label         varchar(255) NOT NULL,
  field_type    varchar(20) NOT NULL,
  field_options text DEFAULT NULL,
  is_required   tinyint(1) NOT NULL DEFAULT 0,
  ordernum      int(11) NOT NULL DEFAULT 0,
  is_active     tinyint(1) NOT NULL DEFAULT 1,
  created_at    datetime NOT NULL,
  PRIMARY KEY  (field_id),
  KEY contest_id (contest_id, is_active, ordernum)
);

CREATE TABLE vespa_szabadidos_answers (
  answer_id      bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  participant_id bigint(20) unsigned NOT NULL,
  contest_id     bigint(20) unsigned NOT NULL,
  field_id       bigint(20) unsigned NOT NULL,
  answer_value   text DEFAULT NULL,
  updated_at     datetime NOT NULL,
  PRIMARY KEY  (answer_id),
  UNIQUE KEY uniq_reszt_mezo (participant_id, field_id),
  KEY contest_id (contest_id)
);
```

`field_type` megengedett értékei: `egyvalasztos`, `tobbvalasztos`, `szoveg`,
`hosszu_szoveg`, `szam`, `datum`, `nyilatkozat`.

`field_options`: soronként egy válaszlehetőség, `\n` elválasztóval. Csak
`egyvalasztos` és `tobbvalasztos` típusnál értelmes, egyébként NULL. Ez a
„Beszámoló kérdések" oldal megszokott beviteli módja.

`answer_value`: `tobbvalasztos` esetén JSON tömb (`["Boccia","Darts"]`),
minden más típusnál sima skalár érték. Azért nem `;`-vel összefűzött szöveg,
mert a válaszlehetőség szövegében is lehet pontosvessző, és az visszaolvasáskor
szétesne.

`contest_id` az `answers` táblában redundáns (a `field_id`-ból levezethető),
de nélküle az export minden sorhoz plusz join-t igényelne. Szándékos
denormalizáció.

`is_active = 0` a puha törlés. Az inaktív mező nem jelenik meg a front-enden,
de a rá adott válaszok megmaradnak és az exportban továbbra is látszanak.

### Elutasított alternatívák

**JSON oszlop a `vespa_external_entries`-en.** Nevezésenként (tehát
versenyszámonként) tárolna, holott versenyenként egyszer kérdezünk. A
mezőcímke későbbi módosítása visszamenőleg értelmezhetetlenné tenné a régi
adatot. Az XLSX-export oszlopait ki kellene találgatni.

**A meglévő `vespa_contests_questions` rendszer kiterjesztése.** Az globális
beszámoló-kérdés rendszer, nem versenyhez kötött, és nincs mezőtípusa. Az
átalakítása a meglévő beszámoló-funkciót kockáztatná.

## Admin felület

### Az oldal új tagolása

A `templates/szabadidos_admin.php` mai szerkezete: *Versenyek megnyitása*
táblázat, majd *Külső nevezők* (versenyválasztó + lista + export). Az új
tagolás:

1. **Versenyek megnyitása külső regisztrációra** — változatlan
2. **Verseny választó** — a mai versenyválasztó felfelé mozgatva, közössé téve
3. **Nevezési mezők** *(új)* — a választott verseny mezőszerkesztője
4. **Külső nevezők** — a lista és az export a választott versenyre

A versenyválasztó ma csak a *megnyitott* versenyeket listázza. A mezőket a
megnyitás előtt kell tudni beállítani, ezért a legördülő ezentúl az összes
type-4 versenyt hozza; a megnyitás állapotát a címkéjében jelzi, a már meglévő
dátum- és lejárat-jelölés mellett.

### A mezőszerkesztő

Soronként egy mező. Egy sor tartalma:

- **▲ ▼** sorrendgombok
- **Címke** szövegmező
- **Típus** legördülő (a hét típus)
- **Kötelező kitölteni** jelölőnégyzet
- **Válaszlehetőségek** textarea — csak `egyvalasztos`/`tobbvalasztos`
  típusnál látszik, típusváltáskor JS mutatja-rejti
- **Mentés** és **Törlés** gomb, mellettük állapotjelző

Az állapotjelző szövegei: „Nem mentett módosítás" → „Mentés…" → „Mentve ✓",
hiba esetén a szerver hibaüzenete.

**+ Új mező** azonnal egy üres, még nem mentett sort tesz a lista végére,
oldalújratöltés nélkül — így a többi sor félkész állapota sem vész el. Az új
sor `field_id`-ja 0; az első sikeres mentés után a szerver által visszaadott
azonosítót kapja meg.

**▲ ▼** a szomszédos sorral cserél `ordernum`-ot, és a cserét azonnal menti.
Szándékosan nem drag&drop: az a „Beszámoló kérdések" oldalon CDN-ről töltött
jQuery UI-t igényelne, amit nem viszünk tovább egy admin oldalra.

**Törlés** puha törlés. Ha a mezőre már érkezett válasz, a gomb megerősítést
kér, és a sor utána „Archivált — X válasz megmarad" állapotba kerül, ahonnan
visszakapcsolható. Ha még nincs rá válasz, a sor egyszerűen eltűnik (a rekord
ilyenkor valóban törölhető).

A mezősorokat JavaScript rajzolja ki egy, a PHP által beágyazott JSON
adatból — ugyanúgy, ahogy a „Beszámoló kérdések" oldal teszi. Így a sor
felépítése egyetlen helyen van leírva; ha a PHP is rajzolna sorokat, a
kétféle markupot örökké szinkronban kellene tartani. A JS az értékeket
`value`/`textContent` beállítással teszi be, nem `innerHTML`-lel.

### AJAX végpontok

Új fájl: `includes/Ajax/szabadidos.fields.php`. Azért külön fájl, mert a
`szabadidos.admin.php` ma a menüregisztrációt és egyetlen végpontot tartalmaz,
és négy továbbitól átláthatatlanná válna.

Mindegyik a meglévő `vespa_szabadidos_admin` nonce-ot ellenőrzi és
`VESPA_Roles::riportalas` jogosultságot vár — ugyanaz a minta, mint a
`vespa_szabadidos_toggle_open`-nél.

| Akció | Feladat |
|---|---|
| `vespa_szabadidos_field_save` | beszúr (`field_id = 0`) vagy frissít; visszaadja a mentett sort |
| `vespa_szabadidos_field_delete` | `is_active = 0`, vagy valódi törlés, ha nincs válasz |
| `vespa_szabadidos_field_restore` | archivált mező visszakapcsolása |
| `vespa_szabadidos_field_move` | két szomszédos sor `ordernum` cseréje |

Mindegyik ellenőrzi, hogy a `contest_id` valóban type-4 verseny, és hogy a
`field_id` ahhoz a versenyhez tartozik. Ez ugyanaz az IDOR-védelmi minta, mint
a `vespa_szabadidos_withdraw`-ban.

### Mentéskori ellenőrzés

- a címke nem lehet üres
- a típus a hét megengedett érték egyike
- `egyvalasztos`/`tobbvalasztos` esetén legalább egy nem üres válaszlehetőség
- `nyilatkozat` típus mindig kötelező; az adminban a jelölőnégyzet le van
  tiltva, a szerver pedig felülírja `is_required = 1`-re

## Front-end

### A nevezés folyamata

A „Nevezek" gomb ma egyetlen AJAX hívást indít. Ez marad, de eléje kerül egy
elágazás:

- Ha a versenynek **nincs** aktív mezője, vagy a résztvevő **erre a versenyre
  már kitöltötte** őket → változatlan viselkedés, a gomb azonnal nevez.
- Egyébként a gomb **kinyit egy űrlapot a verseny kártyáján belül** (nem modal,
  nem külön oldal). Kitöltés után a „Nevezés véglegesítése" gomb a válaszokat
  és a nevezést **egyetlen kérésben** küldi.

Az egy kérés lényeges: a válasz és a nevezés együtt keletkezik, így nem
maradhat félig kitöltött nevező. Ha a nevezés bármi miatt elbukik (betelt
létszám, lejárt verseny, dupla nevezés), a válaszok sem kerülnek be.

Az űrlap stílusa a `szabadidos_frontend.php` tetején definiált `tw-` prefixes
Tailwind osztálykészletet használja.

### Típusok megjelenítése

| Típus | Beviteli elem |
|---|---|
| `egyvalasztos` | rádiógombok |
| `tobbvalasztos` | jelölőnégyzetek |
| `szoveg` | `input[type=text]` |
| `hosszu_szoveg` | `textarea` |
| `szam` | `input[type=number]` |
| `datum` | `input[type=date]` |
| `nyilatkozat` | egyetlen kötelező jelölőnégyzet a címke szövegével |

### Kiszolgáló oldali ellenőrzés

A `vespa_szabadidos_signup` végpontban, a meglévő ellenőrzések után, a
beszúrás előtt:

- minden kötelező, aktív mező ki van töltve
- `egyvalasztos`/`tobbvalasztos` esetén minden beküldött érték szerepel a
  definiált válaszlehetőségek között
- `szam` numerikus, `datum` `Y-m-d` formátumú
- `nyilatkozat` értéke `1`

Hiba esetén mezőnkénti hibaüzenet megy vissza, és az űrlap megtartja a
kitöltött állapotát. A böngésző oldali `required` csak kényelmi funkció; az
érdemi ellenőrzés a szerveren van.

### Ismételt nevezés

Ha a résztvevő ugyanarra a versenyre másik versenyszámra is nevez, a rendszer
nem kérdez újra — a korábbi válaszai érvényben maradnak.

### Amit szándékosan kihagyunk

A résztvevő nem tudja utólag módosítani a beküldött válaszait. Elgépelt
pólóméretet az adminnak kell javítania. Ez külön felület lenne, és most nem
része a feladatnak.

Új mező felvétele után a már nevezett résztvevőket nem szólítjuk fel
pótlásra; a hiányzó adat csak az adminban látszik.

## Admin nevezőlista és XLSX export

A mai hét fix oszlop (Név, Szül. dátum, Nem, E-mail, Telefon, Versenyszám,
Nevezés dátuma) után a verseny mezői kerülnek dinamikus oszlopként.

Az oszlopkészletbe bekerül minden aktív mező, továbbá minden inaktív mező,
amire van válasz — utóbbi `(archivált)` jelöléssel a fejlécben. Ez a puha
törlés lényege: a régi adat az exportból sem tűnik el.

`tobbvalasztos` válasz megjelenítése: a JSON tömb elemei `, ` (vessző + szóköz)
elválasztóval összefűzve. `nyilatkozat`: `igen` / üres.

Ha egy résztvevőnél hiányzik kötelező válasz (mert a mezőt a nevezése *után*
vették fel), a cella üres marad, és a sor `hiányzó adat` jelzést kap.

A nevezők lekérdezése ma két helyen, egymással megegyező SQL-ként szerepel: a
`szabadidos_admin.php` sablonban és a `szabadidos.export.php`-ban. A dinamikus
oszlopok mindkettőbe kellenek, ezért a nevezők + válaszok lekérdezése **közös
segédfüggvénybe kerül** a `vespa.szabadidos.helpers.php`-be, és mindkét hely
azt hívja. Enélkül a két lekérdezés garantáltan szétcsúszna.

## Peremesetek

**Nevezés visszavonása.** A `vespa_szabadidos_withdraw` a nevezés sorát törli,
a válaszokat nem. Ha a résztvevő az összes nevezését visszavonja egy
versenyről, a válaszai megmaradnak; újbóli nevezéskor nem kérdezzük meg
újra őket. Ez összhangban van a „ne vesszen el semmi" elvvel, és az admin
listát nem zavarja: az a nevezésekből indul ki, tehát a nevezés nélküli
válasz egyszerűen nem jelenik meg.

**Több nevezés egy versenyen.** Aki ugyanarra a versenyre két versenyszámra
nevez, két sorral szerepel az admin listában és az exportban, mindkettőnél
ugyanazokkal a válaszokkal. Ez szándékos: a sor a nevezést azonosítja, nem a
résztvevőt.

**Mezők nélküli verseny.** Ha a versenyhez nincs aktív mező, a front-end
pontosan a mai módon viselkedik. A meglévő versenyek tehát változatlanul
működnek tovább.

**Típusváltás mentett mezőn.** Ha egy már kitöltött mező típusát megváltoztatják
(pl. `szoveg` → `egyvalasztos`), a korábbi válaszok szövegként megmaradnak,
és az exportban is úgy jelennek meg. Az admin mentéskor figyelmeztetést kap,
ha a mezőre már érkezett válasz.

## Ellenőrzés

Nincs helyi WordPress-környezet, ezért a `tests/test-frontend-access.php`
mintáját követjük: a WordPress nélkül futtatható logika külön, tiszta
függvényekbe kerül, és azokra írunk tesztet.

Tesztelhető egységek:

- mezőtípus érvényessége
- `field_options` szöveg felbontása válaszlehetőség-tömbbé (üres sorok
  eldobása, whitespace vágása)
- válaszérték ellenőrzése típus + válaszlehetőségek alapján (a hét típusra,
  kötelező és nem kötelező esetben)
- `tobbvalasztos` válasz JSON kódolása és visszaolvasása
- válaszérték megjelenítési formája (export/admin lista)

A DB-hez és AJAX-hoz kötött részt telepítés utáni kézi ellenőrzés fedi, ehhez
lépéssoros listát adunk a megvalósítási tervben.

## Érintett fájlok

| Fájl | Változás |
|---|---|
| `includes/Core/vespa.szabadidos.install.php` | két új tábla, verzió `2` → `3` |
| `includes/Core/vespa.szabadidos.fields.php` | **új** — tiszta, WordPress-független mezőlogika (típusok, validálás, formázás) |
| `includes/Core/vespa.szabadidos.helpers.php` | adatbázist érintő mező- és válaszkezelés, közös nevező-lekérdezés |
| `includes/Ajax/szabadidos.fields.php` | **új** — négy admin AJAX végpont |
| `includes/Ajax/szabadidos.entries.php` | a `signup` válaszokat is fogad és ellenőriz |
| `templates/szabadidos_admin.php` | átrendezés, mezőszerkesztő blokk, dinamikus oszlopok |
| `templates/szabadidos_frontend.php` | nevezési űrlap a „Nevezek" gomb mögött |
| `includes/Export/szabadidos.export.php` | dinamikus oszlopok, közös lekérdezés használata |
| `tests/` | **új** — a tiszta logika tesztjei |

A `vitarex-vespa-plugin.php` nem igényel módosítást: az `includes/Ajax`
könyvtár minden PHP fájlját `glob`-bal tölti be, tehát az új
`szabadidos.fields.php` magától bekerül.
