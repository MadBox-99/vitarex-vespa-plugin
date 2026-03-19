<?php
	ob_start();
?>


<table>
	<tr>
		<td class="label bp">Díjazás:</td>
		<td class="bp">A versenyszámok I-III. helyezettjei (kategóriánként-korcsoportonként és nemenként) érem díjazásban részesülnek.</td>
	</tr>	

	<tr>
		<td class="label bp">Információ:</td>
		<td class="bp">
			<strong>A Magyar Sakk Szövetség érvényes szabályai alapján.</strong>
		</td>
	</tr>		

	<tr>
		<td class="label bp">Költségek:</td>
		<td class="bp">A versennyel kapcsolatos rendezési-díjazási költségeket a rendező biztosítja. Az utazási, szállás és egyéb költségek a résztvevőket terhelik.</td>
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
				<li>A versenykiírásban a változtatás jogát fenntartjuk.</li>
				<li>Mindenkinek sikeres versenyzést kívánunk.</li>
			</ul>
		</td>
	</tr>			
</table>

<?php 
	$html .= ob_get_contents();
	ob_clean();
?>	
