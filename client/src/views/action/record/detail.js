/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/record/detail', ['views/record/detail', 'views/action/record/panels/entity-filter-result'],
    (Dep, EntityFilter) => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalButtons = [{
                "action": "execute",
                "label": this.translate('execute', 'labels', 'Action')
            }];

            this.listenTo(this.model, 'after:save', () => {
                this.handleButtonsDisability();
            });

            let filterButton = {
                tooltip: this.translate('openSearchFilter'),
                action: 'openSearchFilter',
                name: 'filterButton',
                html: EntityFilter.prototype.getFilterButtonHtml.call(this)
            }

            if(this.buttonVisible()) {
                this.additionalButtons.push(filterButton);
            }

            this.listenTo(this.model, 'sync after:save', () => {
                filterButton.html = EntityFilter.prototype.getFilterButtonHtml.call(this);
                this.additionalButtons = this.additionalButtons.filter(b => b.name !== filterButton.name);
                if(this.buttonVisible()) {
                    this.additionalButtons.push(filterButton);
                }

                this.reRender();
            });

            this.listenTo(this.model, 'change:targetEntity', () => {
                let scope = this.model.get('targetEntity');

                let data = {};
                if (this.model.get('data')) {
                    data = this.model.get('data');
                }
                if (typeof data.whereScope === 'undefined' || data.whereScope !== scope) {
                    data = _.extend(data, {
                        where: null,
                        whereData: null,
                        whereScope: scope,
                    });
                    this.model.set('data', data);
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.handleButtonsDisability();
        },

        isButtonsDisabled() {
            return !this.model.get('isActive');
        },

        handleButtonsDisability() {
            if (this.isButtonsDisabled()) {
                $('.additional-button').addClass('disabled');
            } else {
                $('.additional-button').removeClass('disabled');
            }
        },

        actionExecute() {
            if (this.isButtonsDisabled()) {
                return;
            }

            this.confirm(this.translate('executeNow', 'messages', 'Action'), () => {
                this.notify('Please wait...');
                this.ajaxPostRequest('Action/action/executeNow', {actionId: this.model.get('id')}).success(response => {
                    if (response.inBackground) {
                        this.notify(this.translate('jobAdded', 'messages'), 'success');
                    } else {
                        if (response.success) {
                            this.notify(response.message, 'success');
                        } else {
                            this.notify(response.message, 'error');
                        }
                    }
                });
            });
        },

        actionOpenSearchFilter() {
            EntityFilter.prototype.actionOpenSearchFilter.call(this);
        },

        buttonVisible() {
            return this.model.get('targetEntity')
                && this.getAllowedActionTypes().includes(this.model.get('type'))
                && !this.model.get('applyToPreselectedRecords');
        },

        getAllowedActionTypes() {
            return ['update', 'delete', 'email'];
        }
    })
);