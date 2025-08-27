/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/modals/rebuild-database', 'views/modal', function (Dep) {

    return Dep.extend({

        template: 'modals/rebuild-database',

        backdrop: true,

        header: 'Rebuild database',

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$el.find('.modal-body').css({paddingTop: '0px'});

            const container = this.$el.find('.rebuild-db-container');
            if (container.length) {
                new Svelte.RebuildDatabaseModal({
                    target: container.get(0),
                    props: {
                        onApply: () => {
                            Espo.Ui.notify(this.getLanguage().translate('Please wait...'));

                            this.ajaxPostRequest('Admin/rebuildDb').success(response => {
                                Espo.Ui.success(this.getLanguage().translate('Done'));
                                this.close();
                            });
                        },
                        onCancel: () => {
                            this.close();
                        }
                    }
                });
            }
        }

    });
});


