<?php

namespace DatabaseDonor\Tests;

use DatabaseDonor\Transformer;
use PHPUnit\Framework\TestCase;

class TransformerTest extends TestCase
{
    public function setUp(): void
    {
        $relations = [
            "settings" => [
                "stop_on_failure" => false,
                "auto_increment" => true,
                "transform_types" => true,
                "transform_all" => false,
                "default_null_values" => true,
            ],
            "defaults" => [
                'CHAR' => 'undefined',
                'VARCHAR' => 'undefined',
                'TEXT' => 'undefined',
                'TINYTEXT' => 'undefined',
                'MEDIUMTEXT' => 'undefined',
                'LONGTEXT' => 'undefined',
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

        Transformer::set_settings($relations['settings']);
        Transformer::set_defaults($relations['defaults']);
    }

    public function testSetDefaults()
    {
        $defaults = [
            'name' => 'value'
        ];
        Transformer::set_defaults($defaults);
        $this->assertEquals($defaults, Transformer::$defaults);
    }

    public function testSetSettings()
    {
        $defaults = [
            'name' => 'value'
        ];
        Transformer::set_settings($defaults);
        $this->assertEquals($defaults, Transformer::$settings);
    }

    public function testGetUndefinedColumnValue()
    {
        $needed_desc = [
            'Type' => 'varchar(80)',
            'Null' => 'YES'
        ];

        $this->assertEquals('null', Transformer::get_undefined_column_value($needed_desc));

        $needed_desc['Null'] = 'NO';

        $this->assertEquals('\'undefined\'', Transformer::get_undefined_column_value($needed_desc));

        $needed_desc['Type'] = 'decimal(2,1)';

        $this->assertEquals(0, Transformer::get_undefined_column_value($needed_desc));

        $needed_desc['Type'] = 'INT';

        $this->assertEquals(0, Transformer::get_undefined_column_value($needed_desc));
    }

    public function testGetSubtype() {
        $asserts = [
            "varchar(123)" => 'string',
            "int" => 'numeric',
            "decimal(7,4)" => 'float',
            "time" => 'date'
        ];

        foreach ($asserts as $key => $value) {
            $this->assertEquals($value, Transformer::get_subtype($key));
        }
    }

    public function testAreSameSubtypes() {
        $true_asserts = [
            "varchar(123)" => 'mediumtext',
            "int" => 'bigint unsigned',
            "decimal(7,4)" => 'float(12,6) unsigned',
            "time" => 'year'
        ];

        foreach ($true_asserts as $key => $value) {
            $this->assertEquals(true, Transformer::are_same_subtypes($key, $value));
        }

        $false_asserts = [
            "varchar(123)" => 'int',
            "int" => 'float(12,4)',
            "decimal(7,4)" => 'year',
            "time" => 'mediumtext'
        ];

        foreach ($false_asserts as $key => $value) {
            $this->assertEquals(false, Transformer::are_same_subtypes($key, $value));
        }

    }

    public function testCheck()
    {
        $asserts_same_subtypes = [
            "varchar(123)" => 'mediumtext',
            "char(12)" => "char(12)",
            "int" => 'bigint unsigned',
            "decimal(7,4)" => 'float(12,5)',
            "timestamp" => 'year',
        ];

        foreach($asserts_same_subtypes as $first => $second) {
            $this->assertEquals(true, Transformer::check($first, $second, false));
        }

        $asserts_different_subtypes = [
            "varchar(123)" => 'int',
            "int" => 'float(12,5)',
            "decimal(7,4)" => 'int',
            "timestamp" => 'date'
        ];

        foreach($asserts_different_subtypes as $first => $second) {
            $this->assertEquals(true, Transformer::check($first, $second, true));
        }

        $asserts_not_transformable = [
            "int" => 'float(12,5)',
            "decimal(7,4)" => 'int',
            "year" => 'time'
        ];

        foreach($asserts_not_transformable as $first => $second) {
            $this->assertEquals(false, Transformer::check($first, $second, false));
        }
    }

    public function testGetCharCountFromCharType()
    {
        $type = "varchar(80)";

        $this->assertEquals(80, Transformer::get_char_count_from_char_type($type));

        $type = "SOMETHING";

        $this->assertEquals(0, Transformer::get_char_count_from_char_type($type));
    }

    public function testGetNumericTypeFromString()
    {
        $arr = [
            'INT UNSIGNED AUTO_INCREMENT' => 'INT',
            'tinyint' => 'TINYINT',
            'SMALLINT PRIMARY KEY' => 'SMALLINT',
        ];

        foreach ($arr as $type => $ret) {
            $this->assertEquals($ret, Transformer::get_numeric_type_from_string($type));
        }

        $this->assertEquals(false, Transformer::get_numeric_type_from_string("UNDEFINED TYPE"));
    }
}