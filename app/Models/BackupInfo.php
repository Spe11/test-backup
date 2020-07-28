<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Информация о прогрессе бэкапа
 */
class BackupInfo
{
    /** @var string $dumpName Имя дампа */
    public $dumpName;

    /** @var int[] Информация о кол-ве записей таблиц */
    public $tableRowsCount = [];

    /** @var int Кол-во обработанных записей текущей таблицы */
    public $completedRowsCount = 0;

    /** @var bool Завершено */
    public $completed = false;

    /** @var string $fileName Файл сохранения */
    private $fileName = 'backup.txt';

    public function __construct()
    {
        $this->loadData();
    }

    /**
     * Сохранить прогресс
     *
     * @return void
     */
    public function save()
    {
        $data = json_encode([
            'name'      => $this->dumpName,
            'tables'    => $this->tableRowsCount,
            'row'       => $this->completedRowsCount,
            'completed' => $this->completed,
        ]);

        Storage::put($this->fileName, $data);
    }

    /**
     * Очистить прогресс
     *
     * @return void
     */
    public function clear()
    {
        Storage::delete($this->fileName);
    }

    /**
     * Дамп в процессе
     *
     * @return void
     */
    public function inProgress(): bool
    {
        return true === Storage::exists($this->fileName);
    }

    /**
     * Загрузить сохраненные данные
     *
     * @return void
     */
    private function loadData()
    {
        if ($this->inProgress()) {
            $data = Storage::get($this->fileName);
            $data = json_decode($data, true);

            $this->dumpName           = $data['name'];
            $this->tableRowsCount     = $data['tables'];
            $this->completedRowsCount = $data['row'];
            $this->completed          = $data['completed'];
        } else {
            $this->dumpName = $this->generateName();
        }
    }

    /**
     * Сгенерировать имя дапма
     *
     * @return string
     */
    private function generateName(): string
    {
        return sprintf('backup-%s.sql', Carbon::now()->format('Y-m-d-H-i-s'));
    }
}
