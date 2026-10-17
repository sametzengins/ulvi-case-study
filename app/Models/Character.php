<?php

namespace App\Models;

use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $tagline
 * @property string $system_prompt
 */
#[Fillable(['slug', 'name', 'tagline', 'system_prompt'])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;
}
