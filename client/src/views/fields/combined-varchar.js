/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/combined-varchar', 'views/fields/base', Dep => {

    return Dep.extend({

        _template: '',

        svelteComponent: null,

        init() {
            this.prepareOptionName();
            Dep.prototype.init.call(this);
        },

        prepareOptionName() {
            const fieldName = this.options.name || this.options.defs.name;
            this.options.name = this.getMetadata().get(['entityDefs', this.model.name, 'fields', fieldName, 'mainField']) || fieldName;
        },

        setup() {
            Dep.prototype.setup.call(this);
            this.prepareOriginalName();
            if (this.prefixEnabled) {
                this.prefixFieldName = this.originalName + 'PrefixId';
            }
        },

        prepareOriginalName() {
            this.originalName = this.name;
            this.prefixEnabled = this.params.prefixEnabled || this.model.getFieldParam(this.name, 'prefixEnabled');
            this.prefixWhere   = this.model.getFieldParam(this.name, 'where') ?? [];
            if (this.measureId || this.prefixEnabled) {
                this.name = 'combined' + this.originalName.charAt(0).toUpperCase() + this.originalName.slice(1);
            }
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);
        },

        getAttributeList() {
            let list = [this.originalName];
            if (this.measureId) {
                list.push(this.originalName + 'UnitId');
                list.push(this.originalName + 'UnitData');
            }
            if (this.prefixFieldName) {
                list.push(this.prefixFieldName);
            }
            return list;
        },

        validateRequired() {
            if (this.isRequired()) {
                if (!this.model.get(this.originalName)) {
                    const msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
            return false;
        },

        isInheritedField() {
            if (!['detail', 'edit'].includes(this.mode) || !this.model || !this.model.urlRoot || !this.isInheritableField()) {
                return false;
            }
            const inheritedFields = this.model.get('inheritedFields');
            return inheritedFields && Array.isArray(inheritedFields) && inheritedFields.includes(this.originalName);
        },

        getLockedFieldName() {
            return this.originalName;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const target = this.$el[0];
            if (!target) return;

            this.removeSvelteComponent();

            this.svelteComponent = new Svelte.CombinedVarcharField({
                target,
                props: {
                    name:                    this.originalName,
                    value:                   this.model.get(this.originalName) ?? null,
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
                this.model.set(name, value, { ui: true });
            });
        },

        removeSvelteComponent() {
            if (this.svelteComponent) {
                try { this.svelteComponent.$destroy(); } catch (e) {}
                this.svelteComponent = null;
            }
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
            model.set(this.originalName, res[this.originalName] ?? null);
            if (this.measureId) {
                model.set(this.originalName + 'UnitId', res[this.originalName + 'UnitId'] ?? null);
                model.set(this.originalName + 'UnitData', res[this.originalName + 'UnitData'] ?? null);
                model.set(this.originalName + 'AllUnits', res[this.originalName + 'AllUnits'] ?? null);
            }
            if (this.prefixFieldName) {
                model.set(this.prefixFieldName, res[this.prefixFieldName] ?? null);
            }
            Dep.prototype.onInlineEditSave.call(this, res, attrs, model);
        },

        remove(dontEmpty) {
            this.removeSvelteComponent();
            Dep.prototype.remove.call(this, dontEmpty);
        },

    });
});
