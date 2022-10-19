<?php

return [

    // Database, from which data will be exported
    "DONOR_DATABASE_NAME" => "dummy",
    "DONOR_DATABASE_HOST" => "localhost",
    "DONOR_DATABASE_PORT" => "3306",
    "DONOR_DATABASE_USER" => "root",
    "DONOR_DATABASE_PASSWORD" => "grande",

    // Database, in which data will be imported
    "PATIENT_DATABASE_NAME" => "patient",
    "PATIENT_DATABASE_HOST" => "localhost",
    "PATIENT_DATABASE_PORT" => "3306",
    "PATIENT_DATABASE_USER" => "root",
    "PATIENT_DATABASE_PASSWORD" => "grande",


    // name of the table that should be filled
    "patient_table_name" => "floatstest",

    // name of the table from which the values should be taken
    "donor_table_name" => "INVFILE",

    // relations of columns for inserting
    "columns_relations" => [

        // "patient_column_name" => "donor_column_name"
        'f_poht' => 'F_POHT',
        'f_pottc' => 'F_POTTC',
        'f_povat' => 'F_POVAT',

    ],

    "settings" => [

        // stop inserting on first error
        "stop_on_failure" => false,

        // don't import column values to columns where autoincrement exists
        "auto_increment" => true,

        // let the system try to transform incompatible types
        "transform_types" => true,

        // if value can't be transformed - let the system insert default values
        "transform_all" => false,

        // set "defaults" if value is not specified target column can't be NULL
        "default_null_values" => true,

    ],

    // this parameters will be applied if no data provided (for not null columns) or something went wrong with data types cast
    "defaults" => [

        'CHAR' => 'undefined',
        'VARCHAR' => 'undefined',
        'TEXT' => 'undefined',
        'TINYTEXT' => 'undefined',
        'MEDIUMTEXT' => 'undefined',
        'LONGTEXT' => 'undefined',

        'DATE' => date('Y-m-d'),
        'TIME' => date('H:i:s'),
        'DATETIME' => date('Y-m-d H:i:s'),
        'TIMESTAMP' => date('Y-m-d H:i:s'),
        'YEAR' => date('Y'),

        'TINYINT' => 0,
        'SMALLINT' => 0,
        'MEDIUMINT' => 0,
        'INT' => 0,
        'BIGINT' => 0,

        'DECIMAL' => 0,
        'FLOAT' => 0,
        'DOUBLE' => 0,

    ],
];