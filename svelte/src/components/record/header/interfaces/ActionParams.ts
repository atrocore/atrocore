/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

export default interface ActionParams {
    id?: string;
    name?: string;
    action: string;
    data?: Record<string, any>;
    display?: string;
    html?: string;
    label?: string;
    type?: string;
    style?: string;
    size?: 'small' | 'regular'
    tooltip?: string;
    callback?: (e: Event) => void;
    hidden?: boolean;
    disabled?: boolean;
    active?: boolean;
}