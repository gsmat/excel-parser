<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ESD_DOC extends Model
{
    use HasFactory;

    protected $table = 'esd_doc';

    public $timestamps = false;

    protected $fillable = ["chr_id", "code_received", "copy_doc_id", "create_user_id", "data_executed", "date_created", "date_received", "dep_id", "doc_code", "doc_content", "doc_direct_id", "doc_id", "doc_in_type_id", "doc_type_id", "exec_dep_id", "exec_type_id", "exec_user_id", "execution_period", "expire_date", "from_address", "last_updated_date", "no_of_sheets", "note", "org_id", "rcvd_under_control", "send_org_id", "sys_date"];
}
