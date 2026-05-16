# Day 30 — Roles & Permissions + Invoice UI (The Missing Pieces)

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 18 min · **Level:** Intermediate

---

> *"Two things on today's agenda. First — we close the gap from Day 26. The InvoiceService exists, the PDF template exists, but there is no UI to create an invoice, no button to generate the PDF, and no Livewire component wiring it all together. We fix that completely. Then we add a proper roles and permissions system using Spatie so FreelanceFlow is ready for multi-user teams."*

---

## Part A — The Missing Invoice UI (Day 26 Completion)

On Day 26 we built the invoice infrastructure — migration, model, InvoiceService, PDF template, and download controller. What we did not build was the user-facing side: the form to create an invoice, the list to view invoices, and the action buttons to generate and manage PDFs. Today we complete that.

---

### A1 — Create Invoice Livewire Component

```bash
php artisan make:livewire Invoices/Create
```

Open `app/Livewire/Invoices/Create.php`:

```php
<?php

namespace App\Livewire\Invoices;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Invoice — FreelanceFlow')]
class Create extends Component
{
    #[Rule('required|exists:clients,id')]
    public ?int $client_id = null;

    #[Rule('nullable|exists:projects,id')]
    public ?int $project_id = null;

    #[Rule('nullable|string')]
    public string $notes = '';

    #[Rule('required|numeric|min:0|max:100')]
    public float $tax_rate = 18.0;

    #[Rule('nullable|date')]
    public ?string $issued_at = null;

    #[Rule('nullable|date|after_or_equal:issued_at')]
    public ?string $due_at = null;

    // Line items — each row: description, quantity, rate
    public array $lineItems = [
        ['description' => '', 'quantity' => 1, 'rate' => 0],
    ];

    public function addLineItem(): void
    {
        $this->lineItems[] = ['description' => '', 'quantity' => 1, 'rate' => 0];
    }

    public function removeLineItem(int $index): void
    {
        if (count($this->lineItems) > 1) {
            array_splice($this->lineItems, $index, 1);
        }
    }

    public function getSubtotalProperty(): float
    {
        return collect($this->lineItems)
            ->sum(fn ($item) => ($item['quantity'] ?? 0) * ($item['rate'] ?? 0));
    }

    public function getTaxAmountProperty(): float
    {
        return $this->subtotal * ($this->tax_rate / 100);
    }

    public function getTotalProperty(): float
    {
        return $this->subtotal + $this->tax_amount;
    }

    // Projects filtered by selected client
    public function getProjectsProperty()
    {
        return $this->client_id
            ? Project::where('client_id', $this->client_id)->get()
            : collect();
    }

    public function save(InvoiceService $invoiceService): void
    {
        $this->validate();

        // Validate line items manually
        foreach ($this->lineItems as $index => $item) {
            if (empty($item['description'])) {
                $this->addError("lineItems.{$index}.description", 'Description is required.');
                return;
            }
            if (($item['quantity'] ?? 0) <= 0) {
                $this->addError("lineItems.{$index}.quantity", 'Quantity must be greater than 0.');
                return;
            }
            if (($item['rate'] ?? 0) < 0) {
                $this->addError("lineItems.{$index}.rate", 'Rate cannot be negative.');
                return;
            }
        }

        $invoice = $invoiceService->create([
            'client_id'  => $this->client_id,
            'project_id' => $this->project_id ?: null,
            'notes'      => $this->notes,
            'tax_rate'   => $this->tax_rate,
            'issued_at'  => $this->issued_at ?: now()->toDateString(),
            'due_at'     => $this->due_at,
            'line_items' => $this->lineItems,
            'status'     => 'draft',
        ]);

        session()->flash('success', "Invoice {$invoice->number} created.");

        $this->redirect(route('invoices.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.invoices.create', [
            'clients' => Client::active()->orderBy('name')->get(),
        ]);
    }
}
```

Create `resources/views/livewire/invoices/create.blade.php`:

```blade
<div>
    <x-page-header title="New Invoice" subtitle="Create a professional invoice for a client.">
        <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back</a>
    </x-page-header>

    <x-form-card max-width="max-w-3xl">

        {{-- Client & Project --}}
        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Client <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model.live="client_id">
                    <option value="">Select client...</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->display_name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="client_id" />
            </flux:field>

            <flux:field>
                <flux:label>Project <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
                <flux:select wire:model="project_id" :disabled="! $client_id">
                    <option value="">No project</option>
                    @foreach ($this->projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="project_id" />
            </flux:field>
        </div>

        {{-- Dates --}}
        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Invoice date</flux:label>
                <flux:input wire:model="issued_at" type="date" />
                <flux:error name="issued_at" />
            </flux:field>
            <flux:field>
                <flux:label>Due date <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
                <flux:input wire:model="due_at" type="date" />
                <flux:error name="due_at" />
            </flux:field>
        </div>

        {{-- Line items --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-gray-700">Line items</label>
            </div>

            {{-- Header row --}}
            <div class="grid grid-cols-12 gap-2 mb-1 px-1">
                <div class="col-span-6 text-xs text-gray-400 font-medium">Description</div>
                <div class="col-span-2 text-xs text-gray-400 font-medium text-right">Qty</div>
                <div class="col-span-3 text-xs text-gray-400 font-medium text-right">Rate (₹)</div>
                <div class="col-span-1"></div>
            </div>

            @foreach ($lineItems as $index => $item)
                <div class="grid grid-cols-12 gap-2 mb-2 items-center" wire:key="line-{{ $index }}">
                    <div class="col-span-6">
                        <flux:input
                            wire:model="lineItems.{{ $index }}.description"
                            type="text"
                            placeholder="e.g. Website Design"
                        />
                        @error("lineItems.{$index}.description")
                            <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="col-span-2">
                        <flux:input
                            wire:model.live="lineItems.{{ $index }}.quantity"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </div>
                    <div class="col-span-3">
                        <flux:input
                            wire:model.live="lineItems.{{ $index }}.rate"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="col-span-1 flex justify-center">
                        @if (count($lineItems) > 1)
                            <button
                                wire:click="removeLineItem({{ $index }})"
                                class="text-gray-300 hover:text-red-400 transition-colors"
                                type="button"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach

            <button
                wire:click="addLineItem"
                type="button"
                class="mt-1 text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1"
            >
                + Add line item
            </button>
        </div>

        {{-- Totals --}}
        <div class="border-t border-gray-100 pt-4">
            <div class="ml-auto w-56 space-y-2">
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span>₹{{ number_format($this->subtotal, 2) }}</span>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm text-gray-600">GST</span>
                        <div class="w-16">
                            <flux:input wire:model.live="tax_rate" type="number" min="0" max="100" step="0.5" />
                        </div>
                        <span class="text-sm text-gray-400">%</span>
                    </div>
                    <span class="text-sm text-gray-600">₹{{ number_format($this->tax_amount, 2) }}</span>
                </div>

                <div class="flex justify-between text-base font-bold text-gray-900 border-t border-gray-200 pt-2">
                    <span>Total</span>
                    <span>₹{{ number_format($this->total, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Notes --}}
        <flux:field>
            <flux:label>Notes <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
            <flux:textarea wire:model="notes" placeholder="Payment terms, bank details, thank you message..." rows="2" />
        </flux:field>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-2">
            <flux:button wire:click="save" wire:loading.attr="disabled" variant="primary">
                <span wire:loading.remove wire:target="save">Create invoice</span>
                <span wire:loading wire:target="save">Creating...</span>
            </flux:button>
            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>

    </x-form-card>
</div>
```

---

### A2 — Invoice List Livewire Component

```bash
php artisan make:livewire Invoices/InvoiceList
```

Open `app/Livewire/Invoices/InvoiceList.php`:

```php
<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Invoices — FreelanceFlow')]
class InvoiceList extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $status = '';

    public bool $confirmingDelete        = false;
    public ?int  $deletingInvoiceId      = null;
    public bool  $confirmingGenerate     = false;
    public ?int  $generatingInvoiceId    = null;

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    // --- Generate PDF action ---
    public function confirmGenerate(int $invoiceId): void
    {
        $this->generatingInvoiceId = $invoiceId;
        $this->confirmingGenerate  = true;
    }

    public function generatePdf(InvoiceService $invoiceService): void
    {
        $invoice = Invoice::findOrFail($this->generatingInvoiceId);

        $invoiceService->generatePdf($invoice);

        $this->confirmingGenerate  = false;
        $this->generatingInvoiceId = null;

        session()->flash('success', "PDF generated for invoice {$invoice->number}.");
    }

    // --- Mark as sent ---
    public function markSent(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->markAsSent();

        session()->flash('success', "Invoice {$invoice->number} marked as sent.");
    }

    // --- Mark as paid ---
    public function markPaid(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->markAsPaid();

        session()->flash('success', "Invoice {$invoice->number} marked as paid.");
    }

    // --- Delete ---
    public function confirmDelete(int $invoiceId): void
    {
        $this->deletingInvoiceId = $invoiceId;
        $this->confirmingDelete  = true;
    }

    public function delete(): void
    {
        $invoice = Invoice::findOrFail($this->deletingInvoiceId);
        $invoice->delete();

        $this->confirmingDelete  = false;
        $this->deletingInvoiceId = null;

        session()->flash('success', 'Invoice deleted.');
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with('client')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(15);

        return view('livewire.invoices.invoice-list', compact('invoices'));
    }
}
```

Create `resources/views/livewire/invoices/invoice-list.blade.php`:

```blade
<div>
    <x-page-header title="Invoices" subtitle="Manage your invoices and track payments.">
        <a href="{{ route('invoices.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors">
            + New invoice
        </a>
    </x-page-header>

    {{-- Status filter pills --}}
    <div class="flex items-center gap-2 mb-5">
        @foreach (['' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
            <button
                wire:click="$set('status', '{{ $value }}')"
                class="text-sm px-3 py-1.5 rounded-full border transition-colors
                    {{ $status === $value
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'text-gray-600 border-gray-200 hover:border-indigo-300 bg-white' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Invoice rows --}}
    @forelse ($invoices as $invoice)
        <div class="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-2">
            <div class="flex items-start justify-between">

                {{-- Left: invoice info --}}
                <div>
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-gray-900 text-sm">{{ $invoice->number }}</span>
                        {{-- Status badge --}}
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full
                            {{ match($invoice->status) {
                                'paid'    => 'bg-green-100 text-green-700',
                                'sent'    => 'bg-blue-100 text-blue-700',
                                'overdue' => 'bg-red-100 text-red-700',
                                'draft'   => 'bg-gray-100 text-gray-600',
                                default   => 'bg-gray-100 text-gray-600',
                            } }}">
                            {{ $invoice->status_label }}
                        </span>
                        @if ($invoice->is_overdue)
                            <span class="text-xs text-red-500 font-medium">
                                Due {{ $invoice->due_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $invoice->client->name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $invoice->issued_at ? $invoice->issued_at->format('M d, Y') : 'No date' }}
                        @if ($invoice->due_at)
                            · Due {{ $invoice->due_at->format('M d, Y') }}
                        @endif
                    </p>
                </div>

                {{-- Right: total + actions --}}
                <div class="flex items-center gap-4">
                    <span class="text-base font-bold text-gray-900">{{ $invoice->formatted_total }}</span>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2">

                        {{-- Generate PDF --}}
                        @if (! $invoice->has_pdf)
                            <button
                                wire:click="confirmGenerate({{ $invoice->id }})"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium border border-indigo-200 px-2.5 py-1 rounded-md hover:bg-indigo-50 transition-colors"
                            >
                                Generate PDF
                            </button>
                        @else
                            <a
                                href="{{ route('invoices.download', $invoice) }}"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                            >
                                Download PDF
                            </a>
                            <a
                                href="{{ route('invoices.preview', $invoice) }}"
                                target="_blank"
                                class="text-xs text-gray-500 hover:text-gray-700"
                            >
                                Preview
                            </a>
                        @endif

                        {{-- Mark as sent --}}
                        @if ($invoice->status === 'draft')
                            <button
                                wire:click="markSent({{ $invoice->id }})"
                                wire:confirm="Mark invoice {{ $invoice->number }} as sent?"
                                class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                            >
                                Mark sent
                            </button>
                        @endif

                        {{-- Mark as paid --}}
                        @if (in_array($invoice->status, ['sent', 'overdue']))
                            <button
                                wire:click="markPaid({{ $invoice->id }})"
                                wire:confirm="Mark invoice {{ $invoice->number }} as paid?"
                                class="text-xs text-green-600 hover:text-green-800 font-medium"
                            >
                                Mark paid
                            </button>
                            <a
                                href="{{ route('invoices.pay', $invoice) }}"
                                target="_blank"
                                class="text-xs text-purple-600 hover:text-purple-800 font-medium"
                            >
                                Pay link
                            </a>
                        @endif

                        {{-- Delete --}}
                        <button
                            wire:click="confirmDelete({{ $invoice->id }})"
                            class="text-xs text-red-400 hover:text-red-600"
                        >
                            Delete
                        </button>

                    </div>
                </div>

            </div>
        </div>
    @empty
        <x-empty-state
            message="No invoices yet."
            cta-text="Create your first invoice"
            :cta-href="route('invoices.create')"
        />
    @endforelse

    {{-- Pagination --}}
    @if ($invoices->hasPages())
        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif

    {{-- Generate PDF confirmation modal --}}
    <flux:modal wire:model="confirmingGenerate" class="max-w-sm">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-900">Generate PDF?</h3>
            <p class="text-sm text-gray-500">
                This will create the invoice PDF and store it on the server.
                You can download or send it to the client afterwards.
            </p>
            <div class="flex items-center gap-3">
                <flux:button wire:click="generatePdf" wire:loading.attr="disabled" variant="primary" class="flex-1">
                    <span wire:loading.remove wire:target="generatePdf">Generate</span>
                    <span wire:loading wire:target="generatePdf">Generating...</span>
                </flux:button>
                <flux:button wire:click="$set('confirmingGenerate', false)" variant="ghost" class="flex-1">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model="confirmingDelete" class="max-w-sm">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-900">Delete invoice?</h3>
            <p class="text-sm text-gray-500">This action cannot be undone.</p>
            <div class="flex items-center gap-3">
                <flux:button wire:click="delete" wire:loading.attr="disabled" variant="danger" class="flex-1">Yes, delete</flux:button>
                <flux:button wire:click="$set('confirmingDelete', false)" variant="ghost" class="flex-1">Cancel</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
```

---

### A3 — Add Invoice Routes

```php
// routes/web.php (inside auth middleware group)
use App\Livewire\Invoices\Create as CreateInvoice;
use App\Livewire\Invoices\InvoiceList;
use App\Http\Controllers\InvoiceController;

Route::get('/invoices',               InvoiceList::class)->name('invoices.index');
Route::get('/invoices/create',        CreateInvoice::class)->name('invoices.create');
Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
Route::get('/invoices/{invoice}/preview',  [InvoiceController::class, 'preview'])->name('invoices.preview');
Route::post('/invoices/{invoice}/send',    [InvoiceController::class, 'send'])->name('invoices.send');
Route::post('/invoices/{invoice}/paid',    [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');

// Payment routes (public — clients do not need to log in to pay)
Route::get('/invoices/{invoice}/pay',         [\App\Http\Controllers\PaymentController::class, 'show'])->name('invoices.pay');
Route::get('/invoices/{invoice}/pay/success', [\App\Http\Controllers\PaymentController::class, 'success'])->name('invoices.pay.success');
```

---

### A4 — Add Invoices Link to Sidebar

Open `resources/views/partials/sidebar.blade.php` and add the invoices link:

```blade
<li class="{{ request()->routeIs('invoices.*') ? 'active' : '' }}">
    <a href="{{ route('invoices.index') }}">Invoices</a>
</li>
```

---

## Part B — Roles & Permissions with Spatie

### B1 — Install Spatie Permission

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Add the `HasRoles` trait to the User model:

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Billable, HasRoles;
}
```

---

### B2 — Define Roles and Permissions

Create a seeder for roles:

```bash
php artisan make:seeder RoleAndPermissionSeeder
```

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $permissions = [
            // Clients
            'view clients', 'create clients', 'edit clients', 'delete clients',
            // Projects
            'view projects', 'create projects', 'edit projects', 'delete projects',
            // Invoices
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'send invoices',
            // Reports
            'view reports',
            // Settings
            'manage settings', 'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Admin — can do everything
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // Manager — can do most things except manage users and settings
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'view clients', 'create clients', 'edit clients',
            'view projects', 'create projects', 'edit projects',
            'view invoices', 'create invoices', 'edit invoices', 'send invoices',
            'view reports',
        ]);

        // Freelancer — view and create only, no delete or send
        $freelancer = Role::firstOrCreate(['name' => 'freelancer']);
        $freelancer->syncPermissions([
            'view clients', 'create clients',
            'view projects', 'create projects',
            'view invoices', 'create invoices',
        ]);

        $this->command->info('✓ Roles and permissions seeded');
    }
}
```

Add to `DatabaseSeeder`:

```php
$this->call([
    RoleAndPermissionSeeder::class,
    ClientSeeder::class,
    TagSeeder::class,
]);

// Assign admin role to demo user
$user->assignRole('admin');
```

Run it:

```bash
php artisan db:seed --class=RoleAndPermissionSeeder
```

---

### B3 — Check Permissions in Controllers and Livewire

```php
// In a controller
public function destroy(Invoice $invoice)
{
    abort_unless(auth()->user()->can('delete invoices'), 403);
    $invoice->delete();
}

// In a Livewire component
public function delete(): void
{
    abort_unless(auth()->user()->can('delete invoices'), 403);
    $invoice->delete();
}
```

---

### B4 — @can in Blade with Spatie

Spatie integrates with Laravel's native `@can` directive — nothing changes in the view syntax:

```blade
@can('delete invoices')
    <button wire:click="confirmDelete({{ $invoice->id }})">Delete</button>
@endcan

@can('send invoices')
    <button wire:click="markSent({{ $invoice->id }})">Mark sent</button>
@endcan

@can('manage users')
    <a href="/admin/users">Manage users</a>
@endcan
```

---

### B5 — Role Middleware on Routes

```php
// Protect routes by role
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'users']);
});

// Protect by permission
Route::middleware(['auth', 'permission:send invoices'])->group(function () {
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
});

// Multiple roles (OR)
Route::middleware(['auth', 'role:admin|manager'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
});
```

---

### B6 — Quick Spatie Reference

```php
// Assign roles
$user->assignRole('admin');
$user->assignRole(['admin', 'manager']);

// Remove roles
$user->removeRole('manager');

// Sync roles (removes all, assigns new)
$user->syncRoles(['freelancer']);

// Check roles
$user->hasRole('admin');
$user->hasAnyRole(['admin', 'manager']);
$user->hasAllRoles(['admin', 'manager']);

// Check permissions
$user->can('delete invoices');
$user->hasPermissionTo('delete invoices');
$user->hasAnyPermission(['create invoices', 'edit invoices']);

// Get roles and permissions
$user->getRoleNames();           // Collection of role name strings
$user->getAllPermissions();      // Collection of Permission models
$user->getDirectPermissions();   // Permissions assigned directly (not via role)

// Role-specific permissions
$role = Role::findByName('manager');
$role->givePermissionTo('delete clients');
$role->revokePermissionTo('delete clients');
$role->syncPermissions(['view clients', 'create clients']);
```

---

## What We Learned Today

**Invoice UI (Part A):**
- Livewire computed properties (`getSubtotalProperty()`) — computed from other properties, recalculate reactively. Access as `$this->subtotal` in PHP, `$this->subtotal` in Blade
- Dynamic line items array — add and remove rows with `addLineItem()` and `removeLineItem(int $index)`. `array_splice()` removes by index in place
- `wire:confirm` — Livewire's built-in browser confirmation before an action fires. No modal needed for simple confirmations
- `wire:key` on repeating elements — required when the array order can change. Helps Livewire track which DOM element corresponds to which array item
- The `InvoiceService->create()` method auto-generates the invoice number, creates the record, and calls `recalculate()` in one call

**Roles & Permissions (Part B):**
- `spatie/laravel-permission` — roles and permissions stored in the database. Cached in memory per request
- `HasRoles` trait on User — adds `assignRole()`, `hasRole()`, `can()`, `syncRoles()`, `getAllPermissions()`
- `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions()` — always call this first in seeders to prevent stale cache issues
- `role:admin` and `permission:send invoices` middleware — Spatie ships these. Apply to routes directly
- `@can('permission name')` in Blade — works identically to native Laravel `@can`, Spatie integrates automatically

---

## Day 31 — Multi-Tenancy Basics

Tomorrow we prepare FreelanceFlow for teams. Right now each user is a solo freelancer with their own isolated data. In Day 31 we add the concept of a workspace — multiple users sharing one set of clients and projects. We will cover the global scope strategy for multi-tenancy, team switching in the UI, and how to scope every query to the current team without changing existing Livewire components.

See you on Day 31.