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

namespace Atro\DTOs;

/**
 * Result of the execution of an action.
 *
 * - executed:     the action was allowed and really ran (false: conditions not met, feed is inactive, nothing to do)
 * - inBackground: true  = the work was handed over to background job(s), the caller has to wait for them
 *                 false = the work is finished
 *                 null  = unknown, the action type only reports a bool (legacy action type)
 * - success:      the action finished without an error (for background work: the jobs were created)
 */
final readonly class ActionResultDTO
{
    public function __construct(
        public bool $executed,
        public ?bool $inBackground,
        public bool $success,
        public ?string $message = null
    ) {
    }

    /**
     * The action ran and is finished.
     */
    public static function done(bool $success = true, ?string $message = null): self
    {
        return new self(true, false, $success, $message);
    }

    /**
     * The action ran and handed its work over to background job(s).
     */
    public static function queued(?string $message = null): self
    {
        return new self(true, true, true, $message);
    }

    /**
     * The action did not run (conditions not met, feed is inactive, nothing to do).
     */
    public static function skipped(?string $message = null): self
    {
        return new self(false, false, false, $message);
    }

    public static function failed(?string $message = null, bool $executed = true): self
    {
        return new self($executed, false, false, $message);
    }

    /**
     * Wraps the bool returned by an action type that does not report a rich result.
     */
    public static function fromLegacy(bool $result): self
    {
        return new self($result, null, $result);
    }

    /**
     * The meaning of the bool the actions returned before the result object existed.
     */
    public function toBool(): bool
    {
        return $this->executed && $this->success;
    }

    public function toArray(): array
    {
        return [
            'executed'     => $this->executed,
            'inBackground' => $this->inBackground,
            'success'      => $this->success,
            'message'      => $this->message,
        ];
    }
}
