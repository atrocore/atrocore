/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/notification-rule/fields/notification-template', 'views/fields/link', function (Dep) {

    return Dep.extend({

        createDisabled: true,

        transportType: null,

        setup: function () {
            this.transportType = this.model.getFieldParam(this.name, 't_type')
            this.name = this.model.getFieldParam(this.name, 'name')
            this.foreignScope = this.transportType === 'email' ? 'EmailTemplate' : 'NotificationTemplate';
            Dep.prototype.setup.call(this);
        },
    });
});