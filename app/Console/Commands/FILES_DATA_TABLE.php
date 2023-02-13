<?php

namespace App\Console\Commands;

use ArrayIterator;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MultipleIterator;
use ReflectionException;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class FILES_DATA_TABLE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:files {--index=}';

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
    public function handle()
    {
        if (Schema::hasTable('INTEGRATION_FILES_DATA_TABLE') === false) {
            Schema::create('INTEGRATION_FILES_DATA_TABLE', static function (Blueprint $table) {
                $table->integer('FILE_ID')->default(0);
                $table->string('FILE_PATH')->nullable();
                $table->integer('DOC_ID')->default(0);
                $table->integer('FILE_STATUS_ID')->default(0);
                $table->integer('FILES_TEXT_ID')->default(0);
                $table->string('FILE_NAME')->nullable();
                $table->integer('EMP_ID')->default(0);
                $table->integer('MOV_LEVEL')->default(-1)->comment('default value minus one');
                $table->integer('IS_VISIBLE')->default(-1)->comment('default value minus one');
                $table->integer('FILE_TYPE_ID')->default(0)->comment('default value zero');
                $table->string('SYS_DATE')->nullable();
            });
            $this->info('[x] Table Created');
        }
        ini_set('memory_limit', '10000000000');
        $path = '/files/FILES_DATA_TABLE.xlsx';
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
            $fieldNames = ['FILE_ID',
                'FILE_PATH',
                'DOC_ID',
                'FILE_STATUS_ID',
                'FILES_TEXT_ID',
                'FILE_NAME',
                'EMP_ID',
                'MOV_LEVEL',
                'IS_VISIBLE',
                'FILE_TYPE_ID',
                'SYS_DATE'
            ];
            $sheetIndex = $this->option('index');
            $this->info('[x] File starting for read');
            foreach ($reader->getSheetIterator() as $sheetKey => $sheet) {
                if ($sheetKey === (int)$sheetIndex) {
                    foreach ($sheet->getRowIterator() as $key => $row) {
                        if (((int)$sheetIndex === 1 && $key > 1) || ((int)$sheetIndex > 1 && $key >= 1)) {
                            $cells = $row->getCells();
                            $rowData = [];
                            $multipleArrays = new MultipleIterator();
                            $multipleArrays->attachIterator(new ArrayIterator($cells));
                            $multipleArrays->attachIterator(new ArrayIterator($fieldNames));
                            foreach ($multipleArrays as $multipleArray) {
                                try {
                                    $value = $this->checkObject($multipleArray[0]->getValue());
                                    $rowData[$multipleArray[1]] = trim($value);
                                } catch (ReflectionException|Exception $e) {
                                    $this->output->error($e->getMessage());
                                }
                            }
                            $data[] = $rowData;
                            unset($rowData, $cells);
                        }


                    }
                    try {
                        $count = 0;
                        foreach ($this->chunk($data, 116) as $chunk) {
                            $count += count($chunk);
                            DB::transaction(function () use ($chunk, $count, $sheetKey) {
                                if (DB::table('INTEGRATION_FILES_DATA_TABLE')->insert($chunk)) {
                                    $this->info("[x] $count Record Successfully inserted to sheet number $sheetKey");
                                } else {
                                    $this->warn("[x] Record doesn't inserted to sheet number $sheetKey");
                                }

                            }, 6);
                        }
                    } catch (Throwable|Exception $e) {
                        $this->output->error($e->getMessage());
                    }
                }
            }
        } catch (ReaderNotOpenedException|ReflectionException|Throwable|Exception $e) {
            $this->output->error($e->getMessage());
        }
        $reader->close();
        $this->info('[x] File Closed');

        return CommandAlias::SUCCESS;
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

    public function chunk($data, $chunkSize): \Generator
    {
        for ($i = 0, $j = count($data); $i < $j; $i += $chunkSize) {
            yield array_slice($data, $i, $chunkSize);
        }
    }
}
