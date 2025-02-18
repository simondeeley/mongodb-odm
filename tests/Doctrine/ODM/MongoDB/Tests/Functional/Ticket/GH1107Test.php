<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Tests\BaseTest;

class GH1107Test extends BaseTest
{
    public function testOverrideIdStrategy(): void
    {
        $childObj       = new GH1107ChildClass();
        $childObj->name = 'ChildObject';
        $this->dm->persist($childObj);
        $this->dm->flush();
        $this->assertNotNull($childObj->id);
    }
}

/**
 * @ODM\Document
 * @ODM\InheritanceType("SINGLE_COLLECTION")
 */
class GH1107ParentClass
{
    /**
     * @ODM\Id(strategy="NONE")
     *
     * @var string|null
     */
    public $id;

    /**
     * @ODM\Field(type="string")
     *
     * @var string|null
     */
    public $name;
}

/** @ODM\Document */
class GH1107ChildClass extends GH1107ParentClass
{
    /**
     * @ODM\Id(strategy="AUTO")
     *
     * @var string|null
     */
    public $id;
}
