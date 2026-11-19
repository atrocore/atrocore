/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

/**
 * On UI a subtype record is shown only via its supertype record, so the urls of the subtype lead to the supertype.
 */
Espo.define('controllers/subtype', 'controllers/record', Dep => Dep.extend({

    getSupertype() {
        return this.getMetadata().get(['scopes', this.name, 'supertypeId']);
    },

    list() {
        this.getRouter().navigate('#' + this.getSupertype(), {trigger: true, replace: true});
    },

    kanban() {
        this.list();
    },

    view(options) {
        this.redirectToSupertypeRecord(options, 'view');
    },

    edit(options) {
        this.redirectToSupertypeRecord(options, 'edit');
    },

    /**
     * A subtype record has the id of its supertype record.
     */
    redirectToSupertypeRecord(options, mode) {
        this.getRouter().navigate(`#${this.getSupertype()}/${mode}/${options.id}`, {trigger: true, replace: true});
    },

}));
