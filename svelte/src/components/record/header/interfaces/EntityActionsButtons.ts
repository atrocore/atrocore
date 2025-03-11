/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

import ActionParams from "./ActionParams";

export default interface EntityActionButtons {
    buttons: ActionParams[];
    dropdownButtons: ActionParams[];
    executeAction: (action: string, data?: Map<string, any>, event?: Event) => void;
}