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

    ];

    static array $defaults;

    static array $settings;

    public static function set_defaults(array $defaults): void
    {
        self::$defaults = $defaults;
    }

    public static function set_settings(array $settings): void
    {
        self::$settings = $settings;
    }

    protected function __construct() { }

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
            Logger::log($e->getMessage());
            throw new \Exception("Can't transform value.");
        }

        return self::get_prepared_sql_value($return_value, $needed_desc['Type']);
    }

    /**
     * @param mixed $value
     * @param array $to_type
     * @return string
     * @throws \Exception
     */
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
        return 0.0;
    }

    /**
     * @param mixed $value
     * @param string $to_type
     * @return int
     * @throws \Exception
     */
    protected static function cast_numeric_subtype(mixed $value, string $to_type): int
    {
        if (!is_numeric($value)) throw new \Exception("Value can't be transformed to numeric type.");

        $max_value = self::get_max_numeric_value($to_type);

        if (is_array($max_value))
        {
            if ((($max_value[0] > $value) || ($max_value[1] < $value)) && !self::$settings['transform_all']) throw new \Exception('Value out of range');

            // TODO: return max or min value for else case
            $return_value = ($max_value[0] <= $value) && ($value <= $max_value[1]) ? intval($value) : 0;

        } else {

            if ($value > $max_value && !self::$settings['transform_all']) throw new \Exception('Value out of range');

            $return_value = $value > $max_value ? $max_value : intval($value);

        }

        return $return_value;
    }

    /**
     * @param mixed $value
     * @param string $to_type
     * @return string
     * @throws \Exception
     */
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

    /**
     * @param mixed $value
     * @param $needed_type
     * @return string
     */
    public static function get_prepared_sql_value(mixed $value, $needed_type): string
    {
        return match (self::get_subtype($needed_type)) {
            'string', 'date' => "'" . strval($value) . "'",
            'numeric', 'float' => "$value",
            default => 'null',
        };
    }

    /**
     * Get max chars count for specified string type
     * @param $type
     * @return int
     */
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

    /**
     * @param $type
     * @return int|array
     */
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
     * @param $type
     * @return string|bool
     */
    protected static function get_numeric_type_from_string($type): string|bool
    {
        $num_types = ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT'];

        foreach ($num_types as $num_type)
        {
            if (str_contains(strtoupper($type), $num_type)) return $num_type;
        }
        return false;
    }

    /**
     * Get max chars count from VARCHAR or CHAR. Value between "()"
     * @param $string
     * @return int
     */
    protected static function get_char_count_from_char_type($string)
    {
        $string = ' ' . $string;
        $ini = strpos($string, '(');
        if ($ini == 0) return 0;
        $ini += strlen('(');
        $len = strpos($string, ')', $ini) - $ini;
        return (int) substr($string, $ini, $len);
    }

    /**
     * @param string $type1
     * @param string $type2
     * @return bool
     */
    public static function check(string $needed_type, string $import_type, bool $accept_all): bool
    {
        // if types are fully identical pass all checks
        if (strtoupper($needed_type) === strtoupper($import_type)) return true;

        // if types can't be cast don't let system proceed
        if (!self::can_be_casted($needed_type, $import_type, $accept_all))
        {
            return false;
        }

        return true;
    }

    private static function can_be_casted($needed_type, $import_type, $accept_all): bool
    {
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
     * @param string $needed_type
     * @param string $import_type
     * @return bool
     */
    private static function are_same_subtypes(string $needed_type, string $import_type): bool
    {
        return self::get_subtype($needed_type) === self::get_subtype($import_type);
    }

    /**
     * @param $haystack
     * @return string|bool
     */
    private static function get_subtype($haystack): string|bool
    {
        foreach (self::$types as $type => $subtype) {
            if (str_contains(strtoupper($haystack), $type)) return $subtype;
        }
        return false;
    }

    public static function get_undefined_column_value(array $needed_desc)
    {
        if ($needed_desc['Null'] === 'YES') return 'null';

        return self::get_prepared_sql_value(self::$defaults[strtoupper($needed_desc['Type'])], $needed_desc['Type']);
    }

    protected function __clone() { }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize.");
    }


}