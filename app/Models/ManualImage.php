<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualImage extends Model
{
    protected $table = 'manual_images';

    protected $fillable = [
        'manual_id',
        'archivo_path',
        'archivo_hash',
        'mime',
        'size',
        'subido_por',
    ];

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class, 'manual_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}