# Day 19 — File Uploads with Laravel Storage

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 16 min · **Level:** Intermediate

---

> *"Clients share briefs. They send contracts. They attach reference images and brand assets. A project management tool that cannot store files is half a product. Today we add file attachments to FreelanceFlow projects — uploaded from a Livewire form, stored on disk, and retrievable with a secure URL."*

---

## Where We Are

FreelanceFlow has clients, projects, and tags. Projects have budgets, deadlines, and statuses. What they do not have yet is the ability to store files. Today we add that — a proper file upload system using Laravel's Storage facade, Livewire's `WithFileUploads` trait, and a dedicated `attachments` table to track uploaded files.

---

## What We Are Building Today

1. An **attachments table** — stores metadata for uploaded files
2. The **Attachment model** with a `belongsTo` relationship to Project
3. **Storage disk configuration** — local for development
4. **File upload on the Project Edit form** using Livewire `WithFileUploads`
5. **Secure file download** — files served through a controller, not directly from disk
6. **File display** on the project detail page
7. **File deletion** with storage cleanup

---

## Step 1 — Create the Attachments Table

Files need metadata — original name, stored path, mime type, size — separate from the file itself. The file lives on disk. The metadata lives in the database.

```bash
php artisan make:migration create_attachments_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('original_name');   // "Project Brief.pdf"
            $table->string('stored_name');     // "attachments/abc123.pdf"
            $table->string('mime_type');       // "application/pdf"
            $table->unsignedBigInteger('size'); // bytes
            $table->string('disk')->default('local'); // local, s3, etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
```

```bash
php artisan migrate
```

---

## Step 2 — The Attachment Model

```bash
php artisan make:model Attachment
```

```php
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
        'size'       => 'integer',
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
            get: fn () => strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION))
        );
    }

    // Whether this is an image file
    protected function isImage(): Attribute
    {
        return Attribute::make(
            get: fn () => str_starts_with($this->mime_type, 'image/')
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
```

---

## Step 3 — Add the Relationship to the Project Model

Open `app/Models/Project.php` and add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function attachments(): HasMany
{
    return $this->hasMany(Attachment::class);
}
```

---

## Step 4 — Configure Storage

Open `config/filesystems.php`. The `local` disk is already configured — it stores files in `storage/app/`. We need to make sure the `private` disk is set up for files that should not be publicly accessible:

```php
// config/filesystems.php
'disks' => [

    'local' => [
        'driver' => 'local',
        'root'   => storage_path('app/private'),
        'throw'  => false,
    ],

    'public' => [
        'driver'     => 'local',
        'root'       => storage_path('app/public'),
        'url'        => env('APP_URL') . '/storage',
        'visibility' => 'public',
        'throw'      => false,
    ],

    // ... s3 config
],
```

**Important:** Project attachments go on the `local` disk — not `public`. Files in `storage/app/private` are not accessible via a URL. They can only be served through your application code. This means a client cannot guess the filename and download a competitor's contract by changing a URL.

---

## Step 5 — Update the Project Edit Livewire Component

Open `app/Livewire/Projects/Edit.php` and add file upload support:

```php
<?php

namespace App\Livewire\Projects;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Edit Project — FreelanceFlow')]
class Edit extends Component
{
    use WithFileUploads;

    public Project $project;

    #[Rule('required|exists:clients,id')]
    public ?int $selectedClientId = null;

    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('nullable|string')]
    public string $description = '';

    #[Rule('required|in:draft,active,on_hold,completed,cancelled')]
    public string $status = 'draft';

    #[Rule('nullable|numeric|min:0')]
    public ?string $budget = null;

    #[Rule('nullable|date')]
    public ?string $deadline = null;

    #[Rule('nullable|array')]
    public array $selectedTags = [];

    // File upload property — can be a single file or array for multiple
    #[Rule('nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip')]
    public $newFile = null;

    public bool $confirmingDelete  = false;
    public ?int $deletingAttachmentId = null;

    public function mount(Project $project): void
    {
        $this->project           = $project;
        $this->selectedClientId  = $project->client_id;
        $this->name              = $project->name;
        $this->description       = $project->description ?? '';
        $this->status            = $project->status;
        $this->budget            = $project->budget;
        $this->deadline          = $project->deadline?->format('Y-m-d');
        $this->selectedTags      = $project->tags->pluck('id')->toArray();
    }

    public function updatedNewFile(): void
    {
        $this->validateOnly('newFile');
    }

    public function uploadFile(): void
    {
        $this->validateOnly('newFile');

        if (! $this->newFile) {
            return;
        }

        $originalName = $this->newFile->getClientOriginalName();
        $mimeType     = $this->newFile->getMimeType();
        $size         = $this->newFile->getSize();

        // Store the file in storage/app/private/attachments/
        $storedName = $this->newFile->store('attachments', 'local');

        // Save metadata to the database
        $this->project->attachments()->create([
            'original_name' => $originalName,
            'stored_name'   => $storedName,
            'mime_type'     => $mimeType,
            'size'          => $size,
            'disk'          => 'local',
        ]);

        // Reset the file input
        $this->newFile = null;
        $this->reset('newFile');

        // Refresh the project to show the new attachment
        $this->project->refresh();

        session()->flash('success', 'File uploaded successfully.');
    }

    public function confirmDeleteAttachment(int $attachmentId): void
    {
        $this->deletingAttachmentId = $attachmentId;
        $this->confirmingDelete     = true;
    }

    public function deleteAttachment(): void
    {
        $attachment = Attachment::findOrFail($this->deletingAttachmentId);

        // Ensure this attachment belongs to this project
        abort_if($attachment->project_id !== $this->project->id, 403);

        // Delete file from disk first
        $attachment->deleteFromStorage();

        // Then delete the database record
        $attachment->delete();

        $this->confirmingDelete       = false;
        $this->deletingAttachmentId   = null;
        $this->project->refresh();

        session()->flash('success', 'File removed.');
    }

    public function update(): void
    {
        $this->validate([
            'email' => "required|email|max:255",
        ]);

        $this->project->update([
            'client_id'   => $this->selectedClientId,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'budget'      => $this->budget ?: null,
            'deadline'    => $this->deadline ?: null,
        ]);

        $this->project->tags()->sync($this->selectedTags);

        session()->flash('success', 'Project updated successfully.');

        $this->redirect(
            route('clients.show', $this->project->client_id),
            navigate: true
        );
    }

    public function render()
    {
        return view('livewire.projects.edit', [
            'clients'     => Client::active()->orderBy('name')->get(),
            'tags'        => Tag::orderBy('name')->get(),
            'attachments' => $this->project->attachments()->latest()->get(),
        ]);
    }
}
```

**What to notice:**

- `use WithFileUploads` — the Livewire trait that enables file upload handling. One line enables all file upload functionality
- `$this->newFile->store('attachments', 'local')` — stores the file in `storage/app/private/attachments/` with a random filename. Returns the stored path like `attachments/AbCd1234.pdf`
- `getClientOriginalName()` — the original filename the user uploaded. Stored separately so we can display it
- `abort_if($attachment->project_id !== $this->project->id, 403)` — authorisation check. Users should only delete attachments that belong to the current project
- `$this->project->refresh()` — reloads the project and its relationships from the database after changes

---

## Step 6 — Add the File Upload UI to the Edit View

Add a file upload section to `resources/views/livewire/projects/edit.blade.php`:

```blade
{{-- Attachments section --}}
<div class="border-t border-gray-100 pt-5">
    <h3 class="text-sm font-medium text-gray-900 mb-3">Attachments</h3>

    {{-- Existing files --}}
    @if ($attachments->isNotEmpty())
        <div class="space-y-2 mb-4">
            @foreach ($attachments as $attachment)
                <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex items-center gap-3 min-w-0">
                        {{-- File type icon --}}
                        <div class="w-8 h-8 rounded flex items-center justify-center flex-shrink-0
                            {{ match($attachment->extension) {
                                'pdf'                 => 'bg-red-100 text-red-600',
                                'doc', 'docx'         => 'bg-blue-100 text-blue-600',
                                'xls', 'xlsx'         => 'bg-green-100 text-green-600',
                                'png', 'jpg', 'jpeg', 'gif' => 'bg-purple-100 text-purple-600',
                                'zip'                 => 'bg-yellow-100 text-yellow-600',
                                default               => 'bg-gray-100 text-gray-600',
                            } }}">
                            <span class="text-xs font-bold uppercase">{{ $attachment->extension }}</span>
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                {{ $attachment->original_name }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $attachment->formatted_size }}
                                · {{ $attachment->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                        {{-- Download link --}}
                        <a
                            href="{{ route('attachments.download', $attachment) }}"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                        >
                            Download
                        </a>

                        {{-- Delete --}}
                        <button
                            wire:click="confirmDeleteAttachment({{ $attachment->id }})"
                            class="text-xs text-red-500 hover:text-red-700"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Upload new file --}}
    <flux:field>
        <flux:label>Upload file</flux:label>
        <input
            type="file"
            wire:model="newFile"
            class="block w-full text-sm text-gray-500
                file:mr-4 file:py-2 file:px-4
                file:rounded-md file:border-0
                file:text-sm file:font-medium
                file:bg-indigo-50 file:text-indigo-700
                hover:file:bg-indigo-100 cursor-pointer"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.zip"
        />
        <flux:error name="newFile" />

        {{-- Preview the selected file before uploading --}}
        @if ($newFile)
            <div class="mt-2 flex items-center gap-3">
                <p class="text-xs text-gray-600">
                    Ready to upload: <span class="font-medium">{{ $newFile->getClientOriginalName() }}</span>
                    ({{ round($newFile->getSize() / 1024, 1) }} KB)
                </p>
                <flux:button
                    wire:click="uploadFile"
                    wire:loading.attr="disabled"
                    size="sm"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="uploadFile">Upload</span>
                    <span wire:loading wire:target="uploadFile">Uploading...</span>
                </flux:button>
                <button
                    wire:click="$set('newFile', null)"
                    class="text-xs text-gray-400 hover:text-gray-600"
                >
                    Cancel
                </button>
            </div>
        @endif
    </flux:field>
</div>

{{-- Delete attachment modal --}}
<flux:modal wire:model="confirmingDelete" class="max-w-sm">
    <div class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-900">Remove file?</h3>
        <p class="text-sm text-gray-500">
            This file will be permanently deleted from FreelanceFlow.
            This cannot be undone.
        </p>
        <div class="flex items-center gap-3">
            <flux:button
                wire:click="deleteAttachment"
                wire:loading.attr="disabled"
                variant="danger"
                class="flex-1"
            >
                Yes, remove
            </flux:button>
            <flux:button
                wire:click="$set('confirmingDelete', false)"
                variant="ghost"
                class="flex-1"
            >
                Cancel
            </flux:button>
        </div>
    </div>
</flux:modal>
```

The file preview section — showing the name and size before uploading — is a small UX detail that makes the upload feel intentional rather than automatic.

---

## Step 7 — Secure File Download Controller

Files are stored on the private local disk — they cannot be accessed via a URL. We need a controller method that authorises the request and then streams the file to the browser.

Add the route first:

```php
// routes/web.php (inside the auth middleware group)
Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
     ->name('attachments.download');
```

Create the controller:

```bash
php artisan make:controller AttachmentController
```

```php
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
```

`Storage::disk('local')->download($path, $filename)` streams the file directly from disk to the browser with the correct `Content-Disposition` header. The browser receives the file with the original filename the user uploaded — not the random stored name.

---

## Step 8 — Allowed File Types and Size Limits

Update the validation rule on `$newFile` to match what your app actually accepts. The current rule:

```php
#[Rule('nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip')]
public $newFile = null;
```

- `max:10240` — maximum 10 MB (in kilobytes)
- `mimes:pdf,doc,...` — allowed file extensions

For a freelance tool, reasonable limits are:
- Documents: pdf, doc, docx, xls, xlsx, ppt, pptx
- Images: png, jpg, jpeg, gif, webp, svg
- Archives: zip, rar
- Maximum size: 20 MB (adjust based on your storage plan)

---

## Step 9 — Configuring Livewire Temporary Upload Directory

Livewire stores files in a temporary directory while they are being processed. Make sure the directory is writable and configure the cleanup schedule.

Add to `config/livewire.php`:

```php
'temporary_file_upload' => [
    'disk'        => 'local',
    'rules'       => ['required', 'file', 'max:10240'],
    'directory'   => 'livewire-tmp',
    'middleware'  => 'throttle:60,1',
    'preview_mimes' => [
        'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
        'mov', 'avi', 'wmv', 'mp3', 'm4a',
        'jpg', 'jpeg', 'mpga', 'webp', 'wma',
    ],
    'max_upload_time' => 5,
],
```

Schedule automatic cleanup of orphaned temporary files — add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('livewire:clean-uploads')->daily();
```

---

## Storage Reference

```php
// Store a file — returns the stored path
$path = Storage::disk('local')->put('attachments', $file);
$path = $file->store('attachments', 'local');  // same result

// Check if a file exists
Storage::disk('local')->exists($path);  // true or false

// Get file contents
$contents = Storage::disk('local')->get($path);

// Delete a file
Storage::disk('local')->delete($path);

// Download response — streams to browser
return Storage::disk('local')->download($path, 'My File.pdf');

// Response — inline view (e.g., images in the browser)
return Storage::disk('local')->response($path);

// Get file size in bytes
Storage::disk('local')->size($path);

// Get last modified timestamp
Storage::disk('local')->lastModified($path);

// List files in a directory
Storage::disk('local')->files('attachments');

// Public disk — files accessible via URL
$path = Storage::disk('public')->put('avatars', $file);
$url  = Storage::disk('public')->url($path);  // https://app.com/storage/avatars/...
```

---

## What We Learned Today

- **Private vs public disks** — `local` disk files are not accessible via URL. `public` disk files are. Sensitive files always go on `local`
- **`WithFileUploads` trait** — adds file upload handling to any Livewire component. One line enables it all
- **`$file->store('directory', 'disk')`** — stores the file with a random secure filename. Returns the stored path
- **`getClientOriginalName()`** — the original filename from the user's machine. Store this separately for display
- **`Storage::disk()->download($path, $name)`** — streams a file to the browser with a custom filename. The secure download pattern
- **`abort_if($attachment->project_id !== $this->project->id, 403)`** — always authorise file access. Never trust the ID in the URL alone
- **`$this->project->refresh()`** — reloads the model and its relationships after changes in the same request
- **Temporary file cleanup** — `php artisan livewire:clean-uploads` removes orphaned temp files. Schedule it daily

---

## Day 20 — Sending Emails with Laravel Mail

Tomorrow FreelanceFlow sends its first email. When a new project is created, the client receives a notification. We will build a Mailable class, design an email template using Laravel's markdown mail, test it locally with Mailpit, and queue it so the form submission stays fast even when the mail server is slow.

See you on Day 20.