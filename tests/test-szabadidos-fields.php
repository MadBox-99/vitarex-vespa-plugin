<?php
/**
 * A szabadidős nevezési mezők tiszta logikájának unit tesztjei.
 * Futtatás: php tests/test-szabadidos-fields.php
 * WordPress nem kell hozzá: a tesztelt függvények tiszták.
 */

require_once __DIR__ . '/../includes/Core/vespa.szabadidos.fields.php';

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

// ---- Típusok -------------------------------------------------------------

allit(count(vespa_szabadidos_field_types()) === 7, 'het mezotipus van');
allit(vespa_szabadidos_field_type_valid('egyvalasztos'), 'egyvalasztos ervenyes tipus');
allit(vespa_szabadidos_field_type_valid('nyilatkozat'), 'nyilatkozat ervenyes tipus');
allit(!vespa_szabadidos_field_type_valid('valami'), 'ismeretlen tipus nem ervenyes');
allit(!vespa_szabadidos_field_type_valid(''), 'ures tipus nem ervenyes');
allit(vespa_szabadidos_type_has_options('tobbvalasztos'), 'tobbvalasztosnak van valaszlehetosege');
allit(!vespa_szabadidos_type_has_options('szoveg'), 'szovegnek nincs valaszlehetosege');

// ---- Válaszlehetőségek felbontása ---------------------------------------

allit(
    vespa_szabadidos_parse_options("S\nM\nL") === array('S', 'M', 'L'),
    'sortoresekkel elvalasztott lehetosegek'
);
allit(
    vespa_szabadidos_parse_options("S\r\nM\r\nL") === array('S', 'M', 'L'),
    'windows sorvegek is mukodnek'
);
allit(
    vespa_szabadidos_parse_options("  S  \n\n  M  \n") === array('S', 'M'),
    'ures sorok eldobva, whitespace levagva'
);
allit(
    vespa_szabadidos_parse_options("S\nM\nS") === array('S', 'M'),
    'ismetlodo lehetoseg csak egyszer szerepel'
);
allit(vespa_szabadidos_parse_options('') === array(), 'ures szovegbol ures tomb');
allit(vespa_szabadidos_parse_options(null) === array(), 'nullbol ures tomb');
allit(
    vespa_szabadidos_parse_options("Boccia; tollaslabda\nDarts") === array('Boccia; tollaslabda', 'Darts'),
    'a pontosvesszo a lehetoseg szovegeben megmarad'
);
allit(
    vespa_szabadidos_parse_options("A  B") === array('A B'),
    'a lehetoseg belsejeben levo tobbszoros szokoz egyre osszevonva'
);
allit(
    vespa_szabadidos_parse_options("A\tB") === array('A B'),
    'a lehetoseg belsejeben levo tabulator szokozze alakul'
);
allit(
    vespa_szabadidos_parse_options("A  B\nA B") === array('A B'),
    'az osszevonas utan azonossa valo sorok is deduplikalodnak'
);

// ---- Meződefiníció ellenőrzése ------------------------------------------

$e = vespa_szabadidos_validate_field('', 'szoveg', '', 0);
allit($e['ok'] === false, 'ures cimke elbukik');

$e = vespa_szabadidos_validate_field('   ', 'szoveg', '', 0);
allit($e['ok'] === false, 'csak whitespace cimke elbukik');

$e = vespa_szabadidos_validate_field('Cimke', 'ismeretlen', '', 0);
allit($e['ok'] === false, 'ismeretlen tipus elbukik');

$e = vespa_szabadidos_validate_field('Polomeret', 'egyvalasztos', '', 0);
allit($e['ok'] === false, 'valasztos tipus valaszlehetoseg nelkul elbukik');

$e = vespa_szabadidos_validate_field('Polomeret', 'egyvalasztos', "S\nM\nL", 1);
allit($e['ok'] === true, 'ervenyes egyvalasztos mezo atmegy');
allit($e['field']['field_options'] === "S\nM\nL", 'a lehetosegek normalizaltan mentodnek');
allit($e['field']['is_required'] === 1, 'a kotelezo jelzes megmarad');

$e = vespa_szabadidos_validate_field('  Csapatnev  ', 'szoveg', "figyelmen kivul", 0);
allit($e['field']['label'] === 'Csapatnev', 'a cimke whitespace-e levagva');
allit($e['field']['field_options'] === null, 'nem valasztos tipusnal nincs lehetosegek');
allit($e['field']['is_required'] === 0, 'nem kotelezo mezo jelzese megmarad');

$e = vespa_szabadidos_validate_field('Elfogadom a GDPR-t', 'nyilatkozat', '', 0);
allit($e['ok'] === true, 'nyilatkozat lehetosegek nelkul is ervenyes');
allit($e['field']['is_required'] === 1, 'a nyilatkozat mindig kotelezo lesz');

$e = vespa_szabadidos_validate_field(str_repeat('a', 255), 'szoveg', '', 0);
allit($e['ok'] === true, '255 karakteres cimke atmegy');

$e = vespa_szabadidos_validate_field(str_repeat('a', 256), 'szoveg', '', 0);
allit($e['ok'] === false, '256 karakteres cimke elbukik');

$e = vespa_szabadidos_validate_field(str_repeat('á', 255), 'szoveg', '', 0);
allit($e['ok'] === true, 'ekezetes 255 karakteres cimke is atmegy (mb_strlen)');

// ---- Válasz ellenőrzése --------------------------------------------------

$meretek = array('S', 'M', 'L');

$v = vespa_szabadidos_validate_answer('egyvalasztos', 1, $meretek, 'M');
allit($v['ok'] === true && $v['value'] === 'M', 'ervenyes egyvalasztos valasz');

$v = vespa_szabadidos_validate_answer('egyvalasztos', 1, $meretek, 'XXL');
allit($v['ok'] === false, 'listan kivuli egyvalasztos valasz elbukik');

$v = vespa_szabadidos_validate_answer('egyvalasztos', 1, $meretek, '');
allit($v['ok'] === false, 'kotelezo egyvalasztos ures valasza elbukik');

$v = vespa_szabadidos_validate_answer('egyvalasztos', 0, $meretek, '');
allit($v['ok'] === true && $v['value'] === null, 'nem kotelezo mezo uresen hagyhato');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 1, $meretek, array('S', 'L'));
allit($v['ok'] === true, 'ervenyes tobbvalasztos valasz');
allit(json_decode($v['value'], true) === array('S', 'L'), 'a tobbvalasztos valasz JSON tombkent tarolodik');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 1, $meretek, array('S', 'S'));
allit(json_decode($v['value'], true) === array('S'), 'az ismetlodo tobbvalasztos ertek egyszer tarolodik');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 1, $meretek, array('S', 'XXL'));
allit($v['ok'] === false, 'listan kivuli tobbvalasztos ertek elbukik');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 1, $meretek, array());
allit($v['ok'] === false, 'kotelezo tobbvalasztos ures valasza elbukik');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 0, $meretek, array());
allit($v['ok'] === true && $v['value'] === null, 'nem kotelezo tobbvalasztos uresen hagyhato');

$v = vespa_szabadidos_validate_answer('nyilatkozat', 0, array(), '1');
allit($v['ok'] === true && $v['value'] === '1', 'elfogadott nyilatkozat atmegy');

$v = vespa_szabadidos_validate_answer('nyilatkozat', 0, array(), '');
allit($v['ok'] === false, 'el nem fogadott nyilatkozat elbukik a nem kotelezo jelzes ellenere is');

$v = vespa_szabadidos_validate_answer('szam', 1, array(), '12');
allit($v['ok'] === true && $v['value'] === '12', 'ervenyes szam');

$v = vespa_szabadidos_validate_answer('szam', 1, array(), 'tizenketto');
allit($v['ok'] === false, 'nem numerikus szam elbukik');

$v = vespa_szabadidos_validate_answer('datum', 1, array(), '2026-09-26');
allit($v['ok'] === true && $v['value'] === '2026-09-26', 'ervenyes datum');

$v = vespa_szabadidos_validate_answer('datum', 1, array(), '2026-02-30');
allit($v['ok'] === false, 'nem letezo naptari nap elbukik');

$v = vespa_szabadidos_validate_answer('datum', 1, array(), '2026.09.26.');
allit($v['ok'] === false, 'rossz formatumu datum elbukik');

$v = vespa_szabadidos_validate_answer('szoveg', 1, array(), '  Sasok  ');
allit($v['ok'] === true && $v['value'] === 'Sasok', 'a szoveges valasz whitespace-e levagva');

$v = vespa_szabadidos_validate_answer('szoveg', 1, array(), '   ');
allit($v['ok'] === false, 'csak whitespace kotelezo szoveg elbukik');

// ---- Megjelenítés és visszaolvasás ---------------------------------------

allit(
    vespa_szabadidos_format_answer('tobbvalasztos', json_encode(array('Boccia', 'Darts'))) === 'Boccia, Darts',
    'tobbvalasztos valasz vesszovel osszefuzve jelenik meg'
);
allit(
    vespa_szabadidos_format_answer('tobbvalasztos', 'nem json') === 'nem json',
    'romlott JSON eseten a nyers ertek jelenik meg'
);
allit(vespa_szabadidos_format_answer('nyilatkozat', '1') === 'igen', 'elfogadott nyilatkozat megjelenitese');
allit(vespa_szabadidos_format_answer('nyilatkozat', '0') === '', 'el nem fogadott nyilatkozat uresen jelenik meg');
allit(vespa_szabadidos_format_answer('szoveg', null) === '', 'null valasz uresen jelenik meg');
allit(vespa_szabadidos_format_answer('szoveg', 'Sasok') === 'Sasok', 'szoveges valasz valtozatlanul jelenik meg');

allit(
    vespa_szabadidos_decode_answer('tobbvalasztos', json_encode(array('S', 'M'))) === array('S', 'M'),
    'tobbvalasztos valasz tombkent olvashato vissza'
);
allit(
    vespa_szabadidos_decode_answer('tobbvalasztos', null) === array(),
    'null tobbvalasztos valaszbol ures tomb'
);
allit(vespa_szabadidos_decode_answer('szoveg', null) === '', 'null skalar valaszbol ures szoveg');
allit(vespa_szabadidos_decode_answer('szoveg', 'Sasok') === 'Sasok', 'skalar valasz valtozatlanul olvashato vissza');

echo "\n" . ($hibak === 0 ? "Minden teszt sikeres.\n" : $hibak . " teszt elbukott.\n");
exit($hibak === 0 ? 0 : 1);
