/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/combined-int', ['views/fields/base', 'views/fields/combined-varchar'], (Dep, Varchar) => {

    return Dep.extend({

        _template: '',

        svelteComponent: null,

        init() {
            Varchar.prototype.prepareOptionName.call(this);
            Dep.prototype.init.call(this);
        },

        setup() {
            Dep.prototype.setup.call(this);
            this.prepareOriginalName();
            if (this.prefixEnabled) {
                this.prefixFieldName = this.originalName + 'PrefixId';
            }
        },

        prepareOriginalName() {
            Varchar.prototype.prepareOriginalName.call(this);
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);
        },

        getAttributeList() {
            return Varchar.prototype.getAttributeList.call(this);
        },

        validateRequired: function () {
            if (this.isRequired()) {
                let value = this.model.get(this.originalName);
                if (value === null || value === false || (this.measureId && !this.model.get(this.originalName + 'UnitId'))) {
                    let msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        isInheritedField() {
            return Varchar.prototype.isInheritedField.call(this);
        },

        getLockedFieldName() {
            return Varchar.prototype.getLockedFieldName.call(this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const target = this.$el[0];
            if (!target) return;

            this.removeSvelteComponent();

            const rawValue = this.model.get(this.originalName);
            const value = (rawValue === null || rawValue === undefined || isNaN(rawValue)) ? null : parseInt(rawValue, 10);

            this.svelteComponent = new Svelte.CombinedIntField({
                target,
                props: {
                    name:                    this.originalName,
                    value,
                    mode:                    this.mode,
                    prefixValueId: this.prefixEnabled ? (this.model.get(this.prefixFieldName) ?? null) : undefined,
                    prefixWhere:   this.prefixEnabled ? (this.prefixWhere ?? []) : undefined,
                    unitId:                  this.model.get(this.originalName + 'UnitId') ?? null,
                    measureId:               this.measureId ?? null,
                    entityName:              this.model.name ?? '',
                    entityId:                this.model.id ?? null,
                },
            });

            this.svelteComponent.$on('change', event => {
                const { name, value } = event.detail;
                this.model.set(name, value, {ui: true});
            });
        },

        removeSvelteComponent() {
            Varchar.prototype.removeSvelteComponent.call(this);
        },

        fetch() {
            if (this.svelteComponent) {
                return this.svelteComponent.fetch();
            }
            const result = { [this.originalName]: null };
            if (this.measureId) result[this.originalName + 'UnitId'] = null;
            if (this.prefixEnabled) result[this.prefixFieldName] = null;
            return result;
        },

        onInlineEditSave(res, attrs, model) {
            Varchar.prototype.onInlineEditSave.call(this, res, attrs, model);
        },

        remove(dontEmpty) {
            this.removeSvelteComponent();
            Dep.prototype.remove.call(this, dontEmpty);
        },

    });
});
