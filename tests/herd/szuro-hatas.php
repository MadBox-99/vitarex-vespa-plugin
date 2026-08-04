<?php
/**
 * Bizonyítja, hogy egy riport szűrője tényleg megváltoztatja a számokat.
 *
 * A sorok száma önmagában nem elég: több riport minden intézményt kilistáz,
 * ezért a sorszám akkor is azonos marad, ha a szűrő működik. Ezért a számokat
 * hasonlítjuk: a munkalap összes numerikus cellájának összegét.
 *
 * Használat:
 *   php szuro-hatas.php "<alap paraméterek>" "<eltérő paraméterek>"
 *
 * Példa:
 *   php szuro-hatas.php "tanev_diakolimpia_diakok series=3" \
 *                       "tanev_diakolimpia_diakok series=3 gender=nő"
 */

if (PHP_SAPI !== 'cli') {
    exit("Csak parancssorbol futtathato.\n");
}

// A PhpSpreadsheet olvasója régi szintaxist használ; a saját deprecation-
// üzenete elfedné az eredményt. Ez csak az ellenőrzőt némítja — a riportot
// generáló alfolyamat továbbra is teljes hibajelzéssel fut.
error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/wp-content/plugins/vitarex-vespa-plugin/lib/vendor/autoload.php';

$argumentumok = $argv;
array_shift($argumentumok);

if (count($argumentumok) < 2) {
    fwrite(STDERR, "Hasznalat: php szuro-hatas.php \"<alap>\" \"<elteroe>\"\n");
    exit(1);
}

$php = getenv('HERD_PHP')
    ?: getenv('HOME') . '/Library/Application Support/Herd/bin/php83';

/**
 * Legenerálja a riportot és visszaadja a számszerű ujjlenyomatét.
 */
function ujjlenyomat($php, $parameterek)
{
    $ideiglenes = tempnam(sys_get_temp_dir(), 'riport') . '.xlsx';

    $parancs = escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/riport-teszt.php');
    foreach (preg_split('/\s+/', trim($parameterek)) as $resz) {
        $parancs .= ' ' . escapeshellarg($resz);
    }
    $parancs .= ' > ' . escapeshellarg($ideiglenes) . ' 2>/dev/null';

    exec($parancs);

    if (!file_exists($ideiglenes) || filesize($ideiglenes) === 0) {
        return array('hiba' => 'ures kimenet');
    }

    $olvaso = PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($ideiglenes);
    $olvaso->setReadDataOnly(true);
    $lap = $olvaso->load($ideiglenes)->getActiveSheet();

    $osszeg = 0.0;
    $szamok = 0;
    foreach ($lap->toArray(null, true, false, false) as $sor) {
        foreach ($sor as $cella) {
            if (is_numeric($cella)) {
                $osszeg += (float) $cella;
                $szamok++;
            }
        }
    }

    unlink($ideiglenes);

    return array(
        'sorok'  => $lap->getHighestRow(),
        'szamok' => $szamok,
        'osszeg' => $osszeg,
    );
}

$alap    = ujjlenyomat($php, $argumentumok[0]);
$eltero  = ujjlenyomat($php, $argumentumok[1]);

echo "alap:    {$argumentumok[0]}\n";
echo "         ", json_encode($alap, JSON_UNESCAPED_UNICODE), "\n";
echo "eltero:  {$argumentumok[1]}\n";
echo "         ", json_encode($eltero, JSON_UNESCAPED_UNICODE), "\n";

if (isset($alap['hiba']) || isset($eltero['hiba'])) {
    echo "EREDMENY: NEM ERTEKELHETO — valamelyik futas nem adott fajlt\n";
    exit(1);
}

if ($alap === $eltero) {
    echo "EREDMENY: A SZURO NEM VALTOZTAT SEMMIT — azonos szamok\n";
    exit(1);
}

echo "EREDMENY: A SZURO HAT — az osszeg ", $alap['osszeg'], " -> ", $eltero['osszeg'], "\n";
