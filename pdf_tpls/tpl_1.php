<?php
	ob_start();
?>

	<h3>FONTOS INFORMÁCIÓK</h3>

<p>
	<ul>
		<li>Helyszíni nevezésre, sportoló cserére nincs lehetőség! A nevezési listára történő felkerülés a nevező (18. éven aluliak esetén a kísérő vagy a pedagógus) felelőssége!</li>
		<li>Nevezési határidő lejárta után pótnevezést, cserét nem fogadunk el!</li>
		<li>Kérünk minden csapatot, hogy a versenyzők TAJ kártyáját és diákigazolványát hozzák magukkal! A diákigazolványokat a versenyirodán jelentkezéskor szíveskedjenek bemutatni! </li>
		<li>A diákolimpián a versenyzők saját felszerelésüket használhatják, követelmény a sportágnak megfelelő sportruházat. Fehér mez, illetve fehér felsőruházat viselése nem engedélyezett.</li>
		<li>Minden induló versenyzőnek érvényes orvosi (iskolaorvosi) igazolással kell rendelkeznie.</li>
		<li>A versenybírósággal kizárólag a csapatvezető tarthat kapcsolatot.</li>
		<li>Az elveszett értéktárgyakért, felszerelésért a rendezőség felelősséget nem vállal.</li>
		<li>A versenykiírásban nem érintett kérdésekben a központi versenykiírásában meghatározott általános szabályok, valamint a Magyar Asztalitenisz Szövetség szabályai az irányadóak.</li>
		<li>A versenykiírásban a változtatás jogát fenntartjuk.</li>
		<li>Mindenkinek sikeres versenyzést kívánunk!</li>
	</ul>
</p>

<?php 
	$html .= ob_get_contents();
	ob_clean();
?>	
