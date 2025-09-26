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

            $cell.on('mouseenter', e => {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }
                if (this.mode === 'detail') {
                    $link.removeClass('hidden');
                }
            }).on('mouseleave', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $link.addClass('hidden');
                }
            });
        },

        openEditLabelDialog() {
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            let scope = 'Translation',
                viewName = this.getMetadata().get(`clientDefs.${scope}.modalViews.edit`) || 'views/modals/edit',
                key = `${this.getEntityScope()}.${this.getCategory()}.${this.getEntityFieldName()}`;

            this.ajaxGetRequest(`${scope}?where[0][type]=textFilter&where[0][value]=${key}`).then(res => {
                let data = {id: null, code: key};
                res.list.forEach(v => {
                    if(v.code === key) {
                        data = v
                    }
                })
                this.getModelFactory().create(scope, model => {
                    model.set(data);

                    let options = {
                        scope: scope,
                        model: model,
                        id: data.id,
                        fullFormDisabled: this.getMetadata().get('clientDefs.' + scope + '.modalFullFormDisabled') || false,
                    };

                    this.createView('modal', viewName, options, view => {
                      this.modalRenderedCallback(view, key);
                    });
                });
            });
        },

        modalRenderedCallback(view, key) {
            Espo.Ui.notify(false);
            if (!view.model.get('code')) {
                view.model.set('code', key);
            }

            view.render();

            this.listenToOnce(view, 'remove', () => {
                this.clearView('modal');
            });

            this.listenToOnce(view, 'after:save', () => {
                this.model.fetch();
            });
        }

    });
});
