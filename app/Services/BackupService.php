<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BackupInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use stdClass;

/**
 * Сервис бэкапа бд
 */
class BackupService
{
    const COUNT_TO_WRITE = 10000;

    /** @var BackupInfo $backupInfo */
    public $backupInfo;

    public function __construct(BackupInfo $backupInfo)
    {
        $this->backupInfo = $backupInfo;
    }

    /**
     * Создать дамп текущей базы
     *
     * @return void
     */
    public function createDump()
    {
        if (false === $this->backupInfo->inProgress()) {
            $tables = $this->getTables();

            $this->backupInfo->tableRowsCount = $tables;

            $this->lockTables(array_keys($tables));

            $tableQueries = $this->getTablesSql(array_keys($tables));
            foreach ($tableQueries as $query) {
                $this->write($query);
            }
        } else {
            $this->backupInfo->completedRowsCount++;
        }

        foreach ($this->backupInfo->tableRowsCount as $table => $count) {
            $this->writeTableRowsSql($table);
            $this->backupInfo->completedRowsCount = 0;
        }

        $this->backupInfo->completed = true;
        $this->backupInfo->save();
        $this->unlockTables();
    }

    /**
     * Записать в файл дампа
     *
     * @param string $content
     *
     * @return void
     */
    private function write(string $content)
    {
        Storage::append($this->backupInfo->dumpName, $content);
    }

    /**
     * Список таблиц и кол-вом записей
     *
     * @return int[]
     */
    private function getTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $tables = collect($tables)->map(function (stdClass $value) {
            foreach ($value as $key => $name) {
                return $name;
            }
        });

        $result = [];
        foreach ($tables as $table) {
            $result[$table] = DB::table($table)->count();
        }

        return $result;
    }

    /**
     * Список запросов для создания таблиц
     *
     * @param string[] $tables Список таблиц
     *
     * @return string[]
     */
    private function getTablesSql(array $tables): array
    {
        $queries = [];
        foreach ($tables as $table) {
            $queries[] = DB::select(sprintf('SHOW CREATE TABLE `%s`', $table));
        }
        $queries = collect($queries)->map(function (array $value) {
            foreach ($value as $key => $name) {
                return get_object_vars($name)['Create Table'] .= ';';
            }
        });

        return $queries->toArray();
    }

    /**
     * Записать запросы для данных таблицы
     *
     * @param string $table
     */
    private function writeTableRowsSql(string $table)
    {
        $offset = $this->backupInfo->completedRowsCount;
        $limit  = DB::table($table)->count() - $offset;

        $text  = '';
        $count = 0;
        foreach (DB::table($table)->offset($offset)->limit($limit)->cursor() as $row) {
            $row = json_decode(json_encode($row), true);
            foreach ($row as &$value) {
                $value = sprintf('"%s"', $value);
            }

            $query = sprintf('INSERT INTO `%s` (%s) VALUES (%s);', $table, implode(",", array_keys($row)), implode(',', $row));
            $text .= $query . PHP_EOL;
            $count++;

            if ($count === $this->backupInfo->tableRowsCount[$table] || static::COUNT_TO_WRITE === $count) {
                $this->write($text);
                $this->backupInfo->completedRowsCount += $count;
                $this->backupInfo->save();
                $count = 0;
            }


        }
        unset($this->backupInfo->tableRowsCount[$table]);
    }

    /**
     * Анлок таблиц
     *
     * @return void
     */
    private function unlockTables()
    {
        DB::unprepared('UNLOCK TABLES');
    }

    /**
     * Лок таблиц
     *
     * @return void
     */
    private function lockTables(array $tables)
    {
        $text = '';
        foreach ($tables as $index => $table) {
            $text .= $table . ' WRITE';
            $text .= $index === count($tables) - 1 ? ';' : ', ';
        }
        DB::unprepared("LOCK TABLES $text");
    }
}
