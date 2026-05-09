# Day 17 — Many-to-Many Relationships — Project Tags with Pivot Tables

> **Series:** FreelanceFlow — Laravel Zero to Hero · **Phase 2 — Core Features**
> **Read time:** 16 min · **Level:** Intermediate

---

> *"Yesterday a project belonged to one client — a simple one-to-many. Today we tackle something more complex: a project can have many tags, and a tag can belong to many projects. That is many-to-many. It needs a pivot table, a different relationship method, and a completely different mental model. By the end of today, FreelanceFlow projects are taggable."*

---

## What Is a Many-to-Many Relationship?

In a one-to-many, the foreign key lives on one table. A project has a client_id. Simple.

In a many-to-many, neither table can store the foreign key directly. A project can have many tags. A tag can belong to many projects. You cannot put tag_id on the projects table — there could be five tags. You cannot put project_id on the tags table — there could be a hundred projects.

The solution is a pivot table — a third table that stores pairs of IDs:

```
projects         project_tag (pivot)       tags
--------         -------------------       ----
id               project_id                id
name             tag_id                    name
...                                        colour
```

Each row in project_tag represents one project-tag pairing. A project with three tags has three rows in the pivot. Remove a tag from a project — delete the row.

---

## What We Are Building Today

1. A **tags table** and Tag model
2. A **project_tag pivot table**
3. **`belongsToMany`** on both Project and Tag
4. **`attach()`, `detach()`, `sync()`** — managing the pivot
5. A **tag selector** on the Create Project Livewire form
6. A **TagFactory** and updated seeder
7. Displaying tags on the client show page

---

## Step 1 — Create the Tags Migration

```bash
php artisan make:migration create_tags_table
```

```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('colour')->default('#6366f1');
    $table->timestamps();
});
```

---

## Step 2 — Create the Pivot Table

Pivot table naming convention: both model names, alphabetical order, snake_case, singular. project + tag = project_tag.

```bash
php artisan make:migration create_project_tag_table
```

```php
Schema::create('project_tag', function (Blueprint $table) {
    $table->foreignId('project_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->foreignId('tag_id')
          ->constrained()
          ->cascadeOnDelete();

    // Composite primary key prevents duplicate pairings
    $table->primary(['project_id', 'tag_id']);
});
```

```bash
php artisan migrate
```

No id() column. No timestamps. Pivot tables are lean — just the relationship pairs.

---

## Step 3 — The Tag Model

```bash
php artisan make:model Tag
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'colour'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    // Auto-generate slug from name on save
    protected function name(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                $this->attributes['slug'] = str($value)->slug()->toString();
                return $value;
            },
        );
    }
}
```

Setting name automatically generates the slug. "Web Design" becomes "web-design". You never set the slug separately.

---

## Step 4 — Add belongsToMany to the Project Model

Open app/Models/Project.php and add:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function tags(): BelongsToMany
{
    return $this->belongsToMany(Tag::class);
}
```

That is the complete relationship. Laravel infers the pivot table name, the foreign keys, and the join condition automatically.

---

## Step 5 — attach(), detach(), sync()

This is where many-to-many differs from everything else. You do not use create() or save() on a pivot — you use dedicated methods.

```php
$project = Project::find(1);

// Add tags — does NOT remove existing
$project->tags()->attach([1, 2, 3]);

// Remove specific tags
$project->tags()->detach([1, 2]);

// Remove all tags
$project->tags()->detach();

// Sync — the method you use in forms
// Sets tags to EXACTLY the given array
// Attaches new ones, detaches removed ones
$project->tags()->sync([1, 3, 5]);

// Add new tags without removing existing
$project->tags()->syncWithoutDetaching([4, 5]);

// Check if a tag is attached
$project->tags->contains($tag);
$project->tags->contains('id', 1);
```

sync() is what you almost always want when saving a form. The user selects tags 1, 3, and 5. You call sync([1, 3, 5]). Laravel figures out what to attach and detach. No manual diffing.

---

## Step 6 — TagFactory and Seeder

```bash
php artisan make:factory TagFactory --model=Tag
php artisan make:seeder TagSeeder
```

TagFactory:

```php
<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    private array $tagNames = [
        'Web Design', 'Mobile App', 'Branding', 'SEO',
        'Copywriting', 'Photography', 'Video Production',
        'UI/UX Design', 'Backend Development', 'Frontend Development',
        'E-commerce', 'Social Media', 'Content Strategy',
        'Data Analysis', 'DevOps',
    ];

    private array $colours = [
        '#6366f1', '#8b5cf6', '#ec4899', '#f59e0b',
        '#10b981', '#3b82f6', '#ef4444', '#f97316',
    ];

    public function definition(): array
    {
        $name = fake()->unique()->randomElement($this->tagNames);

        return [
            'name'   => $name,
            'slug'   => str($name)->slug()->toString(),
            'colour' => fake()->randomElement($this->colours),
        ];
    }
}
```

TagSeeder:

```php
<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        Tag::factory()->count(15)->create();

        $tags = Tag::all();

        // Attach 1-3 random tags to each project
        Project::all()->each(function (Project $project) use ($tags) {
            $randomTags = $tags->random(fake()->numberBetween(1, 3));
            $project->tags()->sync($randomTags->pluck('id')->toArray());
        });

        $this->command->info('Seeded 15 tags and attached to all projects');
    }
}
```

Update DatabaseSeeder:

```php
$this->call([
    ClientSeeder::class,
    TagSeeder::class, // must run after clients (projects need clients first)
]);
```

```bash
php artisan migrate:fresh --seed
```

---

## Step 7 — Tag Selector on the Create Project Form

Update the Create Livewire component to handle tags:

```php
<?php

namespace App\Livewire\Projects;

use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New Project — FreelanceFlow')]
class Create extends Component
{
    public ?int $client_id = null;

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

    #[Rule('nullable|date|after_or_equal:today')]
    public ?string $deadline = null;

    #[Rule('nullable|array')]
    public array $selectedTags = [];

    public function mount(): void
    {
        if ($this->client_id) {
            $this->selectedClientId = $this->client_id;
        }
    }

    public function save(): void
    {
        $this->validate();

        $project = Project::create([
            'client_id'   => $this->selectedClientId,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'budget'      => $this->budget ?: null,
            'deadline'    => $this->deadline ?: null,
        ]);

        // Sync tags via the pivot table
        $project->tags()->sync($this->selectedTags);

        session()->flash('success', 'Project created successfully.');

        $this->redirect(
            route('clients.show', $this->selectedClientId),
            navigate: true
        );
    }

    public function render()
    {
        return view('livewire.projects.create', [
            'clients' => Client::active()->orderBy('name')->get(),
            'tags'    => Tag::orderBy('name')->get(),
        ]);
    }
}
```

The tag selector in the view — checkboxes bound to the selectedTags array:

```blade
{{-- Tags --}}
<flux:field>
    <flux:label>Tags <span class="text-gray-400 text-xs font-normal">(optional)</span></flux:label>
    <div class="flex flex-wrap gap-2 mt-1">
        @foreach ($tags as $tag)
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input
                    type="checkbox"
                    wire:model="selectedTags"
                    value="{{ $tag->id }}"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <span
                    class="text-xs font-medium px-2 py-0.5 rounded-full"
                    style="background-color: {{ $tag->colour }}22; color: {{ $tag->colour }}"
                >
                    {{ $tag->name }}
                </span>
            </label>
        @endforeach
    </div>
    <flux:error name="selectedTags" />
</flux:field>
```

When a checkbox is checked, its value (tag ID) is added to the selectedTags array. When unchecked, removed. When save() runs, sync() does the rest.

The hex colour trick: appending 22 to a hex colour gives 13% opacity background. #6366f122 is indigo at 13% opacity. Works on any background without needing rgba().

---

## Step 8 — Display Tags on the Client Show Page

Update the project card in clients/show.blade.php:

```blade
@if ($project->tags->isNotEmpty())
    <div class="flex flex-wrap gap-1.5 mt-1.5">
        @foreach ($project->tags as $tag)
            <span
                class="text-xs font-medium px-2 py-0.5 rounded-full"
                style="background-color: {{ $tag->colour }}22; color: {{ $tag->colour }}"
            >
                {{ $tag->name }}
            </span>
        @endforeach
    </div>
@endif
```

---

## Pivot Extra Data with withPivot()

Sometimes you need extra data on the pivot itself — who added the tag, when it was added, a sort order.

Add the column to the pivot migration:

```php
$table->timestamp('tagged_at')->useCurrent();
```

Load it with withPivot():

```php
public function tags(): BelongsToMany
{
    return $this->belongsToMany(Tag::class)
                ->withPivot('tagged_at')
                ->withTimestamps();
}
```

Access it on any tag in the relationship:

```php
foreach ($project->tags as $tag) {
    echo $tag->pivot->tagged_at;
}
```

FreelanceFlow does not use this today — but the pattern is essential for features like "who assigned this" or "when was this attached" that appear later in Phase 2.

---

## Many-to-Many Quick Reference

```php
// Relationship definition
public function tags(): BelongsToMany
{
    return $this->belongsToMany(Tag::class);
}

// Attach — adds, never removes
$project->tags()->attach([1, 2, 3]);

// Detach — removes specific or all
$project->tags()->detach([1, 2]);
$project->tags()->detach();

// Sync — set exactly, attach new, detach removed
$project->tags()->sync([1, 3, 5]);

// SyncWithoutDetaching — add new, never remove
$project->tags()->syncWithoutDetaching([4, 5]);

// Access the relationship
$project->tags;                  // Collection of Tag models
$project->tags()->count();       // count without loading all
$tag->projects;                  // all projects with this tag

// Check membership
$project->tags->contains($tag);
$project->tags->contains('id', 1);

// With pivot data
$tag->pivot->tagged_at;
```

---

## What We Learned Today

- Many-to-many requires a pivot table — a join table storing pairs of IDs from both related tables
- Pivot table naming — alphabetical, snake_case, singular: project_tag not projects_tags
- belongsToMany() on both sides — Laravel infers the pivot table and foreign keys by convention
- attach() — add relationships without removing existing ones
- detach() — remove specific or all relationships
- sync() — set the relationship to exactly the given array — the right tool for saving forms
- withPivot() — access extra columns stored on the pivot via $model->pivot->column
- Checkbox wire:model array binding — checked IDs pushed into a PHP array automatically
- Hex colour with 22 suffix — 13% opacity background tint from any hex colour

---

## Day 18 — Eager Loading and the N+1 Problem

Tomorrow we fix a performance issue hiding in FreelanceFlow right now. When the client show page renders projects and their tags, it runs one database query per project to load the tags. With 90 projects that is 91 queries where 2 would suffice. We will learn with(), load(), withCount(), and how to use Laravel Debugbar to find and eliminate N+1 queries across the entire app.

See you on Day 18.