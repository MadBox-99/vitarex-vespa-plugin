<?php
	ob_start();
?>


<table>
	<tr>
		<td class="label bp">Egyéb:</td>
		<td class="bp">
			<ul>
				<li>Egy csapatban az adott korcsoportba tartozó, egyazon intézmény fiú és lány tanulói szerepelhetnek</li>
				<li>A megyei válogatott nevezése a VESPA-ban:</li>
				<li>Hiába több intézményből áll össze a csapat,  a VESPA felületén egyetlen egy intézményből kell csak nevezni!</li>
				<li>Csapatlétszám: 10 fő</li>
				<li>Egy csapathoz legfeljebb 2 fő kísérő és 1 sofőr csatlakozhat</li>
			</ul>
		</td>
	</tr>	

	<tr>
		<td class="label bp">A lebonyolítás rendje:</td>
		<td class="bp">
			Lebonyolítás a jelentkező csapatok függvényében kerül kialakításra <br>

			Feladatok : <br>
			1. számú feladat: Labdaadogató verseny <br>
			4. számú feladat: Labdahordás <br>
			5. számú feladat: Labdagurítás <br>
			6. számú feladat: Futás váltóbottal <br>
			7. számú feladat: Karika kirakó <br>
			8. számú feladat: Kalapos váltó <br>
			9. számú feladat: Kosárba labda <br>
			10. számú feladat: Akadálypálya

		</td>
	</tr>	

	<tr>
		<td class="label bp">Díjazás:</td>
		<td class="bp">
			I-III. helyezett csapatok egyéni érem <br>
			IV-VI. helyezett csapat oklevél (1 db. oklevél csapatonként) díjazásban részesülnek

		</td>
	</tr>		

	<tr>
		<td class="label bp">Nevezés:</td>
		<td class="bp">
			<strong>
			A versenyre való nevezés a FODISZ VESPA rendszeren keresztül történik. <span style="color:red;">Kérjük a megyei bajnokokat, hogy az országos versenyre külön szíveskedjenek nevezni! </span>
			</strong>
		</td>
	</tr>	

		
</table>

<?php 
	$html .= ob_get_contents();
	ob_clean();
?>	
