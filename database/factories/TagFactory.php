<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    private array $tagNames = [
        'Web Design',
        'Mobile App',
        'Branding',
        'SEO',
        'Copywriting',
        'Photography',
        'Video Production',
        'UI/UX Design',
        'Backend Development',
        'Frontend Development',
        'E-commerce',
        'Social Media',
        'Content Strategy',
        'Data Analysis',
        'DevOps',
    ];

    private array $colours = [
        '#6366f1',
        '#8b5cf6',
        '#ec4899',
        '#f59e0b',
        '#10b981',
        '#3b82f6',
        '#ef4444',
        '#f97316',
    ];

    public function definition(): array
    {
        $name = fake()->unique()->randomElement($this->tagNames);

        return [
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'colour' => fake()->randomElement($this->colours),
        ];
    }
}