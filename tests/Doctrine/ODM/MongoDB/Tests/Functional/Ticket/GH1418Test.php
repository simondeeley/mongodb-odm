<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Query\Query;
use Doctrine\ODM\MongoDB\Tests\BaseTest;

use function assert;

class GH1418Test extends BaseTest
{
    public function testManualHydrateAndMerge(): void
    {
        $document = new GH1418Document();
        $this->dm->getHydratorFactory()->hydrate($document, [
            '_id' => 1,
            'name' => 'maciej',
            'embedOne' => ['name' => 'maciej', 'sourceId' => 1],
            'embedMany' => [
                ['name' => 'maciej', 'sourceId' => 2],
            ],
        ], [Query::HINT_READ_ONLY => true]);

        $this->assertEquals(1, $document->embedOne->id);
        $this->assertEquals(2, $document->embedMany->first()->id);

        $this->dm->merge($document);
        $this->dm->flush();
        $this->dm->clear();

        $document = $this->dm->getRepository(GH1418Document::class)->find(1);
        $this->assertEquals(1, $document->id);
        $this->assertEquals('maciej', $document->embedOne->name);
        $this->assertEquals(1, $document->embedOne->id);
        $this->assertEquals(1, $document->embedMany->count());
        $this->assertEquals('maciej', $document->embedMany->first()->name);
        $this->assertEquals(2, $document->embedMany->first()->id);
    }

    public function testReadDocumentAndManage(): void
    {
        $document     = new GH1418Document();
        $document->id = 1;

        $embedded       = new GH1418Embedded();
        $embedded->id   = 1;
        $embedded->name = 'maciej';

        $document->embedOne    = clone $embedded;
        $document->embedMany[] = clone $embedded;

        $this->dm->persist($document);
        $this->dm->flush();
        $this->dm->clear();

        $document = $this->dm->createQueryBuilder(GH1418Document::class)
            ->readOnly(true)
            ->field('id')
            ->equals(1)
            ->getQuery()
            ->getSingleResult();
        assert($document instanceof GH1418Document);

        $this->assertEquals(1, $document->id);
        $this->assertEquals('maciej', $document->embedOne->name);
        $this->assertEquals(1, $document->embedOne->id);
        $this->assertEquals(1, $document->embedMany->count());
        $this->assertEquals('maciej', $document->embedMany->first()->name);
        $this->assertEquals(1, $document->embedMany->first()->id);

        $document = $this->dm->merge($document);

        $document->embedOne->name     = 'alcaeus';
        $document->embedMany[0]->name = 'alcaeus';

        $this->dm->flush();
        $this->dm->clear();

        $document = $this->dm->getRepository(GH1418Document::class)->find(1);
        $this->assertEquals(1, $document->id);
        $this->assertEquals('alcaeus', $document->embedOne->name);
        $this->assertEquals(1, $document->embedOne->id);
        $this->assertEquals(1, $document->embedMany->count());
        $this->assertEquals('alcaeus', $document->embedMany->first()->name);
        $this->assertEquals(1, $document->embedMany->first()->id);

        $document->embedMany[] = clone $embedded;

        $document = $this->dm->merge($document);
        $this->dm->flush();
        $this->dm->clear();

        $document = $this->dm->getRepository(GH1418Document::class)->find(1);
        $this->assertEquals(1, $document->id);
        $this->assertEquals('alcaeus', $document->embedOne->name);
        $this->assertEquals(2, $document->embedMany->count());
        $this->assertEquals('maciej', $document->embedMany->last()->name);
    }
}

/** @ODM\Document */
class GH1418Document
{
    /**
     * @ODM\Id(strategy="none")
     *
     * @var int|null
     */
    public $id;

    /**
     * @ODM\EmbedOne(targetDocument=GH1418Embedded::class)
     *
     * @var GH1418Embedded|null
     */
    public $embedOne;

    /**
     * @ODM\EmbedMany(targetDocument=GH1418Embedded::class)
     *
     * @var Collection<int, GH1418Embedded>
     */
    public $embedMany;
}

/** @ODM\EmbeddedDocument */
class GH1418Embedded
{
    /**
     * @ODM\Id(strategy="none", type="int")
     * @ODM\AlsoLoad("sourceId")
     *
     * @var int|null
     */
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    public $name;
}
