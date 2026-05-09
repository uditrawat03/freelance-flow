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