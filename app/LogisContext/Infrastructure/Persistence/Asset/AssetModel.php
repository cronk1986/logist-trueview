<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Asset;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $serial_number
 * @property string $manufacturer
 * @property string $model
 * @property string $item_name
 * @property ?int $contract_id
 * @property ?int $location_id
 * @property AssetStatus $status
 * @property Carbon $created_at
 * @property-read CustomerModel $customer
 * @property-read ?ContractModel $contract
 * @property-read ?LocationModel $location
 */
final class AssetModel extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'assets';

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'serial_number',
        'manufacturer',
        'model',
        'item_name',
        'contract_id',
        'location_id',
        'status',
    ];

    /** @return BelongsTo<CustomerModel, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(related: CustomerModel::class, foreignKey: 'customer_id');
    }

    /** @return BelongsTo<ContractModel, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(related: ContractModel::class, foreignKey: 'contract_id');
    }

    /** @return BelongsTo<LocationModel, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(related: LocationModel::class, foreignKey: 'location_id');
    }

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
        ];
    }
}
