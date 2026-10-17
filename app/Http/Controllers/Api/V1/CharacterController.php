<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterResource;
use App\Models\Character;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CharacterResource::collection(Character::query()->orderBy('id')->get());
    }
}
