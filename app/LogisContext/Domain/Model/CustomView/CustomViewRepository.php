<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;

interface CustomViewRepository
{
    public function findById(CustomViewId $id): ?CustomView;
}
