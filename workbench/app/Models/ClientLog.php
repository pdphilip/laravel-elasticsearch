<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model as Eloquent;
use Workbench\Database\Factories\ClientLogFactory;

/**
 * App\Models\ClientLog
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $client_id
 * @property string $title
 * @property string $desc
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read Client $client
 *
 ******Attributes*******
 * @property-read mixed $status_name
 * @property-read mixed $status_color
 *
 * @mixin \Eloquent
 */
class ClientLog extends Eloquent
{
    use HasFactory;

    public $connection = 'elasticsearch';

    //Relationships  =====================================

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public static function newFactory(): ClientLogFactory
    {
        return ClientLogFactory::new();
    }
}
