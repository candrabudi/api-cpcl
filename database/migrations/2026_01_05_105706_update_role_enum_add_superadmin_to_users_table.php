<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateRoleEnumAddSuperadminToUsersTable extends Migration
{
    public function up()
    {
        DB::statement("
            ALTER TABLE users 
            MODIFY role ENUM('superadmin','admin','vendor') 
            NOT NULL
        ");
    }

    public function down()
    {
        DB::statement("
            ALTER TABLE users 
            MODIFY role ENUM('admin','vendor') 
            NOT NULL
        ");
    }
}
