/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

import BreadcrumbsItem from "./BreadcrumbsItem";
import Permissions from "./Permissions";

export default interface Params {
    mode: string;
    scope: string;
    id: string;
    breadcrumbs: BreadcrumbsItem[],
    currentIsHeading: boolean;
    afterOnMount?: Function,
    scopePermissions?: Permissions
}