<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Tests\BaseTest;

use function get_class;

class GH977Test extends BaseTest
{
    public function testAutoRecompute(): void
    {
        $d         = new GH977TestDocument();
        $d->value1 = 'Value 1';
        $this->dm->persist($d);
        $this->dm->flush();
        $this->dm->clear();

        $d         = $this->dm->getRepository(get_class($d))->findOneBy(['value1' => 'Value 1']);
        $d->value1 = 'Changed';
        $this->uow->computeChangeSet($this->dm->getClassMetadata(get_class($d)), $d);
        $changeSet = $this->uow->getDocumentChangeSet($d);
        if (isset($changeSet['value1'])) {
            $d->value2 = 'v1 has changed';
        }

        $this->dm->flush();
        $this->dm->clear();

        $d = $this->dm->getRepository(get_class($d))->findOneBy(['value1' => 'Changed']);
        $this->assertNotNull($d);
        $this->assertEquals('v1 has changed', $d->value2);
    }

    public function testRefreshClearsChangeSet(): void
    {
        $d         = new GH977TestDocument();
        $d->value1 = 'Value 1';
        $this->dm->persist($d);
        $this->dm->flush();
        $this->dm->clear();

        $d         = $this->dm->getRepository(get_class($d))->findOneBy(['value1' => 'Value 1']);
        $d->value1 = 'Changed';
        $this->uow->computeChangeSet($this->dm->getClassMetadata(get_class($d)), $d);
        $this->dm->refresh($d);
        $this->dm->flush();
        $this->dm->clear();

        $d = $this->dm->getRepository(get_class($d))->findOneBy(['value1' => 'Value 1']);
        $this->assertNotNull($d);
    }
}

/** @ODM\Document */
class GH977TestDocument
{
    /**
     * @ODM\Id
     *
     * @var string|null
     */
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    public $value1;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    public $value2;
}
