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

class DOCUMENTS_DATA_TABLE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:document {--index=}';

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
        if (Schema::hasTable('INTEGRATION_DOCUMENTS_DATA_TABLE') === false) {
            Schema::create('INTEGRATION_DOCUMENTS_DATA_TABLE', static function (Blueprint $table) {
                $table->integer('DOC_ID')->default(0);
                $table->bigInteger('DOC_CODE')->default(0);
                $table->integer('DOC_TYPE_ID')->default(0)->comment('When field is null, the default value is zero')->nullable();
                $table->integer('DOC_IN_TYPE_ID')->default(0);
                $table->integer('DOC_DIRECT_ID')->default(0);
                $table->integer('ORG_ID')->default(0);
                $table->integer('NO_OF_SHEETS')->default(0);
                $table->integer('EXEC_TYPE_ID')->default(0);
                $table->string('DATE_EXECUTED')->nullable();
                $table->integer('EXEC_USER_ID')->default(0);
                $table->string('DATE_CREATED')->nullable();
                $table->string('DATE_RECIEVED')->nullable();
                $table->string('DATE_RECEIVED')->nullable();
                $table->integer('RCVD_UNDER_CONTROL')->default(-1)->comment('when field is null,then default value is minus one');
                $table->integer('EXECUTION_PERIOD')->default(-1)->comment('when field is null,then default value is minus one');
                $table->text('DOC_CONTENT')->nullable();
                $table->text('NOTE')->nullable();
                $table->integer('CREATE_USER_ID')->default(0);
                $table->integer('DEP_ID')->default(0);
                $table->integer('SEND_ORG_ID')->default(0);
                $table->string('SYSTARIX')->nullable();
                $table->integer('CHR_ID')->default(0);
                $table->string('EXPIRE_DATE')->nullable();
                $table->integer('COPY_DOC_ID')->default(-1)->comment('default -1');
                $table->integer('EXEC_DEP_ID')->default(0);
                $table->integer('KIME_UNVANLANIB')->nullable();
                $table->string('LAST_UPDATED_DATE')->nullable();
            });
            $this->info('[x] Table Created');
        }
        ini_set('memory_limit', '10000000000');
        $path = '/files/DOCUMENTS_DATA_TABLE.xlsx';
        $filePath = public_path($path);
        $reader = ReaderEntityFactory::createXLSXReader($path);
        try {
            $reader->open($filePath);
            $this->info('[x] File opened');
        } catch (IOException|Exception $e) {
            $this->output->error($e->getMessage());
        }

        try {
            $data = [];
            $fieldNames = [
                'DOC_ID',
                'DOC_CODE',
                'DOC_TYPE_ID',
                'DOC_IN_TYPE_ID',
                'DOC_DIRECT_ID',
                'ORG_ID',
                'NO_OF_SHEETS',
                'EXEC_TYPE_ID',
                'DATE_EXECUTED',
                'EXEC_USER_ID',
                'DATE_CREATED',
                'DATE_RECIEVED',
                'DATE_RECEIVED',
                'RCVD_UNDER_CONTROL',
                'EXECUTION_PERIOD',
                'DOC_CONTENT',
                'NOTE',
                'CREATE_USER_ID',
                'DEP_ID',
                'SEND_ORG_ID',
                'SYSTARIX',
                'CHR_ID',
                'EXPIRE_DATE',
                'COPY_DOC_ID',
                'EXEC_DEP_ID',
                'KIME_UNVANLANIB',
                'LAST_UPDATED_DATE'
            ];
            $sheetIndex = $this->option('index');
            $this->info('[x] File starting for read');
            foreach ($reader->getSheetIterator() as $sheetKey => $sheet) {
                $this->info('[x] File starting for read sheet : ' . $sheetKey);
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
                                    $rowData[$multipleArray[1]] = $value;
                                } catch (ReflectionException|Exception $e) {
                                    $this->output->error($e->getMessage());
                                }
                            }
                            $data[] = $rowData;
                            unset($rowData, $cells);
                        }
                        if ((int)$key % 1000 === 0) {
                            $this->info('Key Number = ' . $key);
                            try {
                                $count = 0;
                                foreach ($this->chunk($data, 77) as $chunk) {
                                    $count += count($chunk);
                                    DB::transaction(function () use ($chunk, $count, $sheetKey) {
                                        if (DB::table('INTEGRATION_DOCUMENTS_DATA_TABLE')->insert($chunk)) {
                                            $this->info("[x] $count Record Successfully inserted to sheet number $sheetKey");
                                        } else {
                                            $this->warn("[x] Record doesn't inserted to sheet number $sheetKey");
                                        }

                                    }, 6);
                                }
                                unset($data);
                            } catch (Throwable|Exception $e) {
                                $this->output->error($e->getMessage());
                            }
                        }


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

    public
    function checkObject($obj)
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

    public
    function chunk($data, $chunkSize): \Generator
    {
        for ($i = 0, $j = count($data); $i < $j; $i += $chunkSize) {
            yield array_slice($data, $i, $chunkSize);
        }
    }
}
