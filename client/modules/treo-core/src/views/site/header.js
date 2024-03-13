/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/site/header', 'class-replace!treo-core:views/site/header', function (Dep) {

    return Dep.extend({

        title: 'AtroCore',

        dataTimestamp: null,

        rebuilding: false,

        setup: function () {
            this.navbarView = this.getMetadata().get('app.clientDefs.navbarView') || this.navbarView;

            Dep.prototype.setup.call(this);

            this.getPublicData();

            $(document).on('click', 'a[data-action="rebuild-notification"]', () => {
                this.rebuildDb();
            });
        },

        getPublicData() {
            setInterval(() => {
                $.ajax('data/publicData.json?silent=true&time=' + $.now(), {local: true}).done(response => {
                    Backbone.Events.trigger('publicData', response);
                    if (response.dataTimestamp) {
                        $.each(response, (k, v) => {
                            localStorage.setItem('pd_' + k, v);
                        });
                        this.isNeedToReloadPage();
                    }

                    if (response.isNeedToRebuildDatabase && !this.rebuilding) {
                        Espo.Ui.notify(this.translate('pleaseRebuildDatabase'), 'info', 1000 * 60, true);
                    }
                });
            }, 1000);
        },

        isNeedToReloadPage() {
            const key = 'pd_dataTimestamp';
            if (this.dataTimestamp && this.dataTimestamp !== localStorage.getItem(key)) {
                setTimeout(() => {
                    Espo.Ui.notify(this.translate('pleaseReloadPage'), 'info', 1000 * 60, true);
                }, 5000);
            }
            this.dataTimestamp = localStorage.getItem(key);
        },

        rebuildDb() {
            this.rebuilding = true;
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            Espo.Ui.confirm(this.translate('rebuildDb', 'messages', 'Admin'), {
                confirmText: this.translate('Apply'),
                cancelText: this.translate('Cancel')
            }, () => {
                $.ajax({
                    url: 'Admin/rebuildDb',
                    type: 'POST',
                    success: () => {
                        this.rebuilding = false;
                        Espo.Ui.success('Success');
                    }
                });
            });
        }

    });

});


