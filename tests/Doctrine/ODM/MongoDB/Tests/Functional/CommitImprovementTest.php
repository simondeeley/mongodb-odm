<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional;

use Doctrine\Common\EventSubscriber;
use Doctrine\ODM\MongoDB\APM\CommandLogger;
use Doctrine\ODM\MongoDB\Events;
use Doctrine\ODM\MongoDB\LockException;
use Doctrine\ODM\MongoDB\PersistentCollection\PersistentCollectionInterface;
use Doctrine\ODM\MongoDB\Tests\BaseTest;
use Documents\Phonebook;
use Documents\Phonenumber;
use Documents\User;
use Documents\VersionedUser;

use function get_class;

class CommitImprovementTest extends BaseTest
{
    /** @var CommandLogger */
    private $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = new CommandLogger();
        $this->logger->register();
    }

    public function tearDown(): void
    {
        $this->logger->unregister();

        parent::tearDown();
    }

    public function testInsertIncludesAllNestedCollections(): void
    {
        $user = new User();
        $user->setUsername('malarzm');
        $privateBook = new Phonebook('Private');
        $privateBook->addPhonenumber(new Phonenumber('12345678'));
        $user->addPhonebook($privateBook);
        $this->dm->persist($user);
        $this->dm->flush();
        $this->assertCount(1, $this->logger, 'Inserting a document includes all nested collections and requires one query');
        $this->dm->clear();

        $user = $this->dm->find(get_class($user), $user->getId());
        $this->assertEquals('malarzm', $user->getUsername());
        $this->assertCount(1, $user->getPhonebooks());
        $this->assertEquals('Private', $user->getPhonebooks()->first()->getTitle());
        $this->assertCount(1, $user->getPhonebooks()->first()->getPhonenumbers());
        $this->assertEquals('12345678', $user->getPhonebooks()->first()->getPhonenumbers()->first()->getPhonenumber());
    }

    public function testCollectionsAreUpdatedJustAfterOwningDocument(): void
    {
        $user = new VersionedUser();
        $user->setUsername('malarzm');
        $privateBook = new Phonebook('Private');
        $privateBook->addPhonenumber(new Phonenumber('12345678'));
        $user->addPhonebook($privateBook);
        $troll = new VersionedUser();
        $troll->setUsername('Troll');
        $this->dm->persist($user);
        $this->dm->persist($troll);
        $this->dm->flush();

        $privateBook->addPhonenumber(new Phonenumber('87654321'));
        $troll->setUsername('Trollsky');
        $troll->setVersion(3);
        try {
            $this->dm->flush();
        } catch (LockException $ex) {
        }

        $this->dm->clear();
        $user         = $this->dm->find(get_class($user), $user->getId());
        $phonenumbers = $user->getPhonebooks()->first()->getPhonenumbers();
        $this->assertCount(2, $phonenumbers);
        $this->assertEquals('12345678', $phonenumbers[0]->getPhonenumber());
        $this->assertEquals('87654321', $phonenumbers[1]->getPhonenumber());
    }

    /**
     * This test checks few things:
     *  - if collections were updated after post* events, our changes would be saved
     *  - if collection snapshot would be taken after post* events, collection
     *    wouldn't be dirty and wouldn't be updated in next flush
     */
    public function testChangingCollectionInPostEventsHasNoIllEffects(): void
    {
        $this->dm->getEventManager()->addEventSubscriber(new PhonenumberMachine());

        $user = new VersionedUser();
        $user->setUsername('malarzm');
        $this->dm->persist($user);
        $this->dm->flush();

        $phoneNumbers = $user->getPhonenumbers();
        $this->assertCount(1, $phoneNumbers); // so we got a number on postPersist
        $this->assertInstanceOf(PersistentCollectionInterface::class, $phoneNumbers); // so we got a number on postPersist
        $this->assertTrue($phoneNumbers->isDirty()); // but they should be dirty

        $collection = $this->dm->getDocumentCollection(get_class($user));
        $inDb       = $collection->findOne();
        $this->assertArrayNotHasKey('phonenumbers', $inDb, 'Collection modified in postPersist should not be in database without recomputing change set');

        $this->dm->flush();

        $phoneNumbers = $user->getPhonenumbers();
        $this->assertInstanceOf(PersistentCollectionInterface::class, $phoneNumbers);
        $this->assertCount(2, $phoneNumbers); // so we got a number on postUpdate
        $this->assertTrue($phoneNumbers->isDirty()); // but they should be dirty

        $inDb = $collection->findOne();
        $this->assertCount(1, $inDb['phonenumbers'], 'Collection changes from postUpdate should not be in database');
    }

    public function testSchedulingCollectionDeletionAfterSchedulingForUpdate(): void
    {
        $user = new User();
        $user->addPhonenumber(new Phonenumber('12345678'));
        $this->dm->persist($user);
        $this->dm->flush();

        $user->addPhonenumber(new Phonenumber('87654321'));
        $this->uow->computeChangeSet($this->dm->getClassMetadata(get_class($user)), $user);
        $this->assertTrue($this->uow->isCollectionScheduledForUpdate($user->getPhonenumbers()));
        $this->assertFalse($this->uow->isCollectionScheduledForDeletion($user->getPhonenumbers()));

        $user->getPhonenumbers()->clear();
        $this->uow->computeChangeSet($this->dm->getClassMetadata(get_class($user)), $user);
        $this->assertFalse($this->uow->isCollectionScheduledForUpdate($user->getPhonenumbers()));
        $this->assertTrue($this->uow->isCollectionScheduledForDeletion($user->getPhonenumbers()));
        $this->logger->clear();
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find(get_class($user), $user->getId());
        $this->assertCount(0, $user->getPhonenumbers());
    }
}

class PhonenumberMachine implements EventSubscriber
{
    /** @var string[] */
    private $numbers = ['12345678', '87654321'];

    /** @var int */
    private $numberId = 0;

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function __call(string $eventName, array $args): void
    {
        $document = $args[0]->getDocument();
        if (! ($document instanceof User)) {
            return;
        }

        // hey I just met you, and this is crazy!
        $document->addPhonenumber(new Phonenumber($this->numbers[$this->numberId++]));
        // and call me maybe ;)

        // recomputing change set in postPersist will schedule document for update
        // which would be handled in same commit(), we're not checking for this
        if ($eventName !== Events::postUpdate) {
            return;
        }

        // prove that even this won't break our flow
        $dm = $args[0]->getDocumentManager();
        $dm->getUnitOfWork()->recomputeSingleDocumentChangeSet(
            $dm->getClassMetadata(get_class($document)),
            $document
        );
    }
}
