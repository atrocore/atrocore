/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

import type ActionButtonParams from '../types/action-button-params'

export const getComputedClasses = (params: ActionButtonParams, extraClassName: string = ''): string => {
    const classes = ['action'];
    if (params.size === 'small') classes.push('small');
    if (params.style) classes.push(...params.style.split(' '));
    if (extraClassName) classes.push(extraClassName);
    if (params.className) classes.push(params.className);
    return classes.filter(Boolean).join(' ');
};