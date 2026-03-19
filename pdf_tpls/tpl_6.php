<?php
	ob_start();
?>


<table>
	<tr>
		<td class="label bp">Díjazás:</td>
		<td class="bp">Az 1-3. helyezettek érem díjazásban részesülnek. Különdíjat kap a legjobb támadó- és védőjátékos.</td>
	</tr>	

	<tr>
		<td class="label bp">Szabályok:</td>
		<td class="bp">
Az MKSZ kézilabda általános szabályai irányadóak. <br>
A pályán csapatokként 5+1 fő tartózkodhat.<br>
Játékidő: 2x10 perc (tiszta játékidő)<br>
A pályán egyidejűleg legfeljebb két partnerjátékos tartózkodhat.<br>
A kiírásban nem szereplő valamennyi sportszakmai kérdésben az MKSZ szabályozottak az irányadók.

		</td>
	</tr>	

	<tr>
		<td class="label bp">Egyéb:</td>
		<td class="bp">
			<ul>
				<li>Kérünk minden csapatot, hogy a versenyzők TAJ kártyáját és diákigazolványát hozzák magukkal! A diákigazolványokat a versenyirodán jelentkezéskor szíveskedjenek bemutatni! </li>
				<li>A diákolimpián a versenyzők saját felszerelésüket használhatják, követelmény a sportágnak megfelelő sportruházat.</li>
				<li>Minden induló versenyzőnek érvényes orvosi (iskolaorvosi) igazolással kell rendelkeznie.</li>
				<li>A versenybírósággal kizárólag a csapatvezető tarthat kapcsolatot.</li>
				<li>Az elveszett értéktárgyakért, felszerelésért a rendezőség felelősséget nem vállal.</li>
				<li>A versenykiírásban nem érintett kérdésekben a központi versenykiírásában meghatározott általános szabályok az irányadóak.</li>
				<li>A versenykiírásban a változtatás jogát fenntartjuk.</li>
			</ul>
		</td>
	</tr>			
</table>

<?php 
	$html .= ob_get_contents();
	ob_clean();
?>	
