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

class TDOC_ICRACI_BACKUP_DATA_TABLE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:t-doc-bak {--index=}';

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
        if (Schema::hasTable('INTEGRATION_TDOC_ICRACI_BACKUP_DATA_TABLE') === false) {
            Schema::create('INTEGRATION_TDOC_ICRACI_BACKUP_DATA_TABLE', static function (Blueprint $table) {
                $table->integer('EMP_ID')->default(0);
                $table->integer('DOC_ID')->default(0);
                $table->string('SYS_DATE')->nullable();
                $table->integer('CURRENT_MESUL')->default(-1);
                $table->integer('TDOC_ICRACI_ID')->default(-1);
                $table->integer('DEP_ID')->default(-1);
            });
            $this->info('[x] Table Created');
        }
        ini_set('memory_limit', '10000000000');
        $path = '/files/TDOC_ICRACI_BACKUP_DATA_TABLE.xlsx';
        $filePath = public_path($path);
        $reader = ReaderEntityFactory::createXLSXReader($path);
        try {
            $reader->open($filePath);
            $this->info('[x] File opened');
        } catch (IOException|Exception $e) {
            $this->output->warning($e->getMessage());
        }

        try {
            $data = [];
            $fieldNames = [
                'EMP_ID',
                'DOC_ID',
                'SYS_DATE',
                'CURRENT_MESUL',
                'TDOC_ICRACI_ID',
                'DEP_ID'
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
                        foreach ($this->chunk($data, 349) as $chunk) {
                            $count += count($chunk);
                            DB::transaction(function () use ($chunk, $count, $sheetKey) {
                                if (DB::table('INTEGRATION_TDOC_ICRACI_BACKUP_DATA_TABLE')->insert($chunk)) {
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
