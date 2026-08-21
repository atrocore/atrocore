<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Symfony Yaml 8.0 rejects a duplicate mapping key whose first occurrence is null, which earlier
 * versions accepted with the last occurrence winning. Dropping that first occurrence keeps the parsed
 * result identical, and a repair is only returned when parsing again proves it.
 */
class YamlDuplicateKeys
{
    private const array NULL_VALUES = ['', '~', 'null'];

    private array $indents = [];

    private array $keys = [];

    private array $values = [];

    private array $droppedKeys = [];

    public function find(string $yaml): array
    {
        return $this->keysOf($this->findCandidates($yaml));
    }

    public function remove(string $yaml): ?string
    {
        $this->droppedKeys = [];

        $candidates = $this->findCandidates($yaml);
        if ($candidates === []) {
            return null;
        }

        $lines = explode("\n", $yaml);
        $parsed = $this->parse($yaml);

        if ($parsed !== null) {
            $repaired = $this->withoutLines($lines, $candidates);

            if ($this->parse($repaired) !== $parsed) {
                return null;
            }

            $this->droppedKeys = $this->keysOf($candidates);

            return $repaired;
        }

        $removed = [];
        foreach ($candidates as $index) {
            $removed[] = $index;
            $repaired = $this->withoutLines($lines, $removed);

            if ($this->parse($repaired) !== null) {
                $this->droppedKeys = $this->keysOf($removed);

                return $repaired;
            }
        }

        return null;
    }

    public function getDroppedKeys(): array
    {
        return $this->droppedKeys;
    }

    private function findCandidates(string $yaml): array
    {
        $this->indexLines(explode("\n", $yaml));

        $candidates = [];
        foreach ($this->keys as $index => $key) {
            if (!in_array($this->values[$index], self::NULL_VALUES, true)) {
                continue;
            }

            if (!$this->hasNestedBlock($index) && $this->hasSiblingDuplicate($index)) {
                $candidates[] = $index;
            }
        }

        return $candidates;
    }

    private function indexLines(array $lines): void
    {
        $this->indents = [];
        $this->keys = [];
        $this->values = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            $this->indents[$index] = strlen($line) - strlen(ltrim($line, ' '));

            if (preg_match('/^ *([A-Za-z_][A-Za-z0-9_.-]*) *:(.*)$/', $line, $match) === 1) {
                $this->keys[$index] = $match[1];
                $this->values[$index] = strtolower(trim($match[2]));
            }
        }
    }

    private function hasSiblingDuplicate(int $index): bool
    {
        foreach ($this->indents as $candidate => $indent) {
            if ($candidate <= $index) {
                continue;
            }

            if ($indent < $this->indents[$index]) {
                return false;
            }

            if ($indent === $this->indents[$index] && ($this->keys[$candidate] ?? null) === $this->keys[$index]) {
                return true;
            }
        }

        return false;
    }

    private function hasNestedBlock(int $index): bool
    {
        foreach ($this->indents as $candidate => $indent) {
            if ($candidate > $index) {
                return $indent > $this->indents[$index];
            }
        }

        return false;
    }

    private function withoutLines(array $lines, array $indexes): string
    {
        return implode("\n", array_values(array_diff_key($lines, array_fill_keys($indexes, true))));
    }

    private function keysOf(array $indexes): array
    {
        return array_values(array_unique(array_map(fn(int $index): string => $this->keys[$index], $indexes)));
    }

    private function parse(string $yaml): ?array
    {
        try {
            $parsed = @Yaml::parse($yaml);
        } catch (ParseException) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }
}
