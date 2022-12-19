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
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class FILES_DATA_TABLE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:files';

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
            $fieldNames = [];
            foreach ($reader->getSheetIterator() as $sheetKey => $sheet) {
                foreach ($sheet->getRowIterator() as $key => $row) {
                    $cells = $row->getCells();
                    if ($key === 1) {
                        foreach ($cells as $cell) {
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
                        $count += count($chunk);
                        if (DB::table('INTEGRATION_FILES_DATA_TABLE')->insert($chunk)) {
                            $this->info("[x] $count Record Successfully inserted from sheet number $sheetKey");
                        } else {
                            $this->warn("[x] $count Record doesnt inserted from sheet number $sheetKey");
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
