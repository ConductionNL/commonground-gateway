<?php


namespace App\ORM\Driver;


use App\Entity\Attribute;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Ramsey\Uuid\Doctrine\UuidGenerator;

class GatewayMetadataDriver implements MappingDriver
{

    private EntityManagerInterface $generalEntityManager;

    private EntityManagerInterface $localEntityManager;

    public function __construct(EntityManagerInterface $generalEntityManager)
    {
        $this->generalEntityManager = $generalEntityManager;
    }

    public function setDefaultFields(ClassMetadataBuilder $builder): ClassMetadataBuilder
    {
        $builder->createField('id', 'uuid')
            ->nullable(false)
            ->makePrimaryKey()
            ->generatedValue()
            ->setCustomIdGenerator(UuidGenerator::class)
            ->build();
        $builder->createField('dateCreated', 'datetime')->nullable()->build();
        $builder->createField('dateModified', 'datetime')->nullable()->build();
        $builder->createField('organization', 'string')->build();
        $builder->createField('owner', 'string')->build();
        $builder->createField('application', 'string')->build();
        $builder->createField('externalId', 'string')->build();
        return $builder;
    }

    public function addRelation(Attribute $attribute, ClassMetadataBuilder $builder): ClassMetadataBuilder
    {
        if($attribute->getMultiple() && $attribute->getInversedBy()->getMultiple()){
            $relation = $builder->createManyToMany($attribute->getName(), $attribute->getInversedBy()->getEntity()->getName());
        } elseif($attribute->getMultiple()) {
            $relation = $builder->createOneToMany($attribute->getName(), $attribute->getInversedBy()->getEntity()->getName());
        } elseif($attribute->getInversedBy() && $attribute->getInversedBy()->getMultiple()){
            $relation = $builder->createManyToOne($attribute->getName(), $attribute->getInversedBy()->getEntity()->getName());
        } elseif($attribute->getInversedBy()) {
            $relation = $builder->createOneToOne($attribute, $attribute->getInversedBy());
        }
        if($attribute->getCascade()){
            $relation->cascadePersist();
        }
        if($attribute->getCascadeDelete()){
            $relation->cascadeRemove();
        }
        $relation->build();

        return $builder;
    }

    public function addField(Attribute $attribute, ClassMetadataBuilder $builder)
    {
        $field = $builder->createField($attribute->getName(), $attribute->getType())
            ->nullable($attribute->getNullable());
        if($attribute->getMaxLength()){
            $field->length($attribute->getMaxLength());
        }
        if($attribute->getColumn()){
            $field->columnName($attribute->getColumn());
        }
        $field->build();

        return $builder;
    }

    public function addFields(ClassMetadataBuilder $builder, array $attributes): ClassMetadataBuilder
    {
        $builder = $this->setDefaultFields($builder);

        foreach($attributes as $attribute)
        {
            if($attribute instanceof Attribute && $attribute->getType() != 'object'){
                $builder = $this->addField($attribute, $builder);
            } elseif ($attribute instanceof Attribute) {
                $this->addRelation($attribute, $builder);
            }
        }

        return $builder;
    }

    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $entity = $this->generalEntityManager->getRepository('App:Entity')->findOneBy(['name' => $className]);
        if(!($entity instanceof Entity) || !($metadata instanceof \Doctrine\ORM\Mapping\ClassMetadata)){
            return;
        }
        $attributes = $entity->getAttributes();

        $tableName = $entity->getTableName() ?? strtolower($entity->getName());

        $metadata->name             = $entity->getName();
        $metadata->table['name']    = $tableName;

        $metadataBuilder = new ClassMetadataBuilder($metadata);
        $metadataBuilder = $this->addFields($metadataBuilder, $attributes);
    }

    public function getAllClassNames()
    {
        // TODO: Implement getAllClassNames() method.
    }

    public function isTransient($className)
    {
        // TODO: Implement isTransient() method.
    }
}