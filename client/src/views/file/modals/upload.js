/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/modals/upload', 'views/modals/edit',
    Dep => Dep.extend({

        fullFormDisabled: true,

        events: _.extend(Dep.prototype.events, {
                'click #upload-via-url': function (e) {
                    if (!this.$el.find('#upload-via-url .btn-primary').hasClass('disabled')) {
                        this.model.trigger('click:upload-via-url');
                    }
                },
            },
        ),

        setup() {
            Dep.prototype.setup.call(this);

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Close'
                }
            ];

            this.header = this.translate('Upload', 'labels', 'File');

            this.listenTo(this.model, 'upload-disabled', disabled => {
                if (disabled) {
                    this.$el.find('#upload-via-url .btn-primary').addClass('disabled');
                    this.$el.find('#upload-url-input').attr('disabled', 'disabled');
                } else {
                    this.$el.find('#upload-via-url .btn-primary').removeClass('disabled');
                    this.$el.find('#upload-url-input').removeAttr('disabled');
                }
            });
        }
    })
);