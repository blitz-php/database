<@php

namespace {namespace};

use BlitzPHP\Database\Model;

class {class} extends Model
{
<?php if (is_string($dbGroup)): ?>
    protected ?string $group         = '{dbGroup}';
<?php endif; ?>
    protected string $table          = '{table}';
    protected string $primaryKey     = 'id';
    protected bool $useAutoIncrement = true;
    protected string $returnType     = {return};
    protected bool $useSoftDeletes   = false;
    protected array $fillable        = [];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected bool $useTimestamps = false;
    protected string $dateFormat    = 'datetime';
    protected string $createdField  = 'created_at';
    protected string $updatedField  = 'updated_at';
    protected string $deletedField  = 'deleted_at';

    // Validation
    protected array $rules = [];
    protected array $messages = [];
    
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected bool $allowCallbacks = true;
    protected array $beforeInsert  = [];
    protected array $afterInsert   = [];
    protected array $beforeUpdate  = [];
    protected array $afterUpdate   = [];
    protected array $beforeDelete  = [];
    protected array $afterDelete   = [];
    protected array $beforeFind    = [];
    protected array $afterFind     = [];
}
