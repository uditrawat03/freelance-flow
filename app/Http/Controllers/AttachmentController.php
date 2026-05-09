<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(Attachment $attachment)
    {
        // Authorise: only allow access to attachments on projects
        // the current user can see (we add proper policies in Phase 2)
        abort_if(! auth()->check(), 403);

        // Verify the file still exists on disk
        abort_unless($attachment->exists(), 404);

        // Stream the file to the browser with the original filename
        return Storage::disk($attachment->disk)
            ->download($attachment->stored_name, $attachment->original_name);
    }
}