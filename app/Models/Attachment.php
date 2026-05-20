<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'original_name',
        'stored_name',
        'mime_type',
        'size',
        'disk',
    ];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // --- Relationship ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // --- Accessors ---

    // Human-readable file size: "2.4 MB", "340 KB"
    protected function formattedSize(): Attribute
    {
        return Attribute::make(
            get: function () {
                $bytes = $this->size;

                if ($bytes >= 1_048_576) {
                    return round($bytes / 1_048_576, 1) . ' MB';
                }

                if ($bytes >= 1_024) {
                    return round($bytes / 1_024, 1) . ' KB';
                }

                return $bytes . ' B';
            }
        );
    }

    // File extension from original name: "pdf", "docx", "png"
    protected function extension(): Attribute
    {
        return Attribute::make(
            get: fn() => strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION))
        );
    }

    // Whether this is an image file
    protected function isImage(): Attribute
    {
        return Attribute::make(
            get: fn() => str_starts_with($this->mime_type, 'image/')
        );
    }

    // Check if the file still exists on disk
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->stored_name);
    }

    // Delete the file from storage
    public function deleteFromStorage(): void
    {
        Storage::disk($this->disk)->delete($this->stored_name);
    }
}