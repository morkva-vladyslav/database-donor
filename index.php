<?php

require_once 'vendor/autoload.php';

use DatabaseDonor\Database;
use DatabaseDonor\Transformer;

$relations = require 'relations.php';

$db_donor = new Database([
    'type'      => 'mysql',
    'database'  => $relations['DONOR_DATABASE_NAME'],
    'host'      => $relations['DONOR_DATABASE_HOST'],
    'username'  => $relations['DONOR_DATABASE_USER'],
    'password'  => $relations['DONOR_DATABASE_PASSWORD'],
], $relations['donor_table_name']);

$db_patient = new Database([
    'type'      => 'mysql',
    'database'  => $relations['PATIENT_DATABASE_NAME'],
    'host'      => $relations['PATIENT_DATABASE_HOST'],
    'username'  => $relations['PATIENT_DATABASE_USER'],
    'password'  => $relations['PATIENT_DATABASE_PASSWORD'],
], $relations['patient_table_name']);


try {

    $db_patient->set_settings($relations);

    Transformer::set_defaults($relations['defaults']);
    Transformer::set_settings($relations['settings']);

    $db_patient->compare_table_types($db_donor->table_desc);

    $db_donor->select_all_rows();

    $db_patient->insert_data($db_donor);

} catch (Exception $e) {
    print_r($e->getMessage());
}



