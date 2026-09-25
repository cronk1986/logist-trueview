<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Contract;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;

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
