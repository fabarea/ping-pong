#!/usr/bin/env php
<?php
/*
Script CLI (command line interface) for synchronizing two database
*/

$credentialsFile = __DIR__ . '/credentials.php';
if (file_exists($credentialsFile)) {
    include $credentialsFile;
}

include 'Classes/Database.php';
include 'Classes/Logger.php';

$synchronizeTables = [
    'pages',
    'tt_content',
    'be_groups',
    'be_users',
    //    'sys_refindex', //todo it can be calculated
    'sys_file',
    'sys_file_reference',
];

$synchronizeTablesDefaultValues = [
    'pages' => [
        'media' => 0,
        //        'subtitle',
        //        'author',
        //        'nav_title',
    ],
    'tt_content' => [
        //            'header_link'
    ],
    //    'be_groups' => [],
    //    'be_users' => [
    //        'password'
    //    ],
    'sys_file' => [],
    'sys_file_reference' => [],
    'be_groups' => [],
    'be_users' => [],
];

$importTables = [
    //'tx_lpcbase_domain_model_sociallink',
    //'tx_captcha_ip',
    'tx_lpcpetition_domain_model_entry',
    'tx_lpcpetition_domain_model_field',
    'tx_lpcpetition_domain_model_entry',
    'tx_lpcpetition_domain_model_field',
    'tx_powermail_domain_model_answer',
    'tx_powermail_domain_model_answers',
    'tx_powermail_domain_model_field',
    'tx_powermail_domain_model_fields',
    'tx_powermail_domain_model_form',
    'tx_powermail_domain_model_forms',
    'tx_powermail_domain_model_mail',
    'tx_powermail_domain_model_mails',
    'tx_powermail_domain_model_page',
    'tx_powermail_domain_model_pages',
];
############################################
# Database credentials
############################################

if (!isset($sourceCredentials)) {
    $sourceCredentials = [
        'host' => 'db',
        'username' => 'root',
        'password' => 'root',
        'database' => 'db2',
        'port' => '3306',
    ];
}

if (!isset($targetCredentials)) {
    $targetCredentials = [
        'host' => 'db',
        'username' => 'root',
        'password' => 'root',
        'database' => 'db',
        'port' => '3306',
    ];
}

############################################
# Beginning of the script
############################################

$logger = new Logger();

$dbSource = new Ecodev\Database(
    $sourceCredentials['host'],
    $sourceCredentials['username'],
    $sourceCredentials['password'],
    $sourceCredentials['port'],
);
$dbSource->connect($sourceCredentials['database']);

$dbTarget = new Ecodev\Database(
    $targetCredentials['host'],
    $targetCredentials['username'],
    $targetCredentials['password'],
    $targetCredentials['port'],
);

$dbTarget->connect($targetCredentials['database']);

// Synchronize tables
foreach ($synchronizeTables as $synchronizeTable) {
    $logger->log('Synchronizing table "' . $synchronizeTable . '" .....');
    $fieldStructures = $dbTarget->select('SHOW COLUMNS FROM ' . $synchronizeTable);
    $newFieldsNames = [];
    foreach ($fieldStructures as $fieldStructure) {
        $newFieldsNames[] = $fieldStructure['Field'];
    }

    // Build clause part of the request
    $clause = '1 = 1';
    $specialFields = ['deleted', 'disable'];
    foreach ($specialFields as $specialField) {
        if (in_array($specialField, $newFieldsNames)) {
            $clause .= ' AND ' . $specialField . ' = 0';
        }
    }
    //$toImportValues = $dbOld->select('SELECT * FROM ' . $synchronizeTable . ' WHERE ' . $clause);
    $toImportValues = $dbSource->select('SELECT * FROM ' . $synchronizeTable . ' WHERE ' . $clause);

    $dbTarget->delete($synchronizeTable); //truncating table

    /*
    $dbNew->query('TRUNCATE TABLE '. $table); => other way to do it
    */
    foreach ($toImportValues as $index => $toImportValue) {
        // We sanitize the data by removing the unwanted fields
        foreach ($toImportValue as $fieldName => $value) {
            if (!in_array($fieldName, $newFieldsNames)) {
                unset($toImportValue[$fieldName]);
            }
        }

        foreach ($synchronizeTablesDefaultValues[$synchronizeTable] as $fieldName => $value) {
            if (!$toImportValue[$fieldName]) {
                $toImportValue[$fieldName] = $synchronizeTablesDefaultValues[$synchronizeTable][$fieldName];
            }
        }
        $dbTarget->insert($synchronizeTable, $toImportValue);
    }
    $logger->log('Synchronized!!!');
}
$logger->log('All tables synchronized successfully!!!');

// Import tables
foreach ($importTables as $importTable) {
    $logger->log('Import whole content of table: "' . $importTable . '"');
    $exportCommand = sprintf(
        'mysqldump -u %s -p"%s" -h %s %s %s > /tmp/%s.sql',
        $sourceCredentials['username'],
        $sourceCredentials['password'],
        $sourceCredentials['host'],
        $sourceCredentials['database'],
        $importTable,
        $importTable,
    );

    exec($exportCommand);
    $importCommand = sprintf(
        'mysql -u %s -p"%s" -h %s %s < /tmp/%s.sql',
        $targetCredentials['username'],
        $targetCredentials['password'],
        $sourceCredentials['host'],
        $targetCredentials['database'],
        $importTable,
    );

    exec($importCommand);
    $logger->log('Done for: ' . $importTable . '!!!');
}
$logger->log('All tables imported successfully in the new database!!!');
$dbTarget->update(
    'pages',
    [
        'slug' => '/',
        'backend_layout' => 'pagets__default',
        'backend_layout_next_level' => 'pagets__default',
        'TSconfig' => '',
    ],
    ['uid' => '1'],
);
$dbTarget->update('pages', ['slug' => '/home'], ['uid' => '7']);
$dbTarget->update('pages', ['slug' => '/zugang-life-archivech'], ['uid' => '21']);
$dbTarget->update('pages', ['slug' => '/tests/nach-62-update'], ['uid' => '20']);

$dbTarget->query(
    'ALTER TABLE `tx_powermail_domain_model_field`  ADD `page` INT UNSIGNED DEFAULT 0 NOT NULL  AFTER `uid`',
);
$dbTarget->query(
    'ALTER TABLE `tx_powermail_domain_model_page`  ADD `form` INT UNSIGNED DEFAULT 0 NOT NULL  AFTER `uid`',
);
//CREATE INDEX `form` ON `tx_powermail_domain_model_mail` (form)
$dbTarget->query('CREATE INDEX `form` ON `tx_powermail_domain_model_mail` (form)');
$dbTarget->query('CREATE INDEX `feuser` ON `tx_powermail_domain_model_mail` (feuser)');
$dbTarget->update('tx_powermail_domain_model_field', ['page' => '1']);
$dbTarget->update('tx_powermail_domain_model_page', ['form' => '1'], ['uid' => '1']);

