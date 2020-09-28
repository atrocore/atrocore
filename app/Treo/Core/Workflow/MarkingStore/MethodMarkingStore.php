<?php

declare(strict_types=1);

namespace Treo\Core\Workflow\MarkingStore;

use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

/**
 * Class MethodMarkingStore
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
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
    public function getMarking($subject)
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
