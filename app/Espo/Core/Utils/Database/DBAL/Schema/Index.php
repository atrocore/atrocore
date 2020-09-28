<?php

namespace Espo\Core\Utils\Database\DBAL\Schema;

use Doctrine\DBAL\Schema\Index as DBALIndex;

class Index extends \Doctrine\DBAL\Schema\Index
{
    public function addFlag($flag)
    {
        $this->_flags[strtolower($flag)] = true;

        return $this;
    }

    public function hasFlag($flag)
    {
        return isset($this->_flags[strtolower($flag)]);
    }

    public function removeFlag($flag)
    {
        unset($this->_flags[strtolower($flag)]);
    }

    public function isFullfilledBy(DBALIndex $other)
    {
        if (count($other->getColumns()) != count($this->getColumns())) {
            return false;
        }

        $sameColumns = $this->spansColumns($other->getColumns());

        if ($sameColumns) {
            $flags = $this->getFlags();
            $otherFlags = $other->getFlags();

            if ( ! $this->isUnique() && !$this->isPrimary() && $flags === $otherFlags) {
                return true;
            } else if ($other->isPrimary() != $this->isPrimary()) {
                return false;
            } else if ($other->isUnique() != $this->isUnique()) {
                return false;
            }

            if (count($flags) != count($otherFlags) || array_diff($flags, $otherFlags) !== array_diff($otherFlags, $flags)) {
                return false;
            }

            return true;
        }

        return false;
    }
}