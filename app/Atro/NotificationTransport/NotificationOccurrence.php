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

namespace Atro\NotificationTransport;

abstract class NotificationOccurrence
{
const CREATION = "creation";

const UPDATING = "updating";

const LINKING = "linking";

const UNLINKING  = "unlinking";

const DELETION = "deletion";

const NOTE_CREATION = "note_created";

const NOTE_UPDATED = "note_updated";

const NOTE_DELETED = "note_deleted";

const OWNERSHIP_ASSIGNMENT = "ownership_assignment";

const UNLIKING_OWNERSHIP_ASSIGNMENT = "unlinking_ownership_assignment";

const MENTION ="mentioned";
}