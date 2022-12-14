<?php

namespace App\Console\Commands;

use Box\Spout\Common\Exception\IOException;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionException;
use Throwable;

class ExcelImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:excel {--filePath=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import excel file to creatable table';

    /**
     * Execute the console command.
     *
     * @return string|void
     */
    public function handle()
    {
        ini_set('memory_limit', '10000000000');
        $argument = $this->option('filePath');
        $filePath = public_path($argument);
        $reader = ReaderEntityFactory::createXLSXReader($argument);
        $fileName = basename($filePath, '.xlsx');
        try {
            $reader->open($filePath);
            $this->info('[x] File opened');
        } catch (IOException|Exception $e) {
            dd($e->getMessage());
        }
        try {
            $fieldNames = [];
            $data = [];

            foreach ($reader->getSheetIterator() as $sheetKey => $sheet) {
//                Artisan::call('optimize:clear');
                foreach ($sheet->getRowIterator() as $key => $row) {
                    $cells = $row->getCells();
//                    dd($cells);
                    if ($key === 1) {
                        $this->checkTable($fileName, $cells);
                        foreach ($cells as $cell) {
                            dd($cell);
                            $fieldNames[] = $cell->getValue();
                        }
                    } else {
                        $rowData = [];
                        $multipleArrays = new \MultipleIterator();
                        $multipleArrays->attachIterator(new \ArrayIterator($cells));
                        $multipleArrays->attachIterator(new \ArrayIterator($fieldNames));
                        foreach ($multipleArrays as $multipleArray) {
                            try {
                                $value = $this->checkObject($multipleArray[0]->getValue());
                                if ($value === '') {
                                    $value = 'NULL';
                                }
                                $rowData[$multipleArray[1]] = trim($value);
                            } catch (ReflectionException|Exception $e) {
                                dd($e->getMessage());
                            }
                        }
                        $data[] = $rowData;
                        unset($rowData);
                    }
                    unset($cells);
                }
                try {
                    foreach ($this->chunk($data, 100) as $count => $chunk) {
                        if (DB::table('INTEGRATION_' . $fileName)->insert($chunk)) {
                            $this->info("[x] Record Successfully inserted from sheet number $sheetKey");
                        } else {
                            $this->warn("[x] Record doesnt inserted from sheet number $sheetKey");
                        }
                    }
                } catch (Throwable|Exception $e) {
//                    return $e->getMessage();
                    dd($e->getMessage());
                }
            }
        } catch (ReaderNotOpenedException|ReflectionException|Throwable|Exception $e) {
            dd($e->getMessage());
        }
        $reader->close();
        $this->info('[x] File Closed');
    }

    public function checkObject($obj)
    {
        try {
            if (is_object($obj)) {
                return $obj->format('d-m-Y h:m:s');
            }
            return $obj;
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }

    public function checkTable(string $tableName, array $fields): bool
    {
        $modifiedTableName = 'INTEGRATION_' . $tableName;
        try {
            if (Schema::hasTable($modifiedTableName) === false) {
                Schema::create($modifiedTableName, static function (Blueprint $table) use ($fields) {
                    foreach ($fields as $field) {
//                        dump($field->getStyle()->getFontSize());
                        $table->text($field->getValue(), 65000)->nullable();
                    }
                });
                $this->info('[x] Table Created');
            } else {
                $this->info('[x] Table exist');
            }
        } catch (Exception|Throwable $exception) {
            dd($exception->getMessage());
//            return $exception->getMessage();
        }
        return true;

    }

    public function chunk($data, $chunkSize)
    {
        for ($i = 0, $j = count($data); $i < $j; $i += $chunkSize) {
            yield array_slice($data, $i, $chunkSize);
        }
    }

}
