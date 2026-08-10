<?php

namespace Espo\Modules\GoogleIntegration\Core\Utils\Database\Orm\LinkConverters;

use Espo\Core\Utils\Database\Orm\Defs\AttributeDefs;
use Espo\Core\Utils\Database\Orm\Defs\EntityDefs;
use Espo\Core\Utils\Database\Orm\Defs\RelationDefs;
use Espo\Core\Utils\Database\Orm\LinkConverter;
use Espo\Entities\Team;
use Espo\ORM\Defs\RelationDefs as LinkDefs;
use Espo\ORM\Type\AttributeType;
use Espo\ORM\Type\RelationType;

/**
 * Polymorphic many-to-many: entity ↔ Team for Google Calendar share targets.
 */
class GoogleCalendarShareTeam implements LinkConverter
{
    private const ENTITY_TYPE_LENGTH = 100;

    public function convert(LinkDefs $linkDefs, string $entityType): EntityDefs
    {
        $name = $linkDefs->getName();
        $relationshipName = $linkDefs->getRelationshipName();

        return EntityDefs::create()
            ->withAttribute(
                AttributeDefs::create($name . 'Ids')
                    ->withType(AttributeType::JSON_ARRAY)
                    ->withNotStorable()
                    ->withParam('isLinkStub', false)
            )
            ->withAttribute(
                AttributeDefs::create($name . 'Names')
                    ->withType(AttributeType::JSON_OBJECT)
                    ->withNotStorable()
                    ->withParam('isLinkStub', false)
            )
            ->withRelation(
                RelationDefs::create($name)
                    ->withType(RelationType::MANY_MANY)
                    ->withForeignEntityType(Team::ENTITY_TYPE)
                    ->withRelationshipName($relationshipName)
                    ->withMidKeys('entityId', 'teamId')
                    ->withConditions(['entityType' => $entityType])
                    ->withAdditionalColumn(
                        AttributeDefs::create('entityType')
                            ->withType(AttributeType::VARCHAR)
                            ->withLength(self::ENTITY_TYPE_LENGTH)
                    )
            );
    }
}
