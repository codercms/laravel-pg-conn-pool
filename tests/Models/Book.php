<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $author_id
 * @property string $name
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Author $author
 * @method static \Illuminate\Database\Eloquent\Builder|Book newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Book newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Book query()
 */
class Book extends Model
{
    public const UPDATED_AT = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'id', 'author');
    }
}
