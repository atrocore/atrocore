/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/modals/compare', 'views/modal', function (Modal) {

    return Modal.extend({

        cssName: 'quick-compare',

        header: false,

        template: 'modals/compare',

        size: '',

        backdrop: true,
        recordView: 'views/record/compare',
        buttonList:[],
        buttons:[],

        setup: function () {
            this.model = this.options.model;
            this.scope = this.model.urlRoot;
            this.header = this.getLanguage().translate('Compare')+' '+this.scope+' '+this.model.get('name')
            Modal.prototype.setup.call(this)
            this.buttonList.push({
                name: 'fullView',
                label: 'Full View'
            });
            this.setupRecord()
        },

        setupRecord() {
            this.notify('Loading...');
            this.ajaxGetRequest(`Connector/action/distantEntity?entityType=${this.scope}&id=${this.model.id}`, null, {async: false}).success(attr => {
                this.notify(false);
                var o = {
                    model: this.model,
                    distantModelAttribute: attr,
                    hideQuickMenu: true,
                    scope: this.scope
                };
                this.createView('modalRecord', this.recordView, o);
            })

        },

        actionFullView(data){
            if (!this.getAcl().check(this.scope, 'read')) {
                this.notify('Access denied', 'error');
                return false;
            }

            const url = '#' + this.scope + '/compare?id=' + this.model.get('id');
            this.getRouter().navigate(url, {trigger: false});
            this.getRouter().dispatch(this.scope, 'compare', {
                id: this.model.get('id'),
                model: this.model
            });
        }
    });
});

