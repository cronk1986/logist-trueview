<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Customer;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $erp_id
 * @property string $name
 * @property ?int $parent_customer_id
 * @property-read ?CustomerModel $parent
 * @property-read Collection<int, CustomerModel> $subAccounts
 */
final class CustomerModel extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $table = 'customers';

    /** @var list<string> */
    protected $fillable = [
        'erp_id',
        'name',
        'parent_customer_id',
    ];

    /** @return BelongsTo<CustomerModel, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(related: self::class, foreignKey: 'parent_customer_id');
    }

    /** @return HasMany<CustomerModel, $this> */
    public function subAccounts(): HasMany
    {
        return $this->hasMany(related: self::class, foreignKey: 'parent_customer_id');
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
