/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/scheduled-job/fields/type', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.params.options = [];
            this.translatedOptions = {};

            const translatedJobs = this.getLanguage().get('ScheduledJob', 'options', 'type');

            $.each((this.getMetadata().get('app.jobTypes') || {}), (type, data) => {
                if (data.scheduledJob) {
                    this.params.options.push(type);
                    this.translatedOptions[type] = translatedJobs[type] ?? type;
                }
            });
        }

    });

});
