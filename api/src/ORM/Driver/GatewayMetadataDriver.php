<?php


namespace App\ORM\Driver;


use App\Entity\Attribute;
use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\InvalidEntityRepository;
use Doctrine\ORM\Mapping\Builder\AssociationBuilder;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Ramsey\Uuid\Doctrine\UuidGenerator;

class GatewayMetadataDriver implements MappingDriver
{

    private EntityManagerInterface $generalEntityManager;
    private array $associationTables;

    public function __construct(EntityManagerInterface $generalEntityManager)
    {
        $this->generalEntityManager = $generalEntityManager;
        $this->associationTables = [];
    }

    /**
     * @param ClassMetadataBuilder $builder
     * @return ClassMetadataBuilder
     */
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

    public function buildManyToManyRelation(Attribute $attribute, ClassMetadataBuilder &$classMetadataBuilder): ?AssociationBuilder
    {
        $fieldName = $attribute->getColumnName() ?? $attribute->getName();

        $inverse = $attribute->getInversedBy()->getColumnName() ?? $attribute->getInversedBy()->getName();

        if(in_array(strtolower($inverse.'_'.$attribute->getInversedBy()->getEntity()->getName()), $this->associationTables)){
            $tableName = strtolower($inverse.'_'.$attribute->getInversedBy()->getEntity()->getName());
            return null;
        } else {
            $tableName = strtolower($fieldName.'_'.$attribute->getEntity()->getName());
        }

        $builder = $classMetadataBuilder->createManyToMany($fieldName, $attribute->getInversedBy()->getEntity()->getName());
        $builder->setJoinTable($tableName);
        $builder->setIndexBy('id');
        $builder->addInverseJoinColumn($attribute->getInversedBy()->getEntity()->getName().'_id', 'id');
        $builder->addJoinColumn($attribute->getEntity()->getName().'_id', 'id');

        if($attribute->getCascade()){
            $builder->cascadePersist();
        }
        if($attribute->getCascadeDelete()){
            $builder->cascadeRemove();
        }

        $builder->fetchLazy();
        $builder->build();

        if(!in_array($tableName, $this->associationTables)){
            $this->associationTables[] = $tableName;
        }

        return $builder;
    }

    /**
     * @param Attribute $attribute
     * @param ClassMetadataBuilder $builder
     * @return ClassMetadataBuilder
     * @throws InvalidEntityRepository
     */
    public function addRelation(Attribute $attribute, ClassMetadataBuilder $builder): ClassMetadataBuilder
    {

        if($attribute->getMultiple() && $attribute->getInversedBy()->getMultiple()){
            $this->buildManyToManyRelation($attribute, $builder);
//            $relation = $builder->createManyToMany($attribute->getName(), $attribute->getInversedBy()->getEntity()->getName())->mappedBy($attribute->getInversedBy()->getName())->setJoinTable($attribute->getEntity()->getName().'_'.$attribute->getInversedBy()->getEntity()->getName())->setIndexBy('id')->addInverseJoinColumn($attribute->getInversedBy()->getEntity()->getName().'_id', 'id')->addJoinColumn($attribute->getEntity()->getName().'_id', 'id');
        } elseif($attribute->getMultiple()) {
//            $relation = $builder->createOneToMany($attribute->getName(), $attribute->getInversedBy()->getEntity()->getName())->mappedBy($attribute->getInversedBy()->getName())->addJoinColumn($attribute->getColumnName() ?? $attribute->getName(), $attribute->getInversedBy()->getColumnName() ?? $attribute->getInversedBy()->getName());
        } elseif($attribute->getInversedBy() && $attribute->getInversedBy()->getMultiple()){
//            $relation = $builder->createManyToOne($attribute->getName(), $attribute->getInversedBy()->getEntity()->getName())->mappedBy($attribute->getInversedBy()->getName())->addJoinColumn($attribute->getColumnName() ?? $attribute->getName(), $attribute->getInversedBy()->getColumnName() ?? $attribute->getInversedBy()->getName());
        } elseif($attribute->getInversedBy()) {
//            $relation = $builder->createOneToOne($attribute->getName(), $attribute->getInversedBy()->getEntity()->getName())->mappedBy($attribute->getInversedBy()->getName())->addJoinColumn($attribute->getColumnName() ?? $attribute->getName(), $attribute->getInversedBy()->getColumnName() ?? $attribute->getInversedBy()->getName());
        } else {
            throw new InvalidEntityRepository('A relation to another entity cannot be made without a inverse');
        }
//        if($attribute->getCascade()){
//            $relation = $relation->cascadePersist();
//        }
//        if($attribute->getCascadeDelete()){
//            $relation = $relation->cascadeRemove();
//        }
//        $relation->build();

        return $builder;
    }

    /**
     * @param Attribute $attribute
     * @param ClassMetadataBuilder $builder
     * @return ClassMetadataBuilder
     */
    public function addField(Attribute $attribute, ClassMetadataBuilder $builder)
    {
        $field = $builder->createField($attribute->getName(), $attribute->getType())
            ->nullable($attribute->getNullable());
        if($attribute->getMaxLength()){
            $field->length($attribute->getMaxLength());
        }
        if($attribute->getColumnName()){
            $field->columnName($attribute->getColumnName());
        }
        $field->build();

        return $builder;
    }

    /**
     * @param ClassMetadataBuilder $builder
     * @param array $attributes
     * @return ClassMetadataBuilder
     * @throws InvalidEntityRepository
     */
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

    /**
     * @param $className
     * @param ClassMetadata $metadata
     * @return void
     * @throws InvalidEntityRepository
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $className = substr($className, strlen('Database\\'));
        $entity = $this->generalEntityManager->getRepository('App:Entity')->findOneBy(['name' => $className]);
        if(!($entity instanceof Entity) || !($metadata instanceof \Doctrine\ORM\Mapping\ClassMetadata)){
            return;
        }
        $attributes = $entity->getAttributes();

        $tableName = $entity->getTableName() ?? strtolower($entity->getName());

        $metadata->name             = $entity->getName();
        $metadata->table['name']    = $tableName;

        $metadataBuilder = new ClassMetadataBuilder($metadata);
        $metadataBuilder = $this->addFields($metadataBuilder, $attributes->toArray());
    }

    /**
     * @return array|string[]
     */
    public function getAllClassNames(): array
    {
        $entities = $this->generalEntityManager->getRepository('App:Entity')->findAll();
        $names = [];
        foreach($entities as $entity) {
            $names[] = 'Database\\'.$entity->getName();
        }

        return $names;
    }

    /**
     * @param $className
     * @return bool
     */
    public function isTransient($className): bool
    {
        if(in_array($className, $this->getAllClassNames())){
            return true;
        }

        return false;
    }
}
