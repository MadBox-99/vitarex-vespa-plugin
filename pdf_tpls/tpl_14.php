<?php
	ob_start();
?>

	<h3>FONTOS INFORMÁCIÓK</h3>

<p>
	<ul>
		<li>Kérünk minden csapatot, hogy a versenyzők TAJ kártyáját és diákigazolványát hozzák magukkal! A diákigazolványokat a versenyirodán jelentkezéskor szíveskedjenek bemutatni! </li>
		<li>A diákolimpián a versenyzők saját felszerelésüket használhatják, követelmény a sportágnak megfelelő sportruházat. </li>
		<li>Minden induló versenyzőnek érvényes orvosi (iskolaorvosi) igazolással kell rendelkeznie.</li>
		<li>A III. korcsoport versenyzői egy futószámban és két ügyességi számban indulhatnak.</li>
		<li>A versenybírósággal kizárólag a csapatvezető tarthat kapcsolatot.</li>
		<li>Az elveszett értéktárgyakért, felszerelésért a rendezőség felelősséget nem vállal.</li>
		<li>A versenykiírásban nem érintett kérdésekben a központi versenykiírásában meghatározott általános szabályok, valamint a Magyar Atlétikai Szövetség szabályai az irányadóak.</li>
		<li>A versenykiírásban a változtatás jogát fenntartjuk.</li>
		<li>Mindenkinek sikeres versenyzést kívánunk.</li>
	</ul>
</p>

<p>
	A nevezési határidő lejárta után nevezéseket nem áll módunkban elfogadni.
</p>

<?php 
	$html .= ob_get_contents();
	ob_clean();
?>	
