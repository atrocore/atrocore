/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

type ActionButtonParams = {
    name?: string;
    label?: string;
    action?: string;
    id?: string | number;
    size?: 'small' | 'regular' | 'large';
    style?: string;
    className?: string;
    hidden?: boolean;
    disabled?: boolean;
    tooltip?: string;
    html?: string;
    dropdown?: boolean;
};

export  default  ActionButtonParams;