<?php  //$Id: settings.php,v 1.1 2010/01/09 23:29:40 arborrow Exp $

$settings->add(new admin_setting_configtext('block_wookie_url','Wookie Server URL',
                  '', "http://localhost:8080/wookie/", PARAM_TEXT));

$settings->add(new admin_setting_configtext('block_wookie_key', 'Wookie API Key',
                   '', "TEST", PARAM_TEXT));

$settings->add(new admin_setting_configtextarea('block_wookie_moodleparams','Moodle Params',
					get_string('moodleparamshelp','block_wookie'),'',PARAM_RAW));

?>
