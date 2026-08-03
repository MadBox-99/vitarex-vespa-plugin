<?php
/**
 * A letöltési fájlnév képzésének unit tesztjei.
 * Futtatás: php tests/test-filename.php
 * WordPress nem kell hozzá: a tesztelt függvény tiszta.
 */

require_once __DIR__ . '/../includes/Core/vespa.filename.php';

$hibak = 0;

function allit($feltetel, $leiras)
{
    global $hibak;
    if ($feltetel) {
        echo "OK    " . $leiras . "\n";
    } else {
        echo "HIBA  " . $leiras . "\n";
        $hibak++;
    }
}

// ---- Szokásos versenynevek --------------------------------------------

allit(
    vespa_filename_resz('Nógrád megyei Atlétika Diákolimpia', 'verseny_1')
        === 'Nógrád megyei Atlétika Diákolimpia',
    'ekezetes versenynev valtozatlan marad'
);
allit(
    vespa_filename_resz('Atlétika Diákolimpia Országos Döntő', 'verseny_1')
        === 'Atlétika Diákolimpia Országos Döntő',
    'orszagos donto neve valtozatlan marad'
);

// ---- Fájlnévben tiltott karakterek ------------------------------------

allit(
    vespa_filename_resz('2024/2025 Atlétika', 'verseny_1') === '2024 2025 Atlétika',
    'per jel szokozre valt (kulonben alkonyvtarnak latszana)'
);
allit(
    vespa_filename_resz('Teszt: "A" *?<>|\\ verseny', 'verseny_1') === 'Teszt A verseny',
    'minden tiltott karakter kikerul'
);
allit(
    vespa_filename_resz("Sor\ntores\ttabbal", 'verseny_1') === 'Sor tores tabbal',
    'vezerlokarakter szokozre valt, nem ragasztja ossze a szavakat'
);
allit(
    vespa_filename_resz('  tobb    szokoz  ', 'verseny_1') === 'tobb szokoz',
    'tobbszoros szokoz osszevonodik es a szelek levagodnak'
);

// ---- Tartalék név ------------------------------------------------------

allit(
    vespa_filename_resz('', 'verseny_42') === 'verseny_42',
    'ures nevbol tartalek nev lesz'
);
allit(
    vespa_filename_resz('   ', 'verseny_42') === 'verseny_42',
    'csak szokozbol allo nevbol is tartalek nev lesz'
);
allit(
    vespa_filename_resz('///', 'verseny_42') === 'verseny_42',
    'csak tiltott karakterbol allo nevbol is tartalek nev lesz'
);
allit(
    vespa_filename_resz(null, 'verseny_42') === 'verseny_42',
    'null nevbol is tartalek nev lesz'
);

// ---- Hosszkorlát -------------------------------------------------------

$hosszu = str_repeat('a', 250);
allit(
    mb_strlen(vespa_filename_resz($hosszu, 'verseny_1')) === 100,
    'a tul hosszu nev 100 karakterre vagodik'
);
allit(
    mb_strlen(vespa_filename_resz(str_repeat('ő', 250), 'verseny_1')) === 100,
    'a vagas karakterben szamol, nem bajtban (ekezetes nev)'
);
allit(
    vespa_filename_resz(str_repeat('ab', 50), 'verseny_1') === str_repeat('ab', 50),
    'a pontosan 100 karakteres nev valtozatlan marad'
);

// ---- Összegzés ---------------------------------------------------------

echo "\n";
if ($hibak === 0) {
    echo "Minden teszt sikeres.\n";
    exit(0);
}

echo $hibak . " teszt elbukott.\n";
exit(1);
