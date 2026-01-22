/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

import { getComputedClasses } from "$lib/components/buttons/ActionButton/utils/action-button";
import DropdownActionParams from "../types/params";

export const getToggleClasses = (params: DropdownActionParams, className: string): string => {
    return `${getComputedClasses(params, className)} dropdown-toggle`.trim();
};