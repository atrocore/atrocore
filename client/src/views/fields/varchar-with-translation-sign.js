/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/varchar-with-translation-sign', 'views/fields/varchar', Dep => {

    return Dep.extend({

        getEntityScope() {
            return this.scope;
        },

        getCategory() {
            return 'fields';
        },

        getEntityFieldName() {
            return this.name;
        },

        initInlineActions() {
            Dep.prototype.initInlineActions.call(this);

            this.listenTo(this, 'after:render', this.initInlineLabelEdit, this);
        },

        getIconTitle() {
            return '';
        },

        initInlineLabelEdit() {
            if (this.getAllUiLanguages().length < 2) {
                return;
            }

            let $cell = this.getCellElement();

            this.getInlineActionsContainer().find('.ph-globe').parent().remove();
            let $link = $(`<a href="javascript:" class="pull-right inline-label-edit-link hidden" title="${this.getIconTitle()}"><i class="ph ph-globe"></i></a>`);

            if ($cell.size() === 0) {
                this.listenToOnce(this, 'after:render', this.initInlineLabelEdit, this);
                return;
            }

            this.getInlineActionsContainer().append($link);

            $link.on('click', () => {
                this.openEditLabelDialog();
            });

            if (this.mode === 'detail') {
                $cell.on('mouseenter', e => {
                    e.stopPropagation();
                    if (this.disabled || this.readOnly) {
                        return;
                    }

                    $link.removeClass('hidden');
                }).on('mouseleave', e => {
                    e.stopPropagation();

                    $link.addClass('hidden');
                });
            }

            if (this.mode === 'edit') {
                $link.removeClass('hidden');
            }
        },

        openEditLabelDialog() {
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            let scope = 'Translation',
                viewName = this.getMetadata().get(`clientDefs.${scope}.modalViews.edit`) || 'views/modals/edit',
                key = `${this.getEntityScope()}.${this.getCategory()}.${this.getEntityFieldName()}`;

            this.ajaxGetRequest(`${scope}?where[0][type]=equals&where[0][field]=code&where[0][value]=${encodeURIComponent(key)}`).then(res => {
                let data = res.list[0] ?? { id: null, code: key, module: 'custom' };
                this.getModelFactory().create(scope, model => {
                    model.set(data);

                    let options = {
                        scope: scope,
                        model: model,
                        id: data.id,
                        fullFormDisabled: this.getMetadata().get('clientDefs.' + scope + '.modalFullFormDisabled') || false,
                    };

                    this.createView('modal', viewName, options, view => {
                        this.modalRenderedCallback(view, data);
                    });
                });
            });
        },

        modalRenderedCallback(view, data) {
            Espo.Ui.notify(false);
            for (let key of ['code', 'module']) {
                if (!view.model.get(key)) {
                    view.model.set(key, data[key]);
                }
            }

            view.render();

            this.listenToOnce(view, 'remove', () => {
                this.clearView('modal');
            });

            this.listenToOnce(view, 'after:save', () => {
                this.model.fetch();
            });
        },

        getAllUiLanguages() {
            let languages = [];

            $.each((this.getConfig().get('referenceData')?.Locale || {}), (code) => {
                languages.push(code);
            });

            $.each((this.getConfig().get('referenceData')?.Language || {}), (code) => {
                if (!languages.includes(code)) {
                    languages.push(code);
                }
            });

            return languages;
        }
    });
});
