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
 * Selects fields of a subtype to show on its supertype - for the columns of a layout, or for the filters of a list,
 * where one field is selected at a time. Sibling subtypes may have fields with the same name, so the subtype is chosen
 * first, then its fields. A field is named on the supertype after its subtype: "<Subtype>_<field>".
 */
Espo.define('views/modals/select-subtype-field', 'view', Dep => Dep.extend({

    _template: '',

    svelteComponent: null,

    fieldsSelected: false,

    setup() {
        Dep.prototype.setup.call(this);

        this.once('remove', this.destroyComponent, this);
        this.listenToOnce(this.getRouter(), 'routed', this.close);
    },

    afterRender() {
        Dep.prototype.afterRender.call(this);

        this.svelteComponent = new Svelte.SubtypeSelectModal({
            target: document.body,
            props: {
                scope: this.options.entityName,
                aclAction: 'read',
                onSelect: this.selectSubtype.bind(this),
                onClose: this.close.bind(this)
            }
        });
    },

    selectSubtype(subtype) {
        this.destroyComponent();

        this.notify('Loading...');
        this.createView('dialog', 'views/modals/select-records', {
            scope: 'EntityField',
            multiple: this.isLayout(),
            createButton: false,
            massRelateEnabled: false,
            allowSelectAllResult: false,
            boolFilterList: ['subtypeFieldsShownOnSupertype'],
            boolFilterData: {
                subtypeFieldsShownOnSupertype: subtype
            }
        }, this.onFieldsDialogCreated.bind(this, subtype));
    },

    onFieldsDialogCreated(subtype, dialog) {
        dialog.render();
        this.notify(false);
        this.listenToOnce(dialog, 'select', this.selectFields.bind(this, subtype));
        this.listenToOnce(dialog, 'close', this.onFieldsDialogClosed);
    },

    onFieldsDialogClosed() {
        if (!this.fieldsSelected) {
            this.close();
        }
    },

    /**
     * Gives the selected fields as columns of the layout or as a filter, named after the subtype.
     */
    selectFields(subtype, models) {
        this.fieldsSelected = true;

        const items = [];
        // a single selection gives the model itself
        for (const model of Array.isArray(models) ? models : [models]) {
            const name = `${subtype}_${model.get('code')}`;
            const item = {id: name, name: name, label: this.translate(name, 'fields', this.options.entityName)};
            if (this.isLayout()) {
                item.fieldDefs = {name: name, requestData: {subtypeFields: name}};
            }
            items.push(item);
        }

        this.trigger('add', items);
        this.remove();
    },

    isLayout() {
        return !!this.options.layoutType;
    },

    destroyComponent() {
        if (this.svelteComponent) {
            this.svelteComponent.$destroy();
            this.svelteComponent = null;
        }
    },

    close() {
        this.destroyComponent();
        this.trigger('close');
        this.remove();
    },

}));
