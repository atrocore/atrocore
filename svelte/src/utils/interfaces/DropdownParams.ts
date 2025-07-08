/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

export default interface DropdownParams {
    disableAutoHide?: boolean;
    dropdownListElSelector?: string;
    placement?: string;
    strategy?: string;
    onDropdownShow?: (dropdown: HTMLElement) => void;
    onDropdownHide?: (dropdown: HTMLElement) => void;
    flip?: boolean|Record<string, any>,
    shift?: boolean|Record<string, any>,
    offset?: boolean|Record<string, any>,
    size?: boolean|Record<string, any>,
    arrow?: boolean|Record<string, any>,
    hide?: boolean|Record<string, any>
}