<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Espo\Core\Workflow\MarkingStore;

use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

/**
 * Class MethodMarkingStore
 */
final class MethodMarkingStore implements MarkingStoreInterface
{
    /**
     * @var bool
     */
    private $singleState;

    /**
     * @var string
     */
    private $property;

    /**
     * MethodMarkingStore constructor.
     *
     * @param bool   $singleState
     * @param string $property
     */
    public function __construct(bool $singleState = false, string $property = 'marking')
    {
        $this->singleState = $singleState;
        $this->property = $property;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarking(object $subject): Marking
    {
        $marking = $subject->get($this->property);

        if (!$marking) {
            return new Marking();
        }

        if ($this->singleState) {
            $marking = [$marking => 1];
        }

        return new Marking($marking);
    }

    /**
     * {@inheritdoc}
     */
    public function setMarking($subject, Marking $marking, array $context = [])
    {
        $marking = $marking->getPlaces();

        if ($this->singleState) {
            $marking = key($marking);
        }

        $subject->set($this->property, $marking, $context);
    }
}
