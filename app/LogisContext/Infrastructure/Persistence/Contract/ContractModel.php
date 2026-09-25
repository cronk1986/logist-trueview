<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Contract;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\VisibleCustomerIds;
use Logis\SharedContext\Infrastructure\Persistence\LikePattern;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $name
 * @property ContractStatus $status
 * @property-read CustomerModel $customer
 */
final class ContractModel extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    protected $table = 'contracts';

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'name',
        'status',
    ];

    /** @return BelongsTo<CustomerModel, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(related: CustomerModel::class, foreignKey: 'customer_id');
    }

    /** @param Builder<ContractModel> $query */
    public function scopeVisibleTo(Builder $query, VisibleCustomers $customers): void
    {
        $query->whereIn('customer_id', VisibleCustomerIds::of(customers: $customers));
    }

    /** @param Builder<ContractModel> $query */
    public function scopeMatching(Builder $query, SearchTerm $term): void
    {
        $query->where(column: 'name', operator: 'like', value: LikePattern::contains(value: $term->value()));
    }

    /** @param Builder<ContractModel> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where(column: 'status', operator: ContractStatus::Active->value);
    }

    protected static function newFactory(): ContractFactory
    {
        return ContractFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
        ];
    }
}
