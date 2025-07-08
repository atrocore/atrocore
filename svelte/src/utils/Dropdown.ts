/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

import DropdownParams from "./interfaces/DropdownParams";

export default class Dropdown {
    referenceEl: HTMLElement;
    floatingEl: HTMLElement;
    params?: DropdownParams

    constructor(referenceEl: HTMLElement, floatingEl: HTMLElement, params?: DropdownParams) {
        this.referenceEl = referenceEl;
        this.floatingEl = floatingEl;
        this.params = params;
    }
}