<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\CustomView;

use Database\Factories\CustomViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;

/**
 * @property int $id
 * @property int $user_id
 * @property int $customer_id
 * @property string $title
 * @property ?string $description
 * @property ViewType $view_type
 * @property array<mixed> $columns
 * @property array<mixed> $filters
 * @property bool $is_default
 * @property bool $is_public
 * @property-read UserModel $user
 * @property-read CustomerModel $customer
 */
final class CustomViewModel extends Model
{
    /** @use HasFactory<CustomViewFactory> */
    use HasFactory;

    protected $table = 'custom_views';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'customer_id',
        'title',
        'description',
        'view_type',
        'columns',
        'filters',
        'is_default',
        'is_public',
    ];

    /** @return BelongsTo<UserModel, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(related: UserModel::class, foreignKey: 'user_id');
    }

    /** @return BelongsTo<CustomerModel, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(related: CustomerModel::class, foreignKey: 'customer_id');
    }

    protected static function newFactory(): CustomViewFactory
    {
        return CustomViewFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'view_type' => ViewType::class,
            'columns' => 'array',
            'filters' => 'array',
            'is_default' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
