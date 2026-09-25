<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Location;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $title
 * @property ?string $full_address
 * @property ?string $city
 * @property ?string $state
 * @property ?string $postal_code
 * @property-read CustomerModel $customer
 */
final class LocationModel extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $table = 'locations';

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'title',
        'full_address',
        'city',
        'state',
        'postal_code',
    ];

    /** @return BelongsTo<CustomerModel, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(related: CustomerModel::class, foreignKey: 'customer_id');
    }

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }
}
