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

    redirectToSupertypeRecord(options, mode) {
        const redirect = supertypeId => {
            this.getRouter().navigate(`#${this.getSupertype()}/${mode}/${supertypeId}`, {trigger: true, replace: true});
        };

        if (options.model && options.model.get('supertypeId')) {
            redirect(options.model.get('supertypeId'));
            return;
        }

        Espo.Ajax.getRequest(`${this.name}/${options.id}`, {select: 'supertypeId'}).then(data => {
            redirect(data.supertypeId);
        });
    },

}));
