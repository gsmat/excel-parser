<?php

namespace App\Console\Commands;

use ArrayIterator;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Exception;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MultipleIterator;
use ReflectionException;
use Throwable;

class DOC_MOVEMENT_DATA_TABLE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:movement-data {--index=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if (Schema::hasTable('INTEGRATION_DOC_MOVEMENT_DATA_TABLE') === false) {
            Schema::create('INTEGRATION_DOC_MOVEMENT_DATA_TABLE', static function (Blueprint $table) {
                $table->integer('DOC_MOV_ID')->default(0)->nullable();
                $table->integer('DOC_ID')->default(0)->nullable();
                $table->integer('FROM_USER_ID')->default(0)->nullable();
                $table->integer('TO_USER_ID')->default(0)->nullable();
                $table->integer('FROM_DEP_ID')->default(0)->nullable();
                $table->integer('TO_DEP_ID')->default(0)->nullable();
                $table->string('DATE_SENT')->nullable();
                $table->string('DATE_RECIEVED')->nullable();
                $table->integer('STATUS_ID')->nullable();
                $table->integer('IS_ACTIVE')->nullable();
                $table->integer('SIDENOTE_ID')->nullable();
                $table->integer('MOV_LEVEL')->nullable();
                $table->text('NOTE')->nullable();
                $table->integer('IS_ORGINAL')->nullable();
                $table->integer('RETURNER_EMP_ID')->default(0);
                $table->string('DATE_SENT_SYSDATE');
                $table->string('LAST_UPDATED_DATE');
            });
            $this->info('[x] Table Created');
        }
        ini_set('memory_limit', '10000000000');
        $path = '/files/DOC_MOVEMENT_DATA_TABLE.xlsx';
        $filePath = public_path($path);
        $reader = ReaderEntityFactory::createXLSXReader($path);
        try {
            $reader->open($filePath);
            $this->info('[x] File opened');
        } catch (IOException|Exception $e) {
            dd($e->getMessage());
        }

        try {
            $data = [];
            $fieldNames = [];
            $sheetIndex = $this->option('index');
            $this->info('[x] File starting for read');
            foreach ($reader->getSheetIterator() as $sheetKey => $sheet) {
                if ($sheetKey === (int)$sheetIndex) {
                    foreach ($sheet->getRowIterator() as $key => $row) {
                        $cells = $row->getCells();
                        if ($key === 1) {
                            foreach ($cells as $cell) {
                                $fieldNames[] = $cell->getValue();
                            }
                        } else {
                            $rowData = [];
                            $multipleArrays = new MultipleIterator();
                            $multipleArrays->attachIterator(new ArrayIterator($cells));
                            $multipleArrays->attachIterator(new ArrayIterator($fieldNames));
                            foreach ($multipleArrays as $multipleArray) {
                                try {
                                    $value = $this->checkObject($multipleArray[0]->getValue());
                                    $rowData[$multipleArray[1]] = $value;
                                } catch (ReflectionException|Exception $e) {
                                    dd($e->getMessage());
                                }
                            }
                            $data[] = $rowData;
                            unset($rowData, $cells);
                        }
                    }
                    try {
                        foreach ($this->chunk($data, 100) as $chunk) {
                            DB::transaction(function () use ($chunk, $sheetKey) {
                                if (DB::table('INTEGRATION_DOC_MOVEMENT_DATA_TABLE')->insert($chunk)) {
                                    $this->output->info("[x] Record Successfully inserted to sheet number $sheetKey");
                                } else {
                                    $this->output->warning("[x] Record doesn't inserted to sheet number $sheetKey");
                                }

                            }, 6);
                        }
                    } catch (Throwable|Exception $e) {
                        dd($e->getMessage());
                    }
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
        } catch (Exception $e) {
            dd($e->getMessage());
        }
    }

    public function chunk($data, $chunkSize): Generator
    {
        for ($i = 0, $j = count($data); $i < $j; $i += $chunkSize) {
            yield array_slice($data, $i, $chunkSize);
        }
    }
}
