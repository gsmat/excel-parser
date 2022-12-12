<?php

namespace App\Console\Commands;

use Box\Spout\Common\Exception\IOException;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Carbon\Carbon;
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
        $argument = $this->option('filePath');
        $filePath = public_path($argument);
        $reader = ReaderEntityFactory::createXLSXReader($argument);
        $fileName = basename($filePath, '.xlsx');
        try {
            $reader->open($filePath);
            $this->info('[x] File opened');
        } catch (IOException|\Exception $e) {
            return $e->getMessage();
        }
        try {
            $fieldNames = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $key => $row) {
                    $cells = $row->getCells();
                    if ($key === 1) {
                        $this->checkTable($fileName, $cells);
                        foreach ($cells as $cell) {
                            $fieldNames[] = $cell->getValue();
                        }
                    } else {
                        $data = [];
                        $multipleArrays = new \MultipleIterator();
                        $multipleArrays->attachIterator(new \ArrayIterator($cells));
                        $multipleArrays->attachIterator(new \ArrayIterator($fieldNames));
                        foreach ($multipleArrays as $multipleArray) {
                            try {
                                $value = trim($this->checkObject($multipleArray[0]->getValue()));
                                if ($multipleArray[0]->getType() === 5) {
                                    $data[$multipleArray[1]] = (string)Carbon::make($multipleArray[0]->getValue())->format('Y-m-d h:m:s');
                                } else {
                                    if ($value === '') {
                                        (int)$value = 0;
                                    }
                                    $data[$multipleArray[1]] = $value;
                                }
                            } catch (ReflectionException|\Exception $e) {
                                return $e->getMessage();
                            }
                        }
                        try {
                            if (DB::table($fileName)->insert($data)) {
                                $this->info("[x] $key  Record Successfully inserted");
                            } else {
                                $this->warn("[x] $key  Record doesnt inserted");
                            }

                        } catch (Throwable|\Exception $e) {
                            return $e->getMessage();
                        }

                    }
                }

            }
        } catch (ReaderNotOpenedException|ReflectionException|Throwable|\Exception $e) {
            return $e->getMessage();
        }
        $reader->close();
        $this->info('Inserting process was successful!');
    }

    public function checkObject($obj)
    {
        try {
            if (is_object($obj)) {
                return $obj->format('d-m-Y h:m:s');
            }
            return $obj;
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function checkTable(string $tableName, array $fields): bool
    {
        $modifiedTableName = $tableName;
        try {
            if (Schema::hasTable($modifiedTableName) === false) {
                Schema::create($modifiedTableName, static function (Blueprint $table) use ($fields) {
                    foreach ($fields as $field) {
                        $table->string($field->getValue(), 255)->nullable();
                    }
                });
            }
        } catch (Exception|Throwable $exception) {
//            dd($exception->getMessage());
            return $exception->getMessage();
        }
        return true;

    }

}
