<?php

namespace Orchestra\Tenanti\Migrator;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migrator as BaseMigrator;
use Orchestra\Tenanti\Contracts\Notice;
use Orchestra\Tenanti\Migration;

class Migrator extends BaseMigrator
{
    /**
     * Entity.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $entity;

    /**
     * The database default connection.
     *
     * @var string|null
     */
    protected $defaultConnection;

    /**
     * Set entity for migration.
     *
     * @return $this
     */
    public function setEntity(?Model $entity)
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * Execute the given callback using the given connection as the default connection.
     *
     * @param  string|null  $name
     *
     * @return mixed
     */
    public function usingConnection($name, callable $callback)
    {
        $this->setConnection($name);

        return \tap($callback($this), function () {
            $this->resetConnection();
        });
    }

    /**
     * Set the default connection name.
     *
     * @param  string|null  $name
     *
     * @return void
     */
    public function setConnection($name)
    {
        if (! \is_null($name)) {
            $this->defaultConnection = $this->resolver->getDefaultConnection();
        }

        parent::setConnection($name);
    }

    /**
     * Reset the default connection name.
     */
    public function resetConnection(): void
    {
        if (! \is_null($this->defaultConnection)) {
            $this->resolver->connection($this->connection)->disconnect();

            $this->setConnection($this->defaultConnection);
        }
    }

    /**
     * Resolve a migration instance from a file.
     *
     * @param  string  $file
     *
     * @return object
     */
    public function resolve($file)
    {
        $class = parent::resolve($file);

        if ($class instanceof Migration) {
            $class->setConnection($this->connection);
        }

        return $class;
    }

    /**
     * Output migration notes using.
     *
     * @return $this
     */
    public function outputUsing(?Notice $notice)
    {
        if ($notice instanceof Notice) {
            $notice->mergeWith($this);
        }

        return $this;
    }

    /**
     * Run "up" a migration instance.
     *
     * @param  string  $file
     * @param  int  $batch
     * @param  bool  $pretend
     *
     * @return void
     */
    protected function runUp($file, $batch, $pretend)
    {
        $file = $this->getMigrationName($file);

        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $migration = $this->resolve($file);

        if ($pretend) {
            $this->pretendToRun($migration, 'up');

            return;
        }

        $migration->up($key = $this->entity->getKey(), $this->entity);

        // Once we have run a migrations class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a migration
        // in the application. A migration repository keeps the migrate order.
        $this->repository->log($file, $batch);

        $this->write("<info>Migrated [{$this->entity->getTable()}:{$key}]:</info> {$file}");
    }

    /**
     * Run "down" a migration instance.
     *
     * @param  string  $file
     * @param  object  $migration
     * @param  bool  $pretend
     *
     * @return void
     */
    protected function runDown($file, $migration, $pretend)
    {
        $file = $this->getMigrationName($file);

        // First we will get the file name of the migration so we can resolve out an
        // instance of the migration. Once we get an instance we can either run a
        // pretend execution of the migration or we can run the real migration.
        $instance = $this->resolve($file);

        if ($pretend) {
            $this->pretendToRun($instance, 'down');

            return;
        }

        $instance->down($key = $this->entity->getKey(), $this->entity);

        // Once we have successfully run the migration "down" we will remove it from
        // the migration repository so it will be considered to have not been run
        // by the application then will be able to fire by any later operation.
        $this->repository->delete($migration);

        $this->write("<info>Rolled back [{$this->entity->getTable()}:{$key}]:</info> {$file}");
    }

    /**
     * Pretend to run the migrations.
     *
     * @param  object  $migration
     * @param  string  $method
     *
     * @return void
     */
    protected function pretendToRun($migration, $method)
    {
        $table = $this->entity->getTable();
        $key = $this->entity->getKey();

        foreach ($this->getQueries($migration, $method) as $query) {
            $name = \get_class($migration);

            $this->write("<info>{$name} [{$table}:{$key}]:</info> {$query['query']}");
        }
    }

    /**
     * Get all of the queries that would be run for a migration.
     *
     * @param  object  $migration
     * @param  string  $method
     *
     * @return array
     */
    protected function getQueries($migration, $method)
    {
        $connection = $migration->getConnection();

        // Now that we have the connections we can resolve it and pretend to run the
        // queries against the database returning the array of raw SQL statements
        // that would get fired against the database system for this migration.
        $db = $this->resolveConnection($connection);

        return $db->pretend(function () use ($migration, $method) {
            \call_user_func([$migration, $method], $this->entity->getKey(), $this->entity);
        });
    }
}
