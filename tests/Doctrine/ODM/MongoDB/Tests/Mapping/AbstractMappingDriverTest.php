<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Mapping;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Aggregation\Builder;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\MappingException;
use Doctrine\ODM\MongoDB\Repository\DefaultGridFSRepository;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Doctrine\ODM\MongoDB\Repository\ViewRepository;
use Doctrine\ODM\MongoDB\Tests\BaseTest;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Reflection\EnumReflectionProperty;
use Documents74\CustomCollection;
use Documents74\UserTyped;
use Documents81\Card;
use Documents81\Suit;
use InvalidArgumentException;

use function key;
use function sprintf;
use function strcmp;
use function usort;

abstract class AbstractMappingDriverTest extends BaseTest
{
    abstract protected function loadDriver(): MappingDriver;

    protected function createMetadataDriverImpl(): MappingDriver
    {
        return $this->loadDriver();
    }

    /**
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @doesNotPerformAssertions
     */
    public function testLoadMapping(): ClassMetadata
    {
        return $this->dm->getClassMetadata(AbstractMappingDriverUser::class);
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testLoadMapping
     */
    public function testDocumentCollectionNameAndInheritance(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('cms_users', $class->getCollection());
        $this->assertEquals(ClassMetadata::INHERITANCE_TYPE_NONE, $class->inheritanceType);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testLoadMapping
     */
    public function testDocumentMarkedAsReadOnly(ClassMetadata $class): ClassMetadata
    {
        $this->assertTrue($class->isReadOnly);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testDocumentCollectionNameAndInheritance
     */
    public function testDocumentLevelReadPreference(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('primaryPreferred', $class->readPreference);
        $this->assertEquals([
            ['dc' => 'east'],
            ['dc' => 'west'],
            [],
        ], $class->readPreferenceTags);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testDocumentCollectionNameAndInheritance
     */
    public function testDocumentLevelWriteConcern(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals(1, $class->getWriteConcern());

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testDocumentLevelWriteConcern
     */
    public function testFieldMappings(ClassMetadata $class): ClassMetadata
    {
        $this->assertCount(14, $class->fieldMappings);
        $this->assertTrue(isset($class->fieldMappings['identifier']));
        $this->assertTrue(isset($class->fieldMappings['version']));
        $this->assertTrue(isset($class->fieldMappings['lock']));
        $this->assertTrue(isset($class->fieldMappings['name']));
        $this->assertTrue(isset($class->fieldMappings['email']));
        $this->assertTrue(isset($class->fieldMappings['roles']));

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @depends testDocumentCollectionNameAndInheritance
     */
    public function testAssociationMappings(ClassMetadata $class): void
    {
        $this->assertCount(6, $class->associationMappings);
        $this->assertTrue(isset($class->associationMappings['address']));
        $this->assertTrue(isset($class->associationMappings['phonenumbers']));
        $this->assertTrue(isset($class->associationMappings['groups']));
        $this->assertTrue(isset($class->associationMappings['morePhoneNumbers']));
        $this->assertTrue(isset($class->associationMappings['embeddedPhonenumber']));
        $this->assertTrue(isset($class->associationMappings['otherPhonenumbers']));
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @depends testDocumentCollectionNameAndInheritance
     */
    public function testGetAssociationTargetClass(ClassMetadata $class): void
    {
        $this->assertEquals(Address::class, $class->getAssociationTargetClass('address'));
        $this->assertEquals(Group::class, $class->getAssociationTargetClass('groups'));
        $this->assertNull($class->getAssociationTargetClass('phonenumbers'));
        $this->assertEquals(Phonenumber::class, $class->getAssociationTargetClass('morePhoneNumbers'));
        $this->assertEquals(Phonenumber::class, $class->getAssociationTargetClass('embeddedPhonenumber'));
        $this->assertNull($class->getAssociationTargetClass('otherPhonenumbers'));
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @depends testDocumentCollectionNameAndInheritance
     */
    public function testGetAssociationTargetClassThrowsExceptionWhenEmpty(ClassMetadata $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $class->getAssociationTargetClass('invalid_association');
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testDocumentCollectionNameAndInheritance
     */
    public function testStringFieldMappings(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('string', $class->fieldMappings['name']['type']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testFieldMappings
     */
    public function testIdentifier(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('identifier', $class->identifier);

        return $class;
    }

    /**
     * @requires PHP >= 7.4
     */
    public function testFieldTypeFromReflection(): void
    {
        $class = $this->dm->getClassMetadata(UserTyped::class);

        $this->assertSame(Type::ID, $class->getTypeOfField('id'));
        $this->assertSame(Type::STRING, $class->getTypeOfField('username'));
        $this->assertSame(Type::DATE, $class->getTypeOfField('dateTime'));
        $this->assertSame(Type::DATE_IMMUTABLE, $class->getTypeOfField('dateTimeImmutable'));
        $this->assertSame(Type::HASH, $class->getTypeOfField('array'));
        $this->assertSame(Type::BOOL, $class->getTypeOfField('boolean'));
        $this->assertSame(Type::FLOAT, $class->getTypeOfField('float'));

        $this->assertSame(CustomCollection::class, $class->getAssociationCollectionClass('embedMany'));
        $this->assertSame(CustomCollection::class, $class->getAssociationCollectionClass('referenceMany'));
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testFieldMappings
     */
    public function testVersionFieldMappings(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('int', $class->fieldMappings['version']['type']);
        $this->assertNotEmpty($class->fieldMappings['version']['version']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testFieldMappings
     */
    public function testLockFieldMappings(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('int', $class->fieldMappings['lock']['type']);
        $this->assertNotEmpty($class->fieldMappings['lock']['lock']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testIdentifier
     */
    public function testAssocations(ClassMetadata $class): ClassMetadata
    {
        $this->assertCount(14, $class->fieldMappings);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testAssocations
     */
    public function testOwningOneToOneAssociation(ClassMetadata $class): ClassMetadata
    {
        $this->assertTrue(isset($class->fieldMappings['address']));
        $this->assertIsArray($class->fieldMappings['address']);
        // Check cascading
        $this->assertTrue($class->fieldMappings['address']['isCascadeRemove']);
        $this->assertFalse($class->fieldMappings['address']['isCascadePersist']);
        $this->assertFalse($class->fieldMappings['address']['isCascadeRefresh']);
        $this->assertFalse($class->fieldMappings['address']['isCascadeDetach']);
        $this->assertFalse($class->fieldMappings['address']['isCascadeMerge']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testOwningOneToOneAssociation
     */
    public function testLifecycleCallbacks(ClassMetadata $class): ClassMetadata
    {
        $expectedLifecycleCallbacks = [
            'prePersist' => ['doStuffOnPrePersist', 'doOtherStuffOnPrePersistToo'],
            'postPersist' => ['doStuffOnPostPersist'],
        ];

        $this->assertEquals($expectedLifecycleCallbacks, $class->lifecycleCallbacks);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testLifecycleCallbacks
     */
    public function testCustomFieldName(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('name', $class->fieldMappings['name']['fieldName']);
        $this->assertEquals('username', $class->fieldMappings['name']['name']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testCustomFieldName
     */
    public function testCustomReferenceFieldName(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('morePhoneNumbers', $class->fieldMappings['morePhoneNumbers']['fieldName']);
        $this->assertEquals('more_phone_numbers', $class->fieldMappings['morePhoneNumbers']['name']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testCustomReferenceFieldName
     */
    public function testCustomEmbedFieldName(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('embeddedPhonenumber', $class->fieldMappings['embeddedPhonenumber']['fieldName']);
        $this->assertEquals('embedded_phone_number', $class->fieldMappings['embeddedPhonenumber']['name']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testCustomEmbedFieldName
     */
    public function testDiscriminator(ClassMetadata $class): ClassMetadata
    {
        $this->assertEquals('discr', $class->discriminatorField);
        $this->assertEquals(['default' => AbstractMappingDriverUser::class], $class->discriminatorMap);
        $this->assertEquals('default', $class->defaultDiscriminatorValue);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testDiscriminator
     */
    public function testEmbedDiscriminator(ClassMetadata $class): ClassMetadata
    {
        $this->assertTrue(isset($class->fieldMappings['otherPhonenumbers']['discriminatorField']));
        $this->assertTrue(isset($class->fieldMappings['otherPhonenumbers']['discriminatorMap']));
        $this->assertTrue(isset($class->fieldMappings['otherPhonenumbers']['defaultDiscriminatorValue']));
        $this->assertEquals('discr', $class->fieldMappings['otherPhonenumbers']['discriminatorField']);
        $this->assertEquals([
            'home' => HomePhonenumber::class,
            'work' => WorkPhonenumber::class,
        ], $class->fieldMappings['otherPhonenumbers']['discriminatorMap']);
        $this->assertEquals('home', $class->fieldMappings['otherPhonenumbers']['defaultDiscriminatorValue']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testEmbedDiscriminator
     */
    public function testReferenceDiscriminator(ClassMetadata $class): ClassMetadata
    {
        $this->assertTrue(isset($class->fieldMappings['phonenumbers']['discriminatorField']));
        $this->assertTrue(isset($class->fieldMappings['phonenumbers']['discriminatorMap']));
        $this->assertTrue(isset($class->fieldMappings['phonenumbers']['defaultDiscriminatorValue']));
        $this->assertEquals('discr', $class->fieldMappings['phonenumbers']['discriminatorField']);
        $this->assertEquals([
            'home' => HomePhonenumber::class,
            'work' => WorkPhonenumber::class,
        ], $class->fieldMappings['phonenumbers']['discriminatorMap']);
        $this->assertEquals('home', $class->fieldMappings['phonenumbers']['defaultDiscriminatorValue']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @return ClassMetadata<AbstractMappingDriverUser>
     *
     * @depends testCustomFieldName
     */
    public function testIndexes(ClassMetadata $class): ClassMetadata
    {
        $indexes = $class->indexes;

        /* Sort indexes by their first fieldname. This is necessary since the
         * index registration order may differ among drivers.
         */
        $this->assertTrue(usort($indexes, static function (array $a, array $b) {
            return strcmp(key($a['keys']), key($b['keys']));
        }));

        $this->assertTrue(isset($indexes[0]['keys']['createdAt']));
        $this->assertEquals(1, $indexes[0]['keys']['createdAt']);
        $this->assertNotEmpty($indexes[0]['options']);
        $this->assertTrue(isset($indexes[0]['options']['expireAfterSeconds']));
        $this->assertSame(3600, $indexes[0]['options']['expireAfterSeconds']);

        $this->assertTrue(isset($indexes[1]['keys']['email']));
        $this->assertEquals(-1, $indexes[1]['keys']['email']);
        $this->assertNotEmpty($indexes[1]['options']);
        $this->assertTrue(isset($indexes[1]['options']['unique']));
        $this->assertEquals(true, $indexes[1]['options']['unique']);

        $this->assertTrue(isset($indexes[2]['keys']['lock']));
        $this->assertEquals(1, $indexes[2]['keys']['lock']);
        $this->assertNotEmpty($indexes[2]['options']);
        $this->assertTrue(isset($indexes[2]['options']['partialFilterExpression']));
        $this->assertSame(['version' => ['$gt' => 1], 'discr' => ['$eq' => 'default']], $indexes[2]['options']['partialFilterExpression']);

        $this->assertTrue(isset($indexes[3]['keys']['mysqlProfileId']));
        $this->assertEquals(-1, $indexes[3]['keys']['mysqlProfileId']);
        $this->assertNotEmpty($indexes[3]['options']);
        $this->assertTrue(isset($indexes[3]['options']['unique']));
        $this->assertEquals(true, $indexes[3]['options']['unique']);

        $this->assertTrue(isset($indexes[4]['keys']['username']));
        $this->assertEquals(-1, $indexes[4]['keys']['username']);
        $this->assertTrue(isset($indexes[4]['options']['unique']));
        $this->assertEquals(true, $indexes[4]['options']['unique']);

        return $class;
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $class
     *
     * @depends testIndexes
     */
    public function testShardKey(ClassMetadata $class): void
    {
        $shardKey = $class->getShardKey();

        $this->assertTrue(isset($shardKey['keys']['name']), 'Shard key is not mapped');
        $this->assertEquals(1, $shardKey['keys']['name'], 'Wrong value for shard key');

        $this->assertTrue(isset($shardKey['options']['unique']), 'Shard key option is not mapped');
        $this->assertTrue($shardKey['options']['unique'], 'Shard key option has wrong value');
        $this->assertTrue(isset($shardKey['options']['numInitialChunks']), 'Shard key option is not mapped');
        $this->assertEquals(4096, $shardKey['options']['numInitialChunks'], 'Shard key option has wrong value');
    }

    public function testGridFSMapping(): void
    {
        $class = $this->dm->getClassMetadata(AbstractMappingDriverFile::class);

        $this->assertTrue($class->isFile);
        $this->assertSame(12345, $class->getChunkSizeBytes());
        $this->assertNull($class->customRepositoryClassName);

        $this->assertArraySubset([
            'name' => '_id',
            'type' => 'id',
        ], $class->getFieldMapping('id'), true);

        $this->assertArraySubset([
            'name' => 'length',
            'type' => 'int',
            'notSaved' => true,
        ], $class->getFieldMapping('size'), true);

        $this->assertArraySubset([
            'name' => 'chunkSize',
            'type' => 'int',
            'notSaved' => true,
        ], $class->getFieldMapping('chunkSize'), true);

        $this->assertArraySubset([
            'name' => 'filename',
            'type' => 'string',
            'notSaved' => true,
        ], $class->getFieldMapping('name'), true);

        $this->assertArraySubset([
            'name' => 'uploadDate',
            'type' => 'date',
            'notSaved' => true,
        ], $class->getFieldMapping('uploadDate'), true);

        $this->assertArraySubset([
            'name' => 'metadata',
            'type' => 'one',
            'embedded' => true,
            'targetDocument' => AbstractMappingDriverFileMetadata::class,
        ], $class->getFieldMapping('metadata'), true);
    }

    public function testGridFSMappingWithCustomRepository(): void
    {
        $class = $this->dm->getClassMetadata(AbstractMappingDriverFileWithCustomRepository::class);

        $this->assertTrue($class->isFile);
        $this->assertSame(AbstractMappingDriverGridFSRepository::class, $class->customRepositoryClassName);
    }

    public function testDuplicateDatabaseNameInMappingCauseErrors(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(
            'Field "bar" in class "Doctrine\ODM\MongoDB\Tests\Mapping\AbstractMappingDriverDuplicateDatabaseName" ' .
            'is mapped to field "baz" in the database, but that name is already in use by field "foo".'
        );
        $this->dm->getClassMetadata(AbstractMappingDriverDuplicateDatabaseName::class);
    }

    public function testDuplicateDatabaseNameWithNotSavedDoesNotThrowExeption(): void
    {
        $metadata = $this->dm->getClassMetadata(AbstractMappingDriverDuplicateDatabaseNameNotSaved::class);

        $this->assertTrue($metadata->hasField('foo'));
        $this->assertTrue($metadata->hasField('bar'));
        $this->assertTrue($metadata->fieldMappings['bar']['notSaved']);
    }

    public function testViewWithoutRepository(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf(
            'Invalid repository class "%s" for mapped class "%s". It must be an instance of "%s".',
            DocumentRepository::class,
            AbstractMappingDriverViewWithoutRepository::class,
            ViewRepository::class
        ));

        $this->dm->getRepository(AbstractMappingDriverViewWithoutRepository::class);
    }

    public function testViewWithWrongRepository(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf(
            'Invalid repository class "%s" for mapped class "%s". It must be an instance of "%s".',
            DocumentRepository::class,
            AbstractMappingDriverViewWithWrongRepository::class,
            ViewRepository::class
        ));

        $this->dm->getRepository(AbstractMappingDriverViewWithWrongRepository::class);
    }

    public function testViewWithoutRootClass(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf(
            'Class "%s" mapped as view without must have a root class.',
            AbstractMappingDriverViewWithoutRootClass::class
        ));

        $this->dm->getClassMetadata(AbstractMappingDriverViewWithoutRootClass::class);
    }

    public function testViewWithNonExistingRootClass(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf(
            'Root class "%s" for view "%s" could not be found.',
            'Doctrine\ODM\MongoDB\LolNo',
            AbstractMappingDriverViewWithNonExistingRootClass::class
        ));

        $this->dm->getClassMetadata(AbstractMappingDriverViewWithNonExistingRootClass::class);
    }

    public function testView(): void
    {
        $metadata = $this->dm->getClassMetadata(AbstractMappingDriverView::class);

        $this->assertEquals('user_name', $metadata->getCollection());
        $this->assertEquals(ClassMetadata::INHERITANCE_TYPE_NONE, $metadata->inheritanceType);

        $this->assertEquals('id', $metadata->identifier);

        $this->assertArraySubset([
            'fieldName' => 'id',
            'id' => true,
            'name' => '_id',
            'type' => 'id',
            'isCascadeDetach' => false,
            'isCascadeMerge' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeRemove' => false,
            'isInverseSide' => false,
            'isOwningSide' => true,
            'nullable' => false,
        ], $metadata->fieldMappings['id']);

        $this->assertArraySubset([
            'fieldName' => 'name',
            'name' => 'name',
            'type' => 'string',
            'isCascadeDetach' => false,
            'isCascadeMerge' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeRemove' => false,
            'isInverseSide' => false,
            'isOwningSide' => true,
            'nullable' => false,
            'strategy' => ClassMetadata::STORAGE_STRATEGY_SET,
        ], $metadata->fieldMappings['name']);
    }

    /**
     * @requires PHP 8.1
     */
    public function testEnumType(): void
    {
        $metadata = $this->dm->getClassMetadata(Card::class);

        self::assertSame(Suit::class, $metadata->fieldMappings['suit']['enumType']);
        self::assertSame('string', $metadata->fieldMappings['suit']['type']);
        self::assertFalse($metadata->fieldMappings['suit']['nullable']);
        self::assertInstanceOf(EnumReflectionProperty::class, $metadata->reflFields['suit']);

        self::assertSame(Suit::class, $metadata->fieldMappings['nullableSuit']['enumType']);
        self::assertSame('string', $metadata->fieldMappings['nullableSuit']['type']);
        self::assertTrue($metadata->fieldMappings['nullableSuit']['nullable']);
        self::assertInstanceOf(EnumReflectionProperty::class, $metadata->reflFields['nullableSuit']);
    }
}

/**
 * @ODM\Document(collection="cms_users", writeConcern=1, readOnly=true)
 * @ODM\DiscriminatorField("discr")
 * @ODM\DiscriminatorMap({"default"="Doctrine\ODM\MongoDB\Tests\Mapping\AbstractMappingDriverUser"})
 * @ODM\DefaultDiscriminatorValue("default")
 * @ODM\HasLifecycleCallbacks
 * @ODM\Indexes(@ODM\Index(keys={"createdAt"="asc"},expireAfterSeconds=3600),@ODM\Index(keys={"lock"="asc"},partialFilterExpression={"version"={"$gt"=1},"discr"={"$eq"="default"}}))
 * @ODM\ShardKey(keys={"name"="asc"},unique=true,numInitialChunks=4096)
 * @ODM\ReadPreference("primaryPreferred", tags={
 *   { "dc"="east" },
 *   { "dc"="west" },
 *   {  }
 * })
 */
#[ODM\Document(collection: 'cms_users', writeConcern: 1, readOnly: true)]
#[ODM\DiscriminatorField('discr')]
#[ODM\DiscriminatorMap(['default' => AbstractMappingDriverUser::class])]
#[ODM\DefaultDiscriminatorValue('default')]
#[ODM\HasLifecycleCallbacks]
#[ODM\Index(keys: ['createdAt' => 'asc'], expireAfterSeconds: 3600)]
#[ODM\Index(keys: ['lock' => 'asc'], partialFilterExpression: ['version' => ['$gt' => 1], 'discr' => ['$eq' => 'default']])]
#[ODM\ShardKey(keys: ['name' => 'asc'], unique: true, numInitialChunks: 4096)]
#[ODM\ReadPreference('primaryPreferred', tags: [['dc' => 'east'], ['dc' => 'west'], []])]
class AbstractMappingDriverUser
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id()]
    public $identifier;

    /**
     * @ODM\Version
     * @ODM\Field(type="int")
     *
     * @var int|null
     */
    #[ODM\Version]
    #[ODM\Field(type: 'int')]
    public $version;

    /**
     * @ODM\Lock
     * @ODM\Field(type="int")
     *
     * @var int|null
     */
    #[ODM\Lock]
    #[ODM\Field(type: 'int')]
    public $lock;

    /**
     * @ODM\Field(name="username", type="string")
     * @ODM\UniqueIndex(order="desc")
     *
     * @var string|null
     */
    #[ODM\Field(name: 'username', type: 'string')]
    #[ODM\UniqueIndex(order: 'desc')]
    public $name;

    /**
     * @ODM\Field(type="string")
     * @ODM\UniqueIndex(order="desc")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'string')]
    #[ODM\UniqueIndex(order: 'desc')]
    public $email;

    /**
     * @ODM\Field(type="int")
     * @ODM\UniqueIndex(order="desc")
     *
     * @var int|null
     */
    #[ODM\Field(type: 'int')]
    #[ODM\UniqueIndex(order: 'desc')]
    public $mysqlProfileId;

    /**
     * @ODM\ReferenceOne(targetDocument=Address::class, cascade={"remove"})
     *
     * @var Address|null
     */
    #[ODM\ReferenceOne(targetDocument: Address::class, cascade: ['remove'])]
    public $address;

    /**
     * @ODM\ReferenceMany(collectionClass=PhonenumberCollection::class, cascade={"persist"}, discriminatorField="discr", discriminatorMap={"home"=HomePhonenumber::class, "work"=WorkPhonenumber::class}, defaultDiscriminatorValue="home")
     *
     * @var PhonenumberCollection
     */
    #[ODM\ReferenceMany(collectionClass: PhonenumberCollection::class, cascade: ['persist'], discriminatorField: 'discr', discriminatorMap: ['home' => HomePhonenumber::class, 'work' => WorkPhonenumber::class], defaultDiscriminatorValue: 'home')]
    public $phonenumbers;

    /**
     * @ODM\ReferenceMany(targetDocument=Group::class, cascade={"all"})
     *
     * @var Collection<int, Group>
     */
    #[ODM\ReferenceMany(targetDocument: Group::class, cascade: ['all'])]
    public $groups;

    /**
     * @ODM\ReferenceMany(targetDocument=Phonenumber::class, collectionClass=PhonenumberCollection::class, name="more_phone_numbers")
     *
     * @var PhonenumberCollection
     */
    #[ODM\ReferenceMany(targetDocument: Phonenumber::class, collectionClass: PhonenumberCollection::class, name: 'more_phone_numbers')]
    public $morePhoneNumbers;

    /**
     * @ODM\EmbedMany(targetDocument=Phonenumber::class, name="embedded_phone_number")
     *
     * @var Collection<int, Phonenumber>
     */
    #[ODM\EmbedMany(targetDocument: Phonenumber::class, name: 'embedded_phone_number')]
    public $embeddedPhonenumber;

    /**
     * @ODM\EmbedMany(discriminatorField="discr", discriminatorMap={"home"=HomePhonenumber::class, "work"=WorkPhonenumber::class}, defaultDiscriminatorValue="home")
     *
     * @var Collection<int, HomePhonenumber|WorkPhonenumber>
     */
    #[ODM\EmbedMany(discriminatorField: 'discr', discriminatorMap: ['home' => HomePhonenumber::class, 'work' => WorkPhonenumber::class], defaultDiscriminatorValue: 'home')]
    public $otherPhonenumbers;

    /**
     * @ODM\Field(type="date")
     *
     * @var DateTime|null
     */
    #[ODM\Field(type: 'date')]
    public $createdAt;

    /**
     * @ODM\Field(type="collection")
     *
     * @var string[]
     */
    #[ODM\Field(type: 'collection')]
    public $roles = [];

    /**
     * @ODM\PrePersist
     */
    #[ODM\PrePersist]
    public function doStuffOnPrePersist(): void
    {
    }

    /**
     * @ODM\PrePersist
     */
    #[ODM\PrePersist]
    public function doOtherStuffOnPrePersistToo(): void
    {
    }

    /**
     * @ODM\PostPersist
     */
    #[ODM\PostPersist]
    public function doStuffOnPostPersist(): void
    {
    }

    /**
     * @param ClassMetadata<AbstractMappingDriverUser> $metadata
     */
    public static function loadMetadata(ClassMetadata $metadata): void
    {
        $metadata->setInheritanceType(ClassMetadata::INHERITANCE_TYPE_NONE);
        $metadata->setCollection('cms_users');
        $metadata->addLifecycleCallback('doStuffOnPrePersist', 'prePersist');
        $metadata->addLifecycleCallback('doOtherStuffOnPrePersistToo', 'prePersist');
        $metadata->addLifecycleCallback('doStuffOnPostPersist', 'postPersist');
        $metadata->setDiscriminatorField(['fieldName' => 'discr']);
        $metadata->setDiscriminatorMap(['default' => self::class]);
        $metadata->setDefaultDiscriminatorValue('default');
        $metadata->mapField([
            'id' => true,
            'fieldName' => 'id',
        ]);
        $metadata->mapField([
            'fieldName' => 'version',
            'type' => 'int',
            'version' => true,
        ]);
        $metadata->mapField([
            'fieldName' => 'lock',
            'type' => 'int',
            'lock' => true,
        ]);
        $metadata->mapField([
            'fieldName' => 'name',
            'name' => 'username',
            'type' => 'string',
        ]);
        $metadata->mapField([
            'fieldName' => 'email',
            'type' => 'string',
        ]);
        $metadata->mapField([
            'fieldName' => 'mysqlProfileId',
            'type' => 'integer',
        ]);
        $metadata->mapOneReference([
            'fieldName' => 'address',
            'targetDocument' => Address::class,
            'cascade' => ['remove'],
        ]);
        $metadata->mapManyReference([
            'fieldName' => 'phonenumbers',
            'targetDocument' => Phonenumber::class,
            'collectionClass' => PhonenumberCollection::class,
            'cascade' => ['persist'],
            'discriminatorField' => 'discr',
            'discriminatorMap' => [
                'home' => HomePhonenumber::class,
                'work' => WorkPhonenumber::class,
            ],
            'defaultDiscriminatorValue' => 'home',
        ]);
        $metadata->mapManyReference([
            'fieldName' => 'morePhoneNumbers',
            'name' => 'more_phone_numbers',
            'targetDocument' => Phonenumber::class,
            'collectionClass' => PhonenumberCollection::class,
        ]);
        $metadata->mapManyReference([
            'fieldName' => 'groups',
            'targetDocument' => Group::class,
            'cascade' => [
                'remove',
                'persist',
                'refresh',
                'merge',
                'detach',
            ],
        ]);
        $metadata->mapOneEmbedded([
            'fieldName' => 'embeddedPhonenumber',
            'name' => 'embedded_phone_number',
        ]);
        $metadata->mapManyEmbedded([
            'fieldName' => 'otherPhonenumbers',
            'targetDocument' => Phonenumber::class,
            'discriminatorField' => 'discr',
            'discriminatorMap' => [
                'home' => HomePhonenumber::class,
                'work' => WorkPhonenumber::class,
            ],
            'defaultDiscriminatorValue' => 'home',
        ]);
        $metadata->addIndex(['username' => 'desc'], ['unique' => true]);
        $metadata->addIndex(['email' => 'desc'], ['unique' => true]);
        $metadata->addIndex(['mysqlProfileId' => 'desc'], ['unique' => true]);
        $metadata->addIndex(['createdAt' => 'asc'], ['expireAfterSeconds' => 3600]);
        $metadata->setShardKey(['name' => 'asc'], ['unique' => true, 'numInitialChunks' => 4096]);
    }
}

/**
 * @template-extends ArrayCollection<int, Phonenumber>
 */
class PhonenumberCollection extends ArrayCollection
{
}

class HomePhonenumber
{
}

class WorkPhonenumber
{
}

class Address
{
}

class Group
{
}

class Phonenumber
{
}

class InvalidMappingDocument
{
    /** @var string|null */
    public $id;
}

/**
 * @ODM\File(chunkSizeBytes=12345)
 */
#[ODM\File(chunkSizeBytes: 12345)]
class AbstractMappingDriverFile
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id]
    public $id;

    /**
     * @ODM\File\Length
     *
     * @var int|null
     */
    #[ODM\File\Length]
    public $size;

    /**
     * @ODM\File\ChunkSize
     *
     * @var int|null
     */
    #[ODM\File\ChunkSize]
    public $chunkSize;

    /**
     * @ODM\File\Filename
     *
     * @var string|null
     */
    #[ODM\File\Filename]
    public $name;

    /**
     * @ODM\File\Metadata(targetDocument=AbstractMappingDriverFileMetadata::class)
     *
     * @var AbstractMappingDriverFileMetadata|null
     */
    #[ODM\File\Metadata(targetDocument: AbstractMappingDriverFileMetadata::class)]
    public $metadata;

    /**
     * @ODM\File\UploadDate
     *
     * @var DateTime|null
     */
    #[ODM\File\UploadDate]
    public $uploadDate;
}

class AbstractMappingDriverFileMetadata
{
    /**
     * @ODM\Field
     *
     * @var string|null
     */
    #[ODM\Field]
    public $contentType;
}

/**
 * @ODM\File(repositoryClass=AbstractMappingDriverGridFSRepository::class)
 */
#[ODM\File(repositoryClass: AbstractMappingDriverGridFSRepository::class)]
class AbstractMappingDriverFileWithCustomRepository
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id]
    public $id;
}

/**
 * @template-extends DefaultGridFSRepository<AbstractMappingDriverFileWithCustomRepository>
 */
class AbstractMappingDriverGridFSRepository extends DefaultGridFSRepository
{
}

/** @ODM\MappedSuperclass */
#[ODM\MappedSuperclass]
class AbstractMappingDriverSuperClass
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id]
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|int|null
     */
    #[ODM\Field(type: 'string')]
    protected $override;
}

/**
 * @ODM\Document
 */
#[ODM\Document]
class AbstractMappingDriverDuplicateDatabaseName extends AbstractMappingDriverSuperClass
{
    /**
     * @ODM\Field(type="int")
     *
     * @var int|null
     */
    #[ODM\Field(type: 'int')]
    public $override;

    /**
     * @ODM\Field(type="string", name="baz")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'string', name: 'baz')]
    public $foo;

    /**
     * @ODM\Field(type="string", name="baz")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'string', name: 'baz')]
    public $bar;
}

/**
 * @ODM\Document
 */
#[ODM\Document]
class AbstractMappingDriverDuplicateDatabaseNameNotSaved extends AbstractMappingDriverSuperClass
{
    /**
     * @ODM\Field(type="int")
     *
     * @var int|null
     */
    #[ODM\Field(type: 'int')]
    public $override;

    /**
     * @ODM\Field(type="string", name="baz")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'int', name: 'baz')]
    public $foo;

    /**
     * @ODM\Field(type="string", name="baz", notSaved=true)
     *
     * @var string|null
     */
    #[ODM\Field(type: 'int', name: 'baz', notSaved: true)]
    public $bar;
}

/**
 * @ODM\View(rootClass=AbstractMappingDriverUser::class)
 */
#[ODM\View(rootClass: AbstractMappingDriverUser::class)]
class AbstractMappingDriverViewWithoutRepository
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id]
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'string')]
    public $name;
}

/**
 * @ODM\View(repositoryClass=DocumentRepository::class, rootClass=AbstractMappingDriverUser::class)
 */
#[ODM\View(repositoryClass: DocumentRepository::class, rootClass: AbstractMappingDriverUser::class)]
class AbstractMappingDriverViewWithWrongRepository
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id]
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'string')]
    public $name;
}

/**
 * @ODM\View(repositoryClass=AbstractMappingDriverViewRepository::class)
 */
#[ODM\View(repositoryClass: AbstractMappingDriverViewRepository::class)]
class AbstractMappingDriverViewWithoutRootClass
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id]
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'string')]
    public $name;
}

/**
 * @ODM\View(repositoryClass=AbstractMappingDriverViewRepository::class, rootClass="Doctrine\ODM\MongoDB\LolNo")
 */
#[ODM\View(repositoryClass: AbstractMappingDriverViewRepository::class, rootClass: 'Doctrine\ODM\MongoDB\LolNo')]
class AbstractMappingDriverViewWithNonExistingRootClass
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id]
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'string')]
    public $name;
}

/**
 * @ODM\View(
 *     repositoryClass=AbstractMappingDriverViewRepository::class,
 *     rootClass=AbstractMappingDriverUser::class,
 *     view="user_name",
 * )
 */
#[ODM\View(repositoryClass: AbstractMappingDriverViewRepository::class, rootClass: AbstractMappingDriverUser::class, view: 'user_name')]
class AbstractMappingDriverView
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    #[ODM\Id]
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    #[ODM\Field(type: 'string')]
    public $name;
}

/**
 * @template-extends DocumentRepository<AbstractMappingDriverViewWithoutRootClass>
 * @template-implements  ViewRepository<AbstractMappingDriverViewWithoutRootClass>
 */
class AbstractMappingDriverViewRepository extends DocumentRepository implements ViewRepository
{
    public function createViewAggregation(Builder $builder): void
    {
        $builder
            ->project()
                ->includeFields(['name']);
    }
}
