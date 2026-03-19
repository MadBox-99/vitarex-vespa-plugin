<?php
	ob_start();
?>


<table>
	<tr>
		<td class="label bp">Lebonyolítás:</td>
		<td class="bp">Csoportmérkőzések, helyosztók</td>
	</tr>	

	<tr>
		<td class="label bp">Díjazás:</td>
		<td class="bp">I-III. helyezettek egyéni érem, IV.-VI. csapat oklevél (1 db/csapat), legjobb csapat serleg
		</td>
	</tr>			

	<tr>
		<td class="label bp">Egyéb:</td>
		<td class="bp">
			<ul>
				<li>Kérünk minden csapatot, hogy a versenyzők TAJ kártyáját és diákigazolványát hozzák magukkal! A diákigazolványokat a versenyirodán jelentkezéskor szíveskedjenek bemutatni</li>
				<li>A diákolimpián a versenyzők saját felszerelésüket használhatják, <strong>követelmény a sportágnak megfelelő sportruházat</strong></li>
				<li><strong>Minden induló versenyzőnek érvényes orvosi (iskolaorvosi) igazolással kell rendelkeznie</strong></li>
				<li>A versenybírósággal kizárólag a csapatvezető tarthat kapcsolatot</li>
				<li>Az elveszett értéktárgyakért, felszerelésért a rendezőség felelősséget nem vállal</li>
				<li>A versenykiírásban nem érintett kérdésekben a központi versenykiírásában meghatározott általános szabályok az irányadóak</li>
				<li>A versenykiírásban a változtatás jogát fenntartjuk			</li>
			</ul>
		</td>
	</tr>	


</table>

<?php 
	$html .= ob_get_contents();
	ob_clean();
?>	
