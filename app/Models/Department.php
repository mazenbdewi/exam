<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    protected $primaryKey = 'department_id';

    protected $fillable = ['department_name'];

    const CREATED_AT = 'department_created_at';

    const UPDATED_AT = 'department_updated_at';
}
