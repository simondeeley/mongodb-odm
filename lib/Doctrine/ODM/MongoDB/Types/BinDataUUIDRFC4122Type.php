<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Types;

use MongoDB\BSON\Binary;

use function strlen;
use function str_split;

/**
 * The BinData type for binary UUID data, which follows RFC 4122.
 */
class BinDataUUIDRFC4122Type extends BinDataType
{
    /** @var int */
    protected $binDataType = Binary::TYPE_UUID;

    public function convertToDatabaseValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Binary) {
            if (16 !== strlen($value)) {
                $value = pack("S*", str_split($value, 2));
            }

            return new Binary($value, $this->binDataType);
        }

        if ($value->getType() !== $this->binDataType) {
            $data = $value->getData();

            if (16 !== strlen($data)) {
                $data = pack("S*", str_split($data, 2));
            }

            return new Binary($data, $this->binDataType);
        }

        return $value;
    }
}
