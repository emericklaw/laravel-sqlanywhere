<?php

namespace emericklaw\SQLAnywhere;

use Illuminate\Database\Connection;
use \emericklaw\SQLAnywhereClient;

class SQLAnywhereConnection extends Connection
{

    /**
     * Create a new database connection instance.
     *
     * @param  PDO     $pdo
     * @param  string  $database
     * @param  string  $tablePrefix
     * @param  array   $config
     * @return void
     */
    public function __construct(SQLAnywhereClient $pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        $this->pdo = $pdo;

        // First we will setup the default properties. We keep track of the DB
        // name we are connected to since it is needed when some reflective
        // type commands are run such as checking whether a table exists.
        $this->database = $database;

        $this->tablePrefix = $tablePrefix;

        $this->config = $config;

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        // new version since Laravel 5.4
        // /vendor/laravel/framework/src/Illuminate/Database/Connection.php
        //  --> function: select(...)
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) return [];

            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $statement = $this->getReadPdo()->prepare($query);

            $statement->execute($this->prepareBindings($bindings));

            return $statement->fetchAll();
        });
    }

    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            // First we will create a statement for the query. Then, we will set the fetch
            // mode and prepare the bindings for the query. Once that's done we will be
            // ready to execute the query against the database and return the cursor.
            $statement = $this->getReadPdo()->prepare($query);

            $statement->execute($this->prepareBindings($bindings));

            return $statement;
        });

        while ($record = $statement->fetch()) {
            yield $record;
        }
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) return 0;

            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
            $statement = $this->getPdo()->prepare($query);

            $statement->execute($this->prepareBindings($bindings));

            return $statement->affectedRows();
        });
    }

    /**
     * Get the default query grammar instance.
     *
     * @return Illuminate\Database\Query\Grammars\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new SQLAnywhereQueryGrammar($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return Illuminate\Database\Schema\Grammars\Grammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return new SQLAnywhereSchemaGrammar($this);
    }
}
