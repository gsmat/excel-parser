<?php

namespace App\Console\Commands;

use App\Models\ESD_DOC;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
            $dbNameKeys = [
                "doc_id",
                "doc_code",
                "doc_type_id",
                "doc_in_type_id",
                "doc_direct_id",
                "org_id",
                "no_of_sheets",
                "exec_type_id",
                "data_executed",
                "exec_user_id",
                "date_created",
                "date_received",
                "code_received",
                "rcvd_under_control",
                "execution_period",
                "doc_content",
                "note",
                "create_user_id",
                "dep_id",
                "send_org_id",
                "sys_date",
                "chr_id",
                "expire_date",
                "copy_doc_id",
                "exec_dep_id",
                "from_address",
                "last_updated_date",
            ];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $key => $row) {

                    if ($key === 1) {
                        $fieldNames = $row->getCells();
                        dd($fileNames);
                        $this->checkTable($fileName, $fieldNames);
                    } else {
                        $cells = $row->getCells();
                        $data = [];
                        $multipleArrays = new \MultipleIterator();
                        $multipleArrays->attachIterator(new \ArrayIterator($cells));
                        $multipleArrays->attachIterator(new \ArrayIterator($dbNameKeys));
                        foreach ($multipleArrays as $multipleArray) {
                            try {
                                $value = $this->checkObject($multipleArray[0]->getValue());
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
                            ESD_DOC::query()->create($data);
                            $this->info("[x] $key  Record Successfully inserted");
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

    public function checkTable(string $table, array $fields): bool
    {
        if (!Schema::hasTable(Str::lower($table))) {
            try {
                Schema::create($table, static function (Blueprint $table) use ($fields) {
                    foreach ($fields as $field) {
                        if ($field->getType() === 1) {
                            $table->integer($field->getValue());
                        }
                        if ($field->getType() === 0) {
                            $table->string($field->getValue());
                        }
                        if ($field->getType() === 5) {
                            $table->date($field->getValue());
                        }
                    }
                });
            } catch (\Exception $exception) {
                dd($exception->getMessage());
            }
        }
        return true;

    }

}
