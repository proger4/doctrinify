<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\Pipeline;

final readonly class RelationDecisionSchema
{
    public function __construct(
        public ModelRelationSchema $relation,
        public bool $accepted,
        public ?string $rejectionReason,
    ) {
    }
}
