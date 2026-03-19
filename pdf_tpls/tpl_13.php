<?php
	ob_start();
?>


<table>
	<tr>
		<td class="label bp">Versenyszámok, lebonyolítás:</td>
		<td class="bp">
Fiú és lány egyes versenyszámok. <br>
Selejtező mérkőzések 3 pályán, ahonnan az 1-2. helyezettek jutnak tovább a kieséses rendszerben lebonyolításra kerülő főtáblára. <br>
A mérkőzéseket <strong>kettő nyert játszmára</strong> játsszák, játszmánkként 11 pontig, a tollaslabda játékszabályai és versenyszabályzata szerint (<a hre="www.badminton.hu/szabalyok">www.badminton.hu/szabalyok</a>). Hosszabbítás nincs.
		</td>
	</tr>	

	<tr>
		<td class="label bp">Díjazás:</td>
		<td class="bp">A versenyszámok I-III. helyezettjei: egyéni érem, IV-VI. helyezett oklevél korcsoportokként és nemekként.
		</td>
	</tr>			
</table>

<?php 
	$html .= ob_get_contents();
	ob_clean();
?>	
