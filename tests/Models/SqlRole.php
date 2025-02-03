<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Support\Facades\Schema;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;

use function assert;

class SqlRole extends EloquentModel
{
    use HybridRelations;

    protected $connection = 'sqlite';

    protected $table = 'roles';

    protected static $unguarded = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sqlUser(): BelongsTo
    {
        return $this->belongsTo(SqlUser::class);
    }

    public static function executeSchema(): void
    {
        $schema = Schema::connection('sqlite');
        assert($schema instanceof SQLiteBuilder);

        $schema->dropIfExists('roles');
        $schema->create('roles', function (Blueprint $table) {
            $table->string('type');
            $table->string('user_id');
            $table->timestamps();
        });
    }
}
