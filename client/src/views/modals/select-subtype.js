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
 * Each record of an entity with subtypes belongs to one of them, so the subtype is chosen before the record is created.
 */
Espo.define('views/modals/select-subtype', 'views/modals/array-field-add', Dep => Dep.extend({

    template: 'modals/select-subtype',

    events: {
        'click button[data-action="add"]': function (e) {
            this.trigger('select', $(e.currentTarget).attr('data-value'));
        },
    },

    data() {
        const subtypes = this.getSubtypes();

        const translatedOptions = {};
        subtypes.forEach(subtype => {
            translatedOptions[subtype] = this.translate(subtype, 'scopeNames');
        });

        return {
            optionList: subtypes,
            translatedOptions: translatedOptions
        };
    },

    setup() {
        Dep.prototype.setup.call(this);

        this.header = this.translate('selectType', 'labels', 'Global');
    },

    getSubtypes() {
        const scopes = this.getMetadata().get('scopes') || {};

        return Object.keys(scopes)
            .filter(scope => scopes[scope].type === 'Subtype' && scopes[scope].supertypeId === this.options.scope)
            .filter(scope => this.getAcl().check(scope, 'create'))
            .sort((a, b) => this.translate(a, 'scopeNames').localeCompare(this.translate(b, 'scopeNames')));
    },

}));
