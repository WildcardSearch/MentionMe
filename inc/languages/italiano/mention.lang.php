<?php
/**
 * This file contains language definitions for MentionMe (English)
 *
 * Copyright © 2013 Wildcard
 * http://www.rantcentralforums.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses
 */

$l['mention_description'] = 'Mostra @menzioni con links (e MyAlerts, se installato)';

// advanced name matching
$l['mention_advanced_matching'] = 'Abilita Matching Avanzato?';
$l['mention_advanced_matching_desc'] = 'Questa opzione abilita il processamento anche degli username con spazi all\'interno senza che questi debbano essere racchiusi tra virgolette.<br /><br />Questa funzione pu\ò aumentare di molto il carico del server e non è consigliata per i forum grandi.';
$l['mention_settingsgroup_description'] = 'Abilita o disabilita il matching avanzato';

// task
$l['mention_task_name'] = 'MentionMe Name Caching';
$l['mention_task_description'] = 'Salva in cache i link degli username attivi per ridurre il numero di query giornaliere';

// MyAlerts
$l['myalerts_mention'] = '{1} ti ha menzionato in questo thread: <a href="{2}">{3}</a>. ({4})';
$l['myalerts_setting_mention'] = 'Ricevi notifiche quando menzionato in un post?';
$l['mention_myalerts_acpsetting_description'] = 'Notifiche per le menzioni?';
$l['myalerts_help_alert_types_mentioned'] = '<strong>Menzionato in un post</strong>
<p>
	Ricevi questo tipo di notifica quando vieni menzionato da un altro membro in qualsiasi posto del sito dove venga usato il tag in stile twitter di <a href="http://mods.mybb.com/view/mentionme"><span style="color: #32CD32;"><strong>MentionMe</strong></span></a>.
</p>';
$l['mention_myalerts_integration_message'] = 'MyAlerts è stato rilevato ed installato ma non ancora integrato con MentionMe! Devi disinstallare e reinstallare il plugin per ricevere gli allert di menzione.';
$l['mention_myalerts_working'] = 'MentionMe è stato integrato con successo con MyAlerts';

?>
