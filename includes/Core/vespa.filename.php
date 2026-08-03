<?php

/**
 * Fájlnév-részlet képzése tetszőleges szövegből (tipikusan a verseny nevéből).
 * WordPress-független, tiszta függvény — a tesztjei tests/test-filename.php.
 */
function vespa_filename_resz($nev, $tartalek)
{
    $nev = trim((string) $nev);

    // Fájlnévben tiltott (Windows/Unix) és vezérlőkarakterek. Szóközre
    // cseréljük, nem törlünk: egy "2024/2025" nem "20242025" akar lenni.
    $nev = preg_replace('#[/\\\\:*?"<>|]+#u', ' ', $nev);
    $nev = preg_replace('#[\x00-\x1F]+#u', ' ', (string) $nev);
    $nev = trim(preg_replace('#\s+#u', ' ', (string) $nev));

    // Hosszkorlát: a legtöbb fájlrendszer 255 bájtnál elhasal, az ékezetes
    // karakterek pedig UTF-8-ban 2 bájtot foglalnak. A megnevezés és a
    // kiterjesztés is a limit alá kell férjen, ezért vágunk bőven alatta.
    if (mb_strlen($nev) > 100) {
        $nev = trim(mb_substr($nev, 0, 100));
    }

    return $nev === '' ? $tartalek : $nev;
}
