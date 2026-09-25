<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Location;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\SharedContext\Infrastructure\Persistence\LikePattern;

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

    /** @param Builder<LocationModel> $query */
    public function scopeVisibleTo(Builder $query, VisibleCustomers $customers): void
    {
        $query->whereIn('customer_id', $customers->values());
    }

    /** @param Builder<LocationModel> $query */
    public function scopeMatching(Builder $query, SearchTerm $term): void
    {
        $pattern = LikePattern::contains(value: $term->value());

        $query->where(column: function (Builder $query) use ($pattern): void {
            $query->where(column: 'title', operator: 'like', value: $pattern)
                ->orWhere(column: 'full_address', operator: 'like', value: $pattern)
                ->orWhere(column: 'city', operator: 'like', value: $pattern)
                ->orWhere(column: 'state', operator: 'like', value: $pattern)
                ->orWhere(column: 'postal_code', operator: 'like', value: $pattern);
        });
    }

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }
}
