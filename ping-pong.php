<?php

/*
Script CLI (command line interface) for synchronizing two database
*/
#@todo

// Tables to be created into the new database
$importTables = array(
	'tx_dam',
	'tx_dam_cat',
	'tx_dam_mm_cat',
	'tx_datafilter_filters',
	'tx_dataquery_queries',
	'tx_displaycontroller_components_mm',
	'tx_phpdisplay_displays',
	'tx_speciality_domain_model_domain',
	'tx_speciality_domain_model_group',
	'tx_speciality_domain_model_master',
	'tx_templatedisplay_displays'
);

// Tables to be synchronized into the new database.
$synchronizeTables = array(
	'tt_content',
	'pages',
	'be_groups',
	'be_users',
	'fe_groups',
	'fe_users',
	'sys_domain',
	'sys_refindex',
	'sys_template',
);

// Special rules applied against synchronized tables.
$synchronizeRuleTables = array(
	'tt_content' => array(

		'subheader',
		'header_link'
	),
	'pages' => array(

		'url',
		'subtitle',
		'author',
		'nav_title',
	),
	'be_groups' => array(),
	'be_users' => array(
		'password'
	),
	'fe_groups' => array(),
	'fe_users' => array(),
	'sys_domain' => array(),
	'sys_refindex' => array(),
	'sys_template' => array(),
);

############################################

include('Classes/Database.php');
include('Classes/Logger.php');
$logger = new Logger();

$dbOld = new Ecodev\Database('localhost', 'root', 'root');
$dbOld->connect('DB-old');
$dbNew = new Ecodev\Database('localhost', 'root', 'root');
$dbNew->connect('DB-new');


foreach ($tables as $table) {
	$logger->log('Synchronizing table " ' . $table . ' " .....');
	$fieldStructures = $dbNew->select('SHOW COLUMNS FROM ' . $table);
	$newFieldsNames = array();
	foreach ($fieldStructures as $fieldStructure) {
		$newFieldsNames[] = $fieldStructure['Field'];
	}
	
	# @todo ne pas synchroniser les records qui ont:
	# le champ s'appelle "deleted", s'il existe  --> le flag qui marque que le record est suppriméer
	# le champ peut s'appeler disable ou hidden, s'il existe --> le flag qui marque que le record est masqué
	
	// première manière d'implémenter:
	$clause = '1 = 1';
	if (isset($newFieldsNames['deleted'])) {
		$clause .= ' deleted = 1'	
	}
	if (isset($newFieldsNames['disable'])) {
		$clause .= ' disable = 1'	
	}
	
	// deuxième manière d'implémenter:
	$specialFields = array('deleted', 'disable', 'hidden');
	foreach ($specialFields as $specialField) {
		if (isset($newFieldsNames['disable'])) {
			$clause .= ' disable = 1'	
		}	
	}
	// same for hidden
	$toImportValues = $dbOld->select('SELECT * FROM ' . $table . ' WHERE ' . $clause);
	$dbNew->delete($table);//truncating table
	/*
	$dbNew->query('TRUNCATE TABLE '. $table); => other way to do it
	*/
	foreach ($toImportValues as $toImportValue) {
		foreach ($toImportValue as $fieldName => $value) {
			if (!in_array($fieldName, $newFieldsNames)) {
				unset($toImportValue[$fieldName]);
			}
		}
		foreach ($convertFields[$table] as $convertField) {
			if (is_null($toImportValue[$convertField])) {
				$toImportValue[$convertField] = '';
			}
		}
		$dbNew->insert($table, $toImportValue);
	}
	$logger->log('Synchronized!!!');
}
# @todo
$logger->log();
$logger->log('All tables synchronized succesfully!!!');

# @todo mettre cette variable au sommet

foreach ($tables as $table) {
	$logger->log('Import whole content of table: "' . $table.'"');
	$exportCommand = 'mysqldump -u root -p"root" DB-old ' . $table . ' > /tmp/' . $table . '.sql';
	exec($exportCommand);
	$importCommand = 'mysql -u root -p"root" DB-new < /tmp/' . $table . '.sql';
	exec($importCommand);
	$logger->log('Done for:' . $table . '!!!');
}
# @todo 
$loger->log();
$logger->log('All tables imported succesfully in the new database!!!');
