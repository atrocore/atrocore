/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:ui', '', function () {

    let ui = Espo.TreoUi = {
        confirmWithBody: function (message, o, callback, context) {
            var confirmText = o.confirmText;
            var cancelText = o.cancelText;
            var confirmStyle = o.confirmStyle || 'danger';
            var extendedBody = o.body || '<span class="confirm-message">' + message + '</a>';

            var dialog = new Espo.Ui.Dialog({
                backdrop: false,
                header: false,
                className: 'dialog-confirm',
                body: extendedBody,
                buttons: [
                    {
                        text: ' ' + confirmText + ' ',
                        name: 'confirm',
                        onClick: function () {
                            if (context) {
                                callback.call(context);
                            } else {
                                callback();
                            }
                            dialog.close();
                        },
                        style: confirmStyle,
                        pullLeft: true
                    },
                    {
                        text: cancelText,
                        name: 'cancel',
                        onClick: function () {
                            dialog.close();
                            if (o.cancelCallback) {
                                if (context) {
                                    o.cancelCallback.call(context);
                                } else {
                                    o.cancelCallback();
                                }
                            }
                        },
                        pullRight: true
                    }
                ]
            });

            dialog.show();
        },
    };

    return ui;
});