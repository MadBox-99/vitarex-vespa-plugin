<?php

/**
 * A szabadidős nevezési mezők tiszta, WordPress-független logikája.
 *
 * Ez a fájl KIZÁRÓLAG függvényeket definiál — se hook, se ABSPATH-guard, se
 * WP-hívás betöltéskor. Így a tests/test-szabadidos-fields.php sima PHP-vel
 * betöltheti. Az adatbázist érintő rész a vespa.szabadidos.helpers.php-ban van.
 */

/**
 * A választható mezőtípusok: gépi kulcs => admin felületen megjelenő címke.
 */
function vespa_szabadidos_field_types()
{
    return array(
        'egyvalasztos'  => 'Egyválasztós (rádiógomb)',
        'tobbvalasztos' => 'Többválasztós (jelölőnégyzetek)',
        'szoveg'        => 'Szöveg (egysoros)',
        'hosszu_szoveg' => 'Szöveg (többsoros)',
        'szam'          => 'Szám',
        'datum'         => 'Dátum',
        'nyilatkozat'   => 'Nyilatkozat (kötelező elfogadás)',
    );
}

function vespa_szabadidos_field_type_valid($type)
{
    return is_string($type) && array_key_exists($type, vespa_szabadidos_field_types());
}

/**
 * Igaz, ha a típushoz válaszlehetőség-listát kell megadni.
 */
function vespa_szabadidos_type_has_options($type)
{
    return $type === 'egyvalasztos' || $type === 'tobbvalasztos';
}

/**
 * A soronként egy válaszlehetőséget tartalmazó szövegből tömb.
 * Az üres sorokat és az ismétlődéseket eldobja, a whitespace-t levágja.
 */
function vespa_szabadidos_parse_options($text)
{
    if (!is_string($text) || $text === '') {
        return array();
    }

    $eredmeny = array();
    foreach (preg_split('/\r\n|\r|\n/', $text) as $sor) {
        // A tárolt lehetőségnek meg kell egyeznie azzal, amit a beküldött válasz
        // a sanitize_text_field kollabálása után tartalmaz — különben a szigorú
        // in_array-es egyezés hamisan bukik. Ezért itt is összevonjuk a belső
        // szóköz/tab-futamokat, mielőtt levágnánk és deduplikálnánk.
        $sor = preg_replace('/[ \t]+/', ' ', $sor);
        $sor = trim($sor);
        if ($sor !== '' && !in_array($sor, $eredmeny, true)) {
            $eredmeny[] = $sor;
        }
    }

    return $eredmeny;
}

/**
 * Meződefiníció ellenőrzése és normalizálása mentés előtt.
 * Siker esetén a 'field' kulcs alatt adja vissza a menthető sort.
 */
function vespa_szabadidos_validate_field($label, $type, $options_text, $is_required)
{
    $label = trim((string) $label);
    if ($label === '') {
        return array('ok' => false, 'error' => 'A címke nem lehet üres.', 'field' => null);
    }
    if (mb_strlen($label) > 255) {
        return array('ok' => false, 'error' => 'A címke legfeljebb 255 karakter lehet.', 'field' => null);
    }

    if (!vespa_szabadidos_field_type_valid($type)) {
        return array('ok' => false, 'error' => 'Ismeretlen mezőtípus.', 'field' => null);
    }

    $opciok = array();
    if (vespa_szabadidos_type_has_options($type)) {
        $opciok = vespa_szabadidos_parse_options($options_text);
        if (count($opciok) === 0) {
            return array('ok' => false, 'error' => 'Adj meg legalább egy válaszlehetőséget.', 'field' => null);
        }
    }

    // A "nem kötelező nyilatkozat" értelmetlen: az elfogadást mindig ki kell pipálni.
    $kotelezo = ($type === 'nyilatkozat') ? 1 : ($is_required ? 1 : 0);

    return array('ok' => true, 'error' => '', 'field' => array(
        'label'         => $label,
        'field_type'    => $type,
        'field_options' => $opciok ? implode("\n", $opciok) : null,
        'is_required'   => $kotelezo,
    ));
}

/**
 * Naptárilag is létező, éééé-hh-nn alakú dátum?
 */
function vespa_szabadidos_valid_date($ertek)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ertek, $m)) {
        return false;
    }
    return checkdate(intval($m[2]), intval($m[3]), intval($m[1]));
}

/**
 * Egy beküldött válasz ellenőrzése és tárolási alakra hozása.
 *
 * $raw: többválasztósnál tömb, minden más típusnál szöveg.
 * $options: a mező válaszlehetőségei (vespa_szabadidos_parse_options eredménye).
 * A 'value' null, ha a mező üresen maradhatott — ilyenkor nincs mit tárolni.
 */
function vespa_szabadidos_validate_answer($type, $is_required, $options, $raw)
{
    $kotelezo = ($type === 'nyilatkozat') ? true : (bool) $is_required;

    if ($type === 'tobbvalasztos') {
        $tisztitott = array();
        foreach ((is_array($raw) ? $raw : array()) as $elem) {
            $elem = trim((string) $elem);
            if ($elem === '') {
                continue;
            }
            if (!in_array($elem, $options, true)) {
                return array('ok' => false, 'error' => 'Érvénytelen válaszlehetőség.', 'value' => null);
            }
            if (!in_array($elem, $tisztitott, true)) {
                $tisztitott[] = $elem;
            }
        }
        if (count($tisztitott) === 0) {
            if ($kotelezo) {
                return array('ok' => false, 'error' => 'Ezt a mezőt ki kell tölteni.', 'value' => null);
            }
            return array('ok' => true, 'error' => '', 'value' => null);
        }
        return array(
            'ok'    => true,
            'error' => '',
            'value' => json_encode($tisztitott, JSON_UNESCAPED_UNICODE),
        );
    }

    $ertek = is_array($raw) ? '' : trim((string) $raw);

    if ($type === 'nyilatkozat') {
        if ($ertek !== '1') {
            return array('ok' => false, 'error' => 'Ezt a nyilatkozatot el kell fogadni.', 'value' => null);
        }
        return array('ok' => true, 'error' => '', 'value' => '1');
    }

    if ($ertek === '') {
        if ($kotelezo) {
            return array('ok' => false, 'error' => 'Ezt a mezőt ki kell tölteni.', 'value' => null);
        }
        return array('ok' => true, 'error' => '', 'value' => null);
    }

    if ($type === 'egyvalasztos' && !in_array($ertek, $options, true)) {
        return array('ok' => false, 'error' => 'Érvénytelen válaszlehetőség.', 'value' => null);
    }
    if ($type === 'szam' && !is_numeric($ertek)) {
        return array('ok' => false, 'error' => 'Csak számot adhatsz meg.', 'value' => null);
    }
    if ($type === 'datum' && !vespa_szabadidos_valid_date($ertek)) {
        return array('ok' => false, 'error' => 'A dátum formátuma éééé-hh-nn.', 'value' => null);
    }

    return array('ok' => true, 'error' => '', 'value' => $ertek);
}

/**
 * A tárolt válasz megjelenítési alakja (admin lista, XLSX export).
 */
function vespa_szabadidos_format_answer($type, $stored)
{
    if ($stored === null || $stored === '') {
        return '';
    }

    if ($type === 'tobbvalasztos') {
        $tomb = json_decode($stored, true);
        // Romlott vagy régi formátumú adatot nyersen mutatunk, nem nyelünk el.
        return is_array($tomb) ? implode(', ', $tomb) : (string) $stored;
    }

    if ($type === 'nyilatkozat') {
        return $stored === '1' ? 'igen' : '';
    }

    return (string) $stored;
}

/**
 * A tárolt válasz beviteli-mező alakja (űrlap előre kitöltése).
 */
function vespa_szabadidos_decode_answer($type, $stored)
{
    if ($type === 'tobbvalasztos') {
        if ($stored === null || $stored === '') {
            return array();
        }
        $tomb = json_decode($stored, true);
        return is_array($tomb) ? $tomb : array();
    }

    return $stored === null ? '' : (string) $stored;
}
