<?php

namespace DatabaseDonor;


class Transformer
{
    static array $types = [
        'CHAR' => 'string',
        'VARCHAR' => 'string',
        'TEXT' => 'string',
        'TINYTEXT' => 'string',
        'MEDIUMTEXT' => 'string',
        'LONGTEXT' => 'string',

        'DATE' => 'date',
        'TIME' => 'date',
        'DATETIME' => 'date',
        'TIMESTAMP' => 'date',
        'YEAR' => 'date',

        'TINYINT' => 'numeric',
        'SMALLINT' => 'numeric',
        'MEDIUMINT' => 'numeric',
        'INT' => 'numeric',
        'BIGINT' => 'numeric',

        'DECIMAL' => 'float',
        'FLOAT' => 'float',
        'DOUBLE' => 'float',
        'NUMERIC' => 'float',

    ];

    static array $defaults;

    static array $settings;

    /**
     * @param array $defaults
     * @return void
     */
    public static function set_defaults(array $defaults): void
    {
        self::$defaults = $defaults;
    }

    /**
     * @param array $settings
     * @return void
     */
    public static function set_settings(array $settings): void
    {
        self::$settings = $settings;
    }

    public static function cast(mixed $value, array $needed_desc, array $current_desc): mixed
    {
        $needed_subtype = self::get_subtype($needed_desc['Type']);

        $return_value = 'null';

        if ($value === null && $needed_subtype !== 'date') {
            if ($needed_desc['Null'] === 'YES') return 'null';
            else {
                if (self::$settings['default_null_values']) return self::get_prepared_sql_value(self::$defaults[strtoupper($needed_desc['Type'])] , $needed_desc['Type']);
                else throw new \Exception('Target column can\'t be null');
            }
        }

        try {
            switch ($needed_subtype) {
                case 'string':
                    $return_value = self::cast_string_subtype($value, $needed_desc['Type']);
                    break;
                case 'numeric':
                    $return_value = self::cast_numeric_subtype($value, $needed_desc['Type']);
                    break;
                case 'date':
                    $return_value = self::cast_date_subtype($value, $needed_desc);
                    break;
                case 'float':
                    $return_value = self::cast_float_subtype($value, $needed_desc['Type']);
                    break;
            }
        } catch (\Exception $e) {
            Logger::log('Transformer Error: ' . $e->getMessage());
            throw new \Exception("Transformer Error: Can't transform value.");
        }

        return self::get_prepared_sql_value($return_value, $needed_desc['Type']);
    }


    private static function cast_date_subtype(mixed $value, array $to_type): string
    {
        if ($value === null) {
            if ($to_type['Null'] === 'NO' && !self::$settings['default_null_values'])
                throw new \Exception("Null can't be transformed to date type. To proceed with null values switch default_null_values setting to true");
            return self::$defaults[strtoupper($to_type['Type'])];
        }

        $phpdate = strtotime($value);

        if (!$phpdate && !self::$settings['transform_all']) throw new \Exception("Value can't be transformed to date type.");

        if (!$phpdate) {
            $mysqldate = self::$defaults[strtoupper($to_type['Type'])];
        } else {
            $format = match (strtoupper($to_type['Type'])) {
                'DATE' => 'Y-m-d',
                'TIME' => 'H:i:s',
                'YEAR' => 'Y',
                default => 'Y-m-d H:i:s',
            };
            $mysqldate = date( $format, $phpdate );
        }

        return $mysqldate;
    }

    private static function cast_float_subtype(mixed $value, string $to_type): float
    {
        if (!is_numeric($value)) throw new \Exception("Value can't be transformed to numeric type.");

        $params = self::get_max_decimal_value($to_type);

        $value = round($value, $params['precision'][1]);

        if ($params['unsigned']) {
            if ((($params['max_values'][0] > $value)
                    || ($params['max_values'][1] < $value))
                && !self::$settings['transform_all'])
                throw new \Exception('Value out of range');

            $return_value = ($params['max_values'][0] <= $value) && ($value <= $params['max_values'][1]) ? $value : 0;
        } else {
            if ($value > $params['max_values'] && !self::$settings['transform_all']) throw new \Exception('Value out of range');

            $return_value = $value > $params['max_values'] ? $params['max_values'] : $value;
        }

        return $return_value;
    }

    protected static function get_max_decimal_value($type): array
    {
        $type = self::get_decimal_type_with_params($type);

        if(isset($type['type'])) {
            $str = str_repeat('9',$type['precision'][0] - $type['precision'][1]) . '.' . str_repeat('9', $type['precision'][1]);

            $type['max_values'] = $type['unsigned'] ? floatval($str) : [floatval($str) * -1, floatval($str)];
        }
        return $type;
    }

    protected static function get_decimal_type_with_params($type): array
    {
        $ret = [
            'unsigned' => str_contains(strtoupper($type), "UNSIGNED"),
            'precision' => self::get_decimal_precision_from_type_string($type)
        ];
        $type = strtoupper($type);
        $type_found = false;

        if (str_contains($type, 'DOUBLE'))
        {
            $type_found = true;
            $ret['type'] = 'DOUBLE';
        }

        if (!$type_found && str_contains($type, 'FLOAT'))
        {
            $type_found = true;
            $ret['type'] = 'FLOAT';
        }

        if (!$type_found && (str_contains($type, 'DECIMAL') || str_contains($type, 'NUMERIC')))
        {
            $ret['type'] = 'DECIMAL';
        }

        return $ret;
    }

    protected static function get_decimal_precision_from_type_string($type): array
    {
        $ret = [0, 0];
        $string = ' ' . $type;
        $ini = strpos($string, '(');
        if ($ini == 0) {
            $ret[1] = 10;
        } else {
            $ini += strlen('(');
            $len = strpos($string, ',', $ini) - $ini;
            $ret[0] = intval(substr($string, $ini, $len));
        }

        $ini2 = strpos($string, ',');
        if ($ini2 == 0) {
            $ret[2] = 0;
        } else {
            $ini2 += strlen(',');
            $len2 = strpos($string, ')', $ini2) - $ini2;
            $ret[1] = intval(substr($string, $ini2, $len2));
        }

        return $ret;
    }


    protected static function cast_numeric_subtype(mixed $value, string $to_type): int
    {
        if (!is_numeric($value)) throw new \Exception("Value can't be transformed to numeric type.");

        $max_value = self::get_max_numeric_value($to_type);

        if (is_array($max_value))
        {
            if ((($max_value[0] > $value) || ($max_value[1] < $value)) && !self::$settings['transform_all']) throw new \Exception('Value out of range');

            // TODO: return max or min value for else case
            $return_value = ($max_value[0] <= $value) && ($value <= $max_value[1]) ? round(intval($value)) : 0;

        } else {

            if ($value > $max_value && !self::$settings['transform_all']) throw new \Exception('Value out of range');

            $return_value = $value > $max_value ? $max_value : round(intval($value));

        }

        return $return_value;
    }


    protected static function cast_string_subtype(mixed $value, string $to_type): string
    {
        $max_chars = self::get_max_chars_count($to_type);

        if ((strlen($value) > $max_chars))
        {
            if (!self::$settings['transform_all']) throw new \Exception("Value is too long to insert.");

            $value = substr($value, 0, $max_chars);
        }

        return self::get_prepared_sql_value($value, $to_type);
    }


    public static function get_prepared_sql_value(mixed $value, $needed_type): string
    {
        return match (self::get_subtype($needed_type)) {
            'string', 'date' => "'" . strval($value) . "'",
            'numeric', 'float' => "$value",
            default => 'null',
        };
    }


    protected static function get_max_chars_count($type): int
    {
        $count = 0;
        if (str_contains(strtoupper($type), "CHAR"))
        {
            $count = self::get_char_count_from_char_type($type);
        } else {
            switch (strtoupper($type)) {
                case "TEXT":
                    $count = 65535;
                case "TINYTEXT":
                    $count = 255;
                case "MEDIUMTEXT":
                    $count = 16777215;
                case "LONGTEXT":
                    $count = 42944967295;
            }
        }

        return $count;
    }


    protected static function get_max_numeric_value($type): int|array
    {
        $type = self::get_numeric_type_from_string($type);
        $unsigned = str_contains(strtoupper($type), "UNSIGNED");

        if($type) {
            switch ($type) {
                case 'TINYINT':
                    return $unsigned ? 127 : [-128, 127];
                case 'SMALLINT':
                    return $unsigned ? 32767 : [-32768, 32767];
                case 'MEDIUMINT':
                    return $unsigned ? 8388607 : [-8388608, 8388607];
                case 'INT':
                    return $unsigned ? 2147483647 : [-2147483648, 2147483647];
                case 'BIGINT':
                    return $unsigned ? 9223372036854775807 : [-9223372036854775808, 9223372036854775807];
            }
        }
        return 0;
    }


    /**
     * Get clear MySQL type from pdo type string
     * @param $type
     * @return string|bool
     */
    public static function get_numeric_type_from_string($type): string|bool
    {
        $num_types = ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT'];

        foreach ($num_types as $num_type)
        {
            if (str_contains(strtoupper($type), $num_type)) return $num_type;
        }
        return false;
    }

    /**
     * Get maximum string length for CHAR and VARCHAR types. Value in brackets.
     * @param $string
     * @return int
     */
    public static function get_char_count_from_char_type($string): int
    {
        $string = ' ' . $string;
        $ini = strpos($string, '(');
        if ($ini == 0) return 0;
        $ini += strlen('(');
        $len = strpos($string, ')', $ini) - $ini;
        return (int) substr($string, $ini, $len);
    }

    /**
     * Check two values for transform-ability due to settings
     * @param string $needed_type
     * @param string $import_type
     * @param bool $accept_all
     * @return bool
     */
    public static function check(string $needed_type, string $import_type, bool $accept_all): bool
    {
        // if types are fully identical pass all checks
        if (strtoupper($needed_type) === strtoupper($import_type)) return true;

        if (!self::are_same_subtypes($needed_type, $import_type))
        {
            $needed_subtype = self::get_subtype($needed_type);
            $imported_subtype = self::get_subtype($import_type);

            if ($needed_subtype === 'string') return true;

            // floats and integers can't be cast to each other without data loss
            if ((($needed_subtype === 'numeric' && $imported_subtype === 'float')
                    || ($imported_subtype === 'numeric' && $needed_subtype === 'float')) && !$accept_all
            ) {
                return false;
            }
        } else {
            // time and year can't be cast to each other without data loss
            if (((strtoupper($needed_type) === 'TIME' && strtoupper($import_type) === 'YEAR')
                    || (strtoupper($import_type) === 'TIME' && strtoupper($needed_type) === 'YEAR'))
                && !$accept_all
            ){
                return false;
            }
        }
        return true;
    }

    /**
     * Compare subtypes for MYSQL types
     * @param string $needed_type
     * @param string $import_type
     * @return bool
     */
    public static function are_same_subtypes(string $needed_type, string $import_type): bool
    {
        return self::get_subtype($needed_type) === self::get_subtype($import_type);
    }

    /**
     * Get subtype from SQL type (varchar(123) => string)
     * @param $haystack
     * @return string|bool
     */
    public static function get_subtype($haystack): string|bool
    {
        foreach (self::$types as $type => $subtype) {
            if (str_contains(strtoupper($haystack), $type)) return $subtype;
        }
        return false;
    }

    /**
     * If inserted value is null - check if it can be defaulted (null if it can be NULL)
     * @param array $needed_desc
     * @return string
     */
    public static function get_undefined_column_value(array $needed_desc): string
    {
        if ($needed_desc['Null'] === 'YES') return 'null';

        // separate brackets-contained string( VARCHAR(123) => VARCHAR )
        $type = strtoupper(substr($needed_desc['Type'], 0, strpos($needed_desc['Type'], '(')));

        if (isset(self::$defaults[$type])) {
            return self::get_prepared_sql_value(self::$defaults[$type], $needed_desc['Type']);
        }

        return self::get_prepared_sql_value(self::$defaults[strtoupper($needed_desc['Type'])], $needed_desc['Type']);
    }

    protected function __construct() { }

    protected function __clone() { }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize.");
    }


}