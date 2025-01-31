/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/fields/navigation', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        editTemplate: 'fields/varchar/detail',

        setup() {
            Dep.prototype.setup.call(this);
            this.initMode = this.mode;
            this.setMode('detail')
            this.initInlineActions();
        },

        afterRender() {
            Dep.prototype.setup.call(this);
            if(this.mode !== 'detail') {
                this.initMode = this.mode;
                this.setMode('detail');
                this.reRender()
            }
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data['value'] = '...'
        },

        inlineEdit: function () {
            this.notify('Loading...')
            this.createView('edit', 'views/layout-profile/modals/navigation', {
                field: this.name,
                model: this.model,
                avoidSaving: this.initMode === 'edit'
            }, view => {
                this.notify(false)
                view.render();
            });
        },
    });
});

