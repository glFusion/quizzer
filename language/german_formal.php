<?php
/**
*   German formal language file for the forms plugin, adressing the user as (Sie)
*
*   @author     Lee Garner <lee@leegarner.com>
*   @translated Siegfried Gutschi <sigi AT modellbaukalender DOT info> (Dez 2016)
*   @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
*   @package    forms
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

$LANG_FORMS = array(
'menu_title'				=> 'Formulare',
'admin_text'				=> 'Erstellen und bearbeiten Sie benutzerdefinierte Formularfelder',
'admin_title'				=> 'Formular-Administration',
'frm_id'					=> 'Formular-ID',
'add_form'					=> 'Neues Formular',
'add_field'					=> 'Neues Feld',
'list_forms'				=> 'Formulare auflisten',
'list_fields'				=> 'Felder auflisten',
'action'					=> 'Weitere Aktionen',
'yes'						=> 'Ja',
'no'						=> 'Nein',
'undefined'					=> 'Unbestimmt',
//'submit'					=> 'Speichern',
//'cancel'					=> 'Abbrechen',
'reset'						=> 'Zur�cksetzen',
'reset_fld_perm'			=> 'Formular-Rechte verwenden',
'save_to_db'				=> 'Speichern',
'email_owner'				=> 'E-Mail an Besitzer',
'email_group'				=> 'E-Mail an Gruppe',
'email_admin'				=> 'E-Mail an Admin',
'onsubmit'					=> 'Aktion Nach dem Senden',
'preview'					=> 'Formular-Vorschau',
'formsubmission'			=> 'Neue Formular-Einsendung: %s',
'introtext'					=> 'Formular-Einleitung',
'def_submit_msg'			=> 'Vielen Dank f�r Ihre Einsendung',
'submit_msg'				=> 'Meldung "Vielen Dank"',
'noaccess_msg'				=> 'Meldung "Kein Zugriff"',
'noedit_msg'				=> 'Meldung "Kein Bearbeiten"',
'max_submit_msg'			=> 'Meldung "Zu oft gesendet"',
'moderate'					=> 'Einsendung �berpr�fen',
'results_group'				=> 'Wer kann Ergebnisse sehen',
'new_frm_saved_msg'			=> 'Formular wurde gespeichert, Sie k�nnen nun unten Neue Felder hinzuf�gen',
'help_msg'					=> 'Hilfe Text',
'hlp_edit_form'				=> '<ul><li>Erstellen Sie hier neue oder bearbeiten Sie bestehende Formulare.</li><li>Beim Erstellen eines Formulars muss das Formular gespeichert werden, bevor Felder hinzugef�gt werden k�nnen.</li></ul>',
'hlp_fld_order'				=> '<ul><li>Die Sortierung gibt an, wo das Feld im Verh�ltnis zu anderen Feldern auf Formularen erscheint.</li><li>Dies kann sp�ter jederzeit ge�ndert werden.</li></ul>',
'hlp_fld_value'				=> 'Der Wert hat je nach Feld-Typ mehrere Bedeutungen:<br><ul><li>F�r Textfelder ist dies der Standardwert, der in neuen Eintr�gen verwendet wird.</li><li>F�r Kontrollk�stchen ist dies der zur�ckgegebene Wert wenn diese aktiviert sind (normalerweise 1).</li><li>Bei Radio-Buttons und Dropdown-Listen handelt es sich um eine Zeichenfolge im Format: "value1:prompt1; value2:prompt2; ..."</li></ul>',
'hlp_fld_mask'				=> 'Geben Sie die Datenmaske ein (z.B. "99999-9999" f�r eine US-Postleitzahl)',
'hlp_fld_enter_format'		=> 'Geben Sie den Format-String ein',
'hlp_fld_enter_default'		=> 'Geben Sie den Standardwert f�r dieses Feld ein',
'hlp_fld_def_option'		=> 'Geben Sie den f�r die Standard-Einstellung zu verwendenden Optionsnamen ein',
'hlp_fld_def_option_name'	=> 'Geben Sie den Standard-Optionsnamen ein',
'hlp_fld_chkbox_default'	=> 'Aktivieren Sie dieses Kontrollk�stchen, wenn es standardm��ig aktiviert sein soll.',
'hlp_fld_default_date'		=> 'Standart Datums-Format ("JJJJ-MM-TT hh:mm", 24-Stunden Format)',
'hlp_fld_default_time'		=> 'Standart Uhrzeit-Format ("hh:mm", 24-Stunden Format)',
'hlp_fld_autogen'			=> 'Geben Sie an, ob die Daten f�r dieses Feld automatisch angelegt werden sollen.',

'hdr_form_preview'			=> '<ul><li>Dies ist eine voll funktionsf�hige Vorschau wie Ihr Formular aussehen wird.</li><li>Wenn Sie die Datenfelder ausf�llen und auf "Senden" klicken, werden die Daten gespeichert und / oder per E-Mail an den Eigent�mer des Formulars gesendet.</li><li>Wenn nach dem Senden die Ergebnisse angezeigt werden sollen, oder eine Umleitungs-URL f�r das Formular konfiguriert ist, werden Sie zur entsprechenden Seite weitergeleitet.</li></ul>',
'hdr_field_edit'			=> 'Bearbeiten Sie hier die einzelnen Felder des Formulares.',
'hdr_form_list'				=> '<ul><li>W�hlen Sie ein zu bearbeitendes Formular aus oder erstellen Sie ein neues Formular indem Sie oben auf "Neues Formular" klicken.</li><li>Weitere verf�gbare Aktionen werden in der Auswahl unter der Spalte "Weitere Aktionen" angezeigt.</li></ul>',
'hdr_field_list'			=> 'W�hlen Sie ein Feld zum Bearbeiten aus oder erstellen Sie ein neues Feld indem Sie oben auf "Neues Feld" klicken.',
'hdr_form_results'			=> 'Hier sind die Einsendungen Ihrer Formulare. Sie k�nnen sie l�schen, indem Sie auf das entsprechende Kontrollk�stchen und danach auf "L�schen" klicken.',

'form_results'				=> 'Formular-Einsendungen',
'del_selected'				=> 'L�sche ausgew�hlte',
'export'					=> 'CSV exportieren',
'submitter'					=> 'Eingesendet von',
'submitted'					=> 'Eingesendet',
'orderby'					=> 'Sortierung',
'name'						=> 'Name',
'type'						=> 'Typ',
'enabled'					=> 'Aktiviert',
'required'					=> 'Erforderlich',
'hidden'					=> 'Versteckt',
'normal'					=> 'Normal',
'spancols'					=> 'Ohne Feld-Bezeichnung',
'user_reg'					=> 'Registrierung',
'readonly'					=> 'Nur Lesen',
'select'					=> '- Ausw�hlen -',
'move'						=> 'Verschieben',
'rmfld'						=> 'Aus Formular entfernen',
'killfld'					=> 'Entfernen und Daten L�schen',
'usermenu'					=> 'Benutzer anzeigen',

//'description'				=> 'Beschreibung',
'textprompt'				=> 'Feld-Bezeichnung',
'fieldname'					=> 'Feld-Name/ID',
'formname'					=> 'Formular-Name',
'fieldtype'					=> 'Feld-Typ',
'inputlen'					=> 'Gr��e des Eingabe-Feldes',
'maxlen'					=> 'Max. Zeichen f�r Eingabe',
'columns'					=> 'Spalten',
'rows'						=> 'Reihen',
'value'						=> 'Wert',
'defvalue'					=> 'Standart-Wert',
'showtime'					=> 'Zeit anzeigen',
'hourformat'				=> '12/24-Stunden Format',
'hour12'					=> '12-Stunden',
'hour24'					=> '24-Stunden',
'format'					=> 'Format',
'input_format'				=> 'Eingabe-Format',
'month'						=> 'Monat',
'day'						=> 'Tag',
'year'						=> 'Jahr',
'autogen'					=> 'Automatisch erzeugen',
'mask'						=> 'Daten-Maske f�r Feld',
'stripmask'					=> 'Ohne Daten-Maske',
//'ent_registration'		=> 'Anmeldedaten eingeben',
'pos_after'					=> 'Position nach',
'nochange'					=> 'Keine �nderungen',
'first'						=> 'Erste Position',
'permissions'				=> 'Berechtigungen',
'group'						=> 'Gruppe',
'owner'						=> 'Eigent�mer',
'admin_group'				=> 'Admin-Gruppe',
'user_group'				=> 'Gruppe kann einsenden',
'results_group'				=> 'Gruppe kann lesen',
'redirect'					=> 'Nach Senden weiter zu URL',
'fieldset1'					=> 'Zus�tzliche Formular-Einstellungen',
'entered_by'				=> 'Eingesendet von',
'datetime'					=> 'Datum/Uhrzeit',
'is_required'				=> 'darf nicht leer sein',
'frm_invalid'				=> 'Das Formular enth�lt fehlende oder ung�ltige Felder.',
'add_century'				=> 'Jahrhundert hinzuf�gen, wenn fehlt',
'err_name_required'			=> 'Fehler, Formular-Name darf nicht leer sein.',
'confirm_form_delete'		=> 'Sind Sie sicher, dass Sie dieses Formular wirklich l�schen wollen? Alle zugeh�rigen Felder und Daten werden dauerhaft gel�scht.',
'confirm_delete'			=> 'Sind Sie sicher, dass Sie dieses Feld l�schen wollen?',
'fld_access'				=> 'Feld-Status',

'fld_types' => array(
    'text'					=> 'Text', 
    'textarea'				=> 'Text-Feld',
    'numeric'				=> 'Zahlen-Feld',
    'checkbox'				=> 'Kontrollk�stchen',
    'multicheck'			=> 'Mehrfach-Kontrollk�stchen',
    'select'				=> 'Dropdown-Liste',
    'radio'					=> 'Radio-Buttons',
    'date'					=> 'Datum',
    'time'					=> 'Uhrzeit',
    'static'				=> 'Statisch',
    'calc'					=> 'Berechnung',
),
'calc_type'					=> 'Berechnungs-Art',
'calc_types'=> array(
    'add'					=> 'Addieren',
    'sub'					=> 'Subtrahieren',
    'mul'					=> 'Multiplizieren',
    'div'					=> 'Dividieren',
    'mean'					=> 'Durchschnitt',
),
'submissions'				=> 'Einsendungen',
'frm_url'					=> 'Formular-URL',
'req_captcha'				=> 'CAPTCHA aktivieren',
'inblock'					=> 'Im Block anzeigen',
'preview_on_save'			=> 'Ergebnisse anzeigen',
'ip_addr'					=> 'IP-Adresse',
'view_html'					=> 'HTML',
'never'						=> 'Nie',
'when_fill'					=> 'Beim Ausf�llen des Formulars',
'when_save'					=> 'Beim Speichern des Formulars',
'max_submit'				=> 'Einsendungs-Limit gesamt',
'onetime'					=> 'Limit pro Benutzer',
'pul_once'					=> 'Eine Einsendung - bearbeiten nicht erlaubt',
'pul_edit'					=> 'Eine Einsendung - bearbeiten erlaubt',
'pul_mult'					=> 'Mehrfach Einsendungen erlaubt',
'other_emails'				=> 'Andere E-Mail (trennen mit ";")',
'instance'					=> 'Beispiel',
'showing_instance'			=> 'Ergebnisse anzeigen f�r &quot;%s&quot;',
'clear_instance'			=> 'Alle anzeigen',
'datepicker' => 'Click for a date picker',
'print' => 'Print',
'toggle_success' => 'Item has been updated.',
'toggle_failure' => 'Error updating item.',
'edit_result_header' => 'Editing the submission by %1$s (%2$d) from %3$s at %4$s',
'form_type' => 'Form Type',
'regular' => 'Regular Form',
'field_updated' => 'Field updated',
'save_disabled' => 'Saving disabled in form preview.',
);

$PLG_forms_MESSAGE1 = 'Vielen Dank f�r Ihre Formular-Einsendung.';
$PLG_forms_MESSAGE2 = 'Das Formular enth�lt fehlende oder ung�ltige Felder.';
$PLG_forms_MESSAGE3 = 'Das Plugin wurde aktualisiert';
$PLG_forms_MESSAGE4 = 'Es gab eine Fehler beim aktualisieren des Plugin.';
$PLG_forms_MESSAGE5 = 'Ein Datenbankfehler ist aufgetreten. �berpr�fen Sie die Datei "error.log" f�r weitere Details.';
$PLG_forms_MESSAGE6 = 'Ihr Formular wurde erstellt. Sie k�nnen nun Felder erstellen.';
$PLG_forms_MESSAGE7 = 'Die maximale Anzahl der Einsendungen wurde erreicht.';

/** Language strings for the plugin configuration section */
$LANG_configsections['forms'] = array(
    'label' => 'Forms',
    'title' => 'Forms Configuration'
);

$LANG_configsubgroups['forms'] = array(
    'sg_main'				=> 'Haupteinstellungen'
);

$LANG_fs['forms'] = array(
    'fs_main'				=> 'Allgemeine-Einstellungen',
    'fs_flddef'				=> 'Standart Feld-Einstellungen',
);

$LANG_confignames['forms'] = array(
    'displayblocks'			=> 'Bl�cke anzeigen',
    'default_permissions'	=> 'Standard-Berechtigungen',
    'defgroup'				=> 'Standard-Gruppe',
    'fill_gid'				=> 'Standard-Gruppe schreiben',
    'results_gid'			=> 'Standard-Gruppe lesen',

    'def_text_size'			=> 'Standard Text-Feld-Gr��e',
    'def_text_maxlen'		=> 'Standard Text-L�nge',
    'def_textarea_rows'		=> 'Standard Text-Feld-Reihen',
    'def_textarea_cols'		=> 'Standard Text-Feld-Spalten',
    'def_date_format'		=> 'Standard Datums-Format',
    'def_calc_format'		=> 'Standard Rechen-Art',
);

// Note: entries 0, 1, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['forms'] = array(
    0 => array('Ja' => 1, 'Nein' => 0),
    1 => array('Ja' => TRUE, 'Nein' => FALSE),
    2 => array('Wie eingesendet' => 'submitorder', 'Nach Stimmen' => 'voteorder'),
    3 => array('Ja' => 1, 'Nein' => 0),
    6 => array('Normal' => 'normal', 'Bl�cke' => 'blocks'),
    9 => array('Nie' => 0, 'Wenn Warteschlange' => 1, 'Immer' => 2),
    10 => array('Nie' => 0, 'Immer' => 1, 'Akzeptiert' => 2, 'Abgelehnt' => 3),
    12 => array('Kein Zugriff' => 0, 'Nur Lesen' => 2, 'Lesen & Schreiben' => 3),
    13 => array('Keine Bl�cke' => 0, 'Linke Bl�cke' => 1, 'Rechte Bl�cke' => 2, 'Linke & Rechte Bl�cke' => 3),
);


?>
