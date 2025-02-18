<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Tests\BaseTest;
use Documents\Functional\Ticket\GH683\EmbeddedSubDocument1;
use Documents\Functional\Ticket\GH683\EmbeddedSubDocument2;
use Documents\Functional\Ticket\GH683\ParentDocument;

use function get_class;

class GH683Test extends BaseTest
{
    public function testEmbedOne(): void
    {
        $parent       = new ParentDocument();
        $parent->name = 'Parent';

        $sub1       = new EmbeddedSubDocument1();
        $sub1->name = 'Sub 1';

        $parent->embedOne = $sub1;

        $this->dm->persist($parent);
        $this->dm->flush();
        $this->dm->clear();

        $id = $parent->id;

        $parent = $this->dm->find(get_class($parent), $id);
        $this->assertInstanceOf(get_class($sub1), $parent->embedOne);
    }

    public function testEmbedMany(): void
    {
        $parent       = new ParentDocument();
        $parent->name = 'Parent';

        $sub1       = new EmbeddedSubDocument1();
        $sub1->name = 'Sub 1';

        $sub2       = new EmbeddedSubDocument2();
        $sub2->name = 'Sub 2';

        $parent->embedMany = new ArrayCollection();
        $parent->embedMany->add($sub1);
        $parent->embedMany->add($sub2);

        $this->dm->persist($parent);
        $this->dm->flush();
        $this->dm->clear();

        $id = $parent->id;

        $parent    = $this->dm->find(get_class($parent), $id);
        $firstSub  = $parent->embedMany->get(0);
        $secondSub = $parent->embedMany->get(1);
        $this->assertInstanceOf(get_class($sub1), $firstSub);
        $this->assertInstanceOf(get_class($sub2), $secondSub);
    }
}
