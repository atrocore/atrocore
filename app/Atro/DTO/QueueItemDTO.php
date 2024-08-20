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

namespace Atro\DTO;

class QueueItemDTO
{
    protected string $name;
    protected string $serviceName;
    protected array $data;
    protected string $priority = 'Normal';
    protected string $hash = '';
    protected ?string $parentId = null;
    protected ?string $startFrom = null;

    public function __construct(string $name, string $serviceName, array $data = [])
    {
        $this->name = $name;
        $this->serviceName = $serviceName;
        $this->data = $data;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): void
    {
        $this->priority = $priority;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function setStartFrom(\DateTime $dateTime): void
    {
        $this->startFrom = $dateTime->format('Y-m-d H:i:s');
    }

    public function getStartFrom(): ?string
    {
        return $this->startFrom;
    }
}