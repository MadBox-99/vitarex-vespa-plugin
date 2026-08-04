# Helyi WordPress teszt környezet (Herd)

Ez a könyvtár egy eldobható, újraépíthető WordPress példányt ír le, amelyben a
VESPA plugin **valódi adaton** futtatható. Eddig a riportokat csak élesben
lehetett ellenőrizni; itt egy parancsból végigfut mind.

## Mit épít fel

| Elem | Érték |
|---|---|
| Könyvtár | `~/Herd/fodisz-teszt` |
| Cím | `http://fodisz-teszt.test` |
| WordPress | 6.8.3 (hu_HU) — ugyanaz a `db_version`, mint az élesen, így nincs migráció |
| Adatbázis | `fodisz_teszt`, a `fodisz_vespa` másolata |
| Fiók | `helyi_admin` / `teszt123` |
| Pluginok | VESPA plugin, megtekintés-számláló, The Events Calendar |

Az adatbázis a `fodisz_vespa` **másolata**: a tesztek soha nem írnak az
eredetibe, az marad a visszaállítás alapja.

## Előfeltételek

- Fut a Herd MySQL szolgáltatása (`127.0.0.1:3306`, `root`, jelszó nélkül)
- Létezik a `fodisz_vespa` adatbázis. Ha nem, töltsd be a repóban lévő dumpból:

  ```bash
  mysql -h 127.0.0.1 -u root -e "CREATE DATABASE fodisz_vespa"
  mysql -h 127.0.0.1 -u root fodisz_vespa < fodisz_vespa.sql
  ```

A `wp-cli` hiányzik? Az építő szkript letölti magától a `~/.local/bin/wp` helyre.

## Használat

```bash
./kornyezet-epit.sh          # felépítés nulláról (meglévőt újraépít)
./kornyezet-visszaall.sh     # csak az adatbázis visszaállítása, gyorsabb
```

Az építő szkript idempotens: meglévő WordPress-t nem tölt le újra, de az
adatbázist mindig frissen klónozza.

## Riportok ellenőrzése

```bash
cd ~/Herd/fodisz-teszt

./riport-matrix.sh                                   # mind a 27 kombináció
./riport-ellenoriz.sh tanev_diakolimpia_diakok series=3   # egyetlen riport
```

A `riport-ellenoriz.sh` három dolgot néz meg, ebben a sorrendben:

1. a fájl `PK\x03\x04` ZIP-fejléccel kezdődik-e — az XLSX ZIP-konténer, tehát
   bármilyen elé írt PHP-figyelmeztetés azonnal kibukik;
2. van-e a tartalomban `Warning:` / `Notice:` / `_doing_it_wrong` nyom;
3. megnyitható-e a munkafüzet, és hány sort tartalmaz.

Ez pontosan az a hibaosztály, ami élesben sérült XLSX-et okoz. A `wp-config.php`
ezért **szándékosan** `WP_DEBUG_DISPLAY = true` beállítással készül: így a hiba
látszik, nem pedig némán elrejtőzik.

## Szűrő hatásának bizonyítása

A sorok száma önmagában félrevezető: több riport minden intézményt kilistáz,
ezért a sorszám akkor is változatlan marad, ha a szűrő működik. A
`szuro-hatas.php` ezért a **számokat** hasonlítja össze:

```bash
cd ~/Herd/fodisz-teszt
php szuro-hatas.php "tanev_diakolimpia_diakok series=3" \
                    "tanev_diakolimpia_diakok series=3 gender=nő"
```

Mért példák a valódi adaton:

| Összehasonlítás | Összeg |
|---|---|
| `tanev_diakolimpia_diakok series=3` | 5784 |
| ugyanaz `gender=nő` szűréssel | 1768 |
| ugyanaz `disabilityGroupId=1` szűréssel | 4758 |
| `tanev_versenyen_indult_iskolak series=3` (csak szezon) | 897 |
| ugyanaz `series=0` (nincs szezonszűrés) | 2123 |
| `series=0 year=2025` (csak naptári év) | 1084 |
| `series=3 year=2025` (metszet) | 664 |

A metszet mindkét külön szűrésnél kisebb — ez az összhang igazolja, hogy a
szezon és a naptári év tényleg egymástól függetlenül szűr.

## Fájlok

| Fájl | Szerep |
|---|---|
| `kornyezet-epit.sh` | teljes felépítés nulláról |
| `kornyezet-visszaall.sh` | csak adatbázis-visszaállítás |
| `riport-teszt.php` | egy riport generálása parancssorból, valódi WordPress-szel |
| `riport-ellenoriz.sh` | egy riport generálása + épség-ellenőrzés |
| `riport-matrix.sh` | az összes riport és szűrő-kombináció |
| `szuro-hatas.php` | két szűrés számainak összehasonlítása |
| `mu-plugins/teszt-biztonsag.php` | levélküldés tiltása, keresőmotor-kizárás, admin figyelmeztetés |
| `mu-plugins/teszt-cli-hitelesites.php` | parancssori jogosultság a riport-végponthoz |

## Amire figyelj

**Az adatbázis éles másolat.** Valódi diákok, pedagógusok és igazgatók adatait
tartalmazza, valódi e-mail címekkel. Ezért a `teszt-biztonsag.php` mu-plugin a
`pre_wp_mail` szűrővel **minden kimenő levelet eldob** — a levélküldést nem
konfiguráljuk rosszul, hanem elvágjuk. Ne kapcsold ki, és a környezetet ne tedd
elérhetővé a gépeden kívülről.

**A riport-végpont az `init` hookon fut**, az `init` viszont már a
`wp-load.php` betöltése közben eldördül. Ezért a parancssori jogosultságot nem
a hívó szkript állítja be, hanem a `teszt-cli-hitelesites.php` mu-plugin, jóval
korábbi ponton. Ez a mu-plugin csak parancssorból és csak a
`VESPA_TESZT_ADMIN` környezeti változóra aktív, böngészőből semmilyen hatása
nincs.

**A főoldal bejelentkezésre irányít át.** Ez nem hiba: a VESPA alkalmazás
kötelező bejelentkezést ír elő. A számláló beacon-ját ezért nem lehet itt
anonim böngészővel végigmérni — helyette a REST végpont hívható közvetlenül:

```bash
curl -X POST "http://fodisz-teszt.test/?rest_route=/fodisz-szamlalo/v1/megtekintes" \
     -d "post_id=161" -A "Mozilla/5.0 Teszt"
```

**Az `~/Herd/fodisz-teszt` könyvtár nincs verziókövetve.** Az összes érdemi
fájl itt, a plugin repójában van; a könyvtár bármikor eldobható és
újraépíthető.
