<?php

namespace DatabaseDonor;

use DatabaseDonor\Logger;
use http\Exception;

class Database
{
    /**
     * @var \PDO Database connection
     */
    public \PDO $pdo;

    /**
     * @var string
     */
    public string $table_name;

    /**
     * @var array Table description
     */
    public array $table_desc = [];

    public array $donor_desc = [];

    /**
     * @var array Settings specified in relations.php
     */
    private array $settings;

    /**
     * @var array Columns relations specified in relations.php
     */
    private array $relations;

    /**
     * @var array|false Data for insertion
     */
    public array|false $data;

    /**
     * @var array Column names that have difference with donor columns
     */
    protected array $needs_to_be_cast;

    /**
     * @var array
     */
    protected array $keys_to_insert;

    /**
     * @param array $cred
     * @param string $table_name
     */
    public function __construct(array $cred, string $table_name)
    {
        $this->table_name = $table_name;

        $dsn = sprintf('%s:dbname=%s;host=%s',
            $cred['type'],
            $cred['database'],
            $cred['host']);

        try {
            $this->pdo = new \PDO($dsn, $cred['username'], $cred['password']);
            $this->desc_table();
        }
        catch (\PDOException $e) {
            throw new \PDOException($e->getMessage());
        }
    }

    /**
     * Set table description
     * @return array
     */
    public function desc_table(): array
    {
        $stm = $this->pdo->prepare("DESC $this->table_name");
        $stm->execute();
        $data = $stm->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $item)
        {
            $this->table_desc[$item['Field']] = $item;
        }

        return $this->table_desc;
    }

    /**
     * Set settings and relations from relations.php
     * @param $relations
     * @return void
     */
    public function set_settings($relations): void
    {
        $this->settings = $relations['settings'];
        $this->relations = $relations['columns_relations'];
    }

    /**
     * Check columns for types. With specific settings types can be casted if needed
     * @param array $table_to_compare
     * @param array $relations
     * @return void
     * @throws \Exception
     */
    public function compare_table_types(array $table_to_compare): void
    {

        $this->donor_desc = $table_to_compare;

        foreach ($this->table_desc as $key => $values) {

            if (!isset($this->relations[$key]))  {
                if ($this->table_desc[$key]['Null'] !== 'YES'
                    && strtoupper($this->table_desc[$key]['Extra']) !== 'AUTO_INCREMENT') throw new \Exception('Incompatible column types. Check logs for information.');
                continue;
            }

            $column_to_compare = $this->relations[$key];

            $compare_type = $table_to_compare[$column_to_compare]['Type'];
            $current_type = $values['Type'];

            if ($compare_type !== $current_type)
            {
                // TIMESTAMP and DATETIME can be cast to each other
                if((strtoupper($compare_type) === "TIMESTAMP" && strtoupper($current_type) === "DATETIME")
                    || (strtoupper($compare_type) === "DATETIME" && strtoupper($current_type) === "TIMESTAMP")) continue;

                $this->needs_to_be_cast[] = $key;

                $message = "Incompatible types for $key and $column_to_compare ($current_type != $compare_type)";
                Logger::log($message);
                print_r($message . PHP_EOL);

                if (!$this->settings['transform_types']
                    || !$this->can_be_transformed($current_type, $compare_type, $this->settings['transform_all'])){
                    throw new \Exception('Incompatible column types. Check logs for information.');
                }
            }
        }
    }

    /**
     * Check if columns values can transform to needed type
     * @param string $type1
     * @param string $type2
     * @param $accept_all
     * @return bool
     */
    protected function can_be_transformed(string $type1, string $type2, $accept_all): bool
    {
        return Transformer::check($type1, $type2, $accept_all);
    }

    /**
     * TODO: add limit
     * Select data from specified table
     * @param string $limit
     * @return array
     */
    public function select_all_rows(string $limit = ""): array
    {
        try {
            $stm = $this->pdo->prepare("SELECT * FROM $this->table_name");
            $stm->execute();
            $this->data = $stm->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage());
        }

        return $this->data;
    }


    public function insert_data(Database $donor)
    {
        if (!is_array($donor->data) || empty($donor->data)) {
            throw new \Exception('No data provided or something went wrong. Check logs for more info.');
        }

        $columns = $this->prepare_keys_to_insert();

        foreach ($donor->data as $item_to_insert)
        {
            $stm = sprintf("INSERT INTO %s (%s) VALUES (%s)",
                $this->table_name,
                $columns,
                $this->prepare_values_to_insert($item_to_insert, $columns)
            );

            Logger::logSQLstm($stm);

            try {
                $stm = $this->pdo->prepare($stm);
                $stm->execute();
            } catch (\PDOException $e) {
                Logger::log($e->getMessage());
                if ($this->settings['stop_on_failure']) throw new \PDOException($e->getMessage());
            }
        }
    }

    /**
     * Prepare column names for bindValue to be inserted
     * @return string
     */
    private function prepare_keys_to_insert(): string
    {
        $substring = "";
        $counter = 1;

        foreach ($this->table_desc as $key => $value)
        {
            if ((strtoupper($value['Extra']) === "AUTO_INCREMENT") && $this->settings['auto_increment']) {
                $counter++;
                continue;
            }

            // TODO: add defaulted keys
//            if ((strtoupper($value['Null']) === "YES") && (!$this->column_has_relation($key)) && $this->settings['default_null_values'])
//            {
//                $substring .= $key;
//            }

            $substring .= $key;
            $this->keys_to_insert[] = $key;

            if ($counter < count($this->table_desc)) $substring .= ", ";
            $counter++;
        }

        return $substring;
    }

    /**
     * Check if column name specified in relations array
     * @param $key
     * @return bool
     */
    private function column_has_relation($key): bool
    {
        return in_array($key, $this->relations);
    }

    /**
     * Prepare insert values, transform them if needed
     * @param array $item_to_insert
     * @return string
     */
    private function prepare_values_to_insert(array $item_to_insert, $keys_substring): string
    {
        $reverted_relations = $this->revert_relations();

        $substring = $keys_substring;

        foreach ($item_to_insert as $key => $value) {

            // if column key exist in relations array
            if (isset($reverted_relations[$key]) && in_array($reverted_relations[$key], $this->keys_to_insert)) {

                if (in_array($reverted_relations[$key], $this->needs_to_be_cast)) {
                    try {
                        $needed_value = Transformer::cast(
                            $value,
                            $this->table_desc[$reverted_relations[$key]],
                            $this->donor_desc[$key]);

                        $substring = $this->replace_key($reverted_relations[$key], $needed_value , $substring);
                    } catch (\Exception $e) {
                        if ($this->settings['stop_on_failure']) {
                            throw new \Exception($e->getMessage());
                        }
                        Logger::log($e->getMessage() . " | Column $key, Value: $value;");
                    }
                } else {

                    $string = $value !== null
                        ? Transformer::get_prepared_sql_value($value, $this->table_desc[$reverted_relations[$key]]['Type'])
                        : Transformer::get_undefined_column_value($this->table_desc[$reverted_relations[$key]]);

                    $substring = $this->replace_key($reverted_relations[$key], $string , $substring);

                }

            }

        }

       // TODO: check flow, comma appears. Temp solution
        if (str_ends_with($substring, ', ')) {
            $substring = rtrim($substring, ", ");
        }

        return $substring;
    }

    private function revert_relations(): array
    {
        $ret = [];
        foreach ($this->relations as $key => $value) {
            $ret[$value] = $key;
        }
        return $ret;
    }

    protected function replace_key($search, $replace, $subject)
    {
        if (($pos = strpos($subject, $search)) !== false) {
            return substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;
    }


}