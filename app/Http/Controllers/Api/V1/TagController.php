<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tags = Tag::withCount('projects')
                   ->orderBy('name')
                   ->get();

        return TagResource::collection($tags);
    }

    public function show(Tag $tag): TagResource
    {
        $tag->loadCount('projects');

        return new TagResource($tag);
    }
}