<?php

namespace App\Models\FileStorage;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFileSystemBinding extends Model
{
    protected $table = 'file_systemBinding';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idUser',
        'bindingKey',
        'idFilePossession',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function possession(): BelongsTo
    {
        return $this->belongsTo(StoredFilePossession::class, 'idFilePossession');
    }
}
