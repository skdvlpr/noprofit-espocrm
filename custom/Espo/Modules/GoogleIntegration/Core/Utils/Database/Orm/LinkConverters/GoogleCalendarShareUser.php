<?php

namespace Espo\Modules\GoogleIntegration\Core\Utils\Database\Orm\LinkConverters;

use Espo\Core\Utils\Database\Orm\Defs\AttributeDefs;
use Espo\Core\Utils\Database\Orm\Defs\EntityDefs;
use Espo\Core\Utils\Database\Orm\Defs\RelationDefs;
use Espo\Core\Utils\Database\Orm\LinkConverter;
use Espo\Entities\User;
use Espo\ORM\Defs\RelationDefs as LinkDefs;
use Espo\ORM\Type\AttributeType;
use Espo\ORM\Type\RelationType;

/**
 * Polymorphic many-to-many: entity ↔ User for Google Calendar share targets.
 */
class GoogleCalendarShareUser implements LinkConverter
{
    private const ENTITY_TYPE_LENGTH = 100;

    public function convert(LinkDefs $linkDefs, string $entityType): EntityDefs
    {
        $name = $linkDefs->getName();
        $relationshipName = $linkDefs->getRelationshipName();

        return EntityDefs::create()
            ->withRelation(
                RelationDefs::create($name)
                    ->withType(RelationType::MANY_MANY)
                    ->withForeignEntityType(User::ENTITY_TYPE)
                    ->withRelationshipName($relationshipName)
                    ->withMidKeys('entityId', 'userId')
                    ->withConditions(['entityType' => $entityType])
                    ->withAdditionalColumn(
                        AttributeDefs::create('entityType')
                            ->withType(AttributeType::VARCHAR)
                            ->withLength(self::ENTITY_TYPE_LENGTH)
                    )
            );
    }
}
