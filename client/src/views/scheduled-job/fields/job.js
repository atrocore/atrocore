
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('views/scheduled-job/fields/job', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.mode == 'edit' || this.mode == 'detail') {
                this.wait(true);
                $.ajax({
                    url: 'Admin/jobs',
                    success: function (data) {
                        this.params.options = data.filter(function (item) {
                            return !this.getMetadata().get(['entityDefs', 'ScheduledJob', 'jobs', item, 'isSystem']);
                        }, this);
                        this.params.options.unshift('');

                        const translatedJobs = this.getLanguage().get('ScheduledJob', 'options', 'job');

                        this.translatedOptions = {};
                        this.params.options.forEach(option => {
                            this.translatedOptions[option] = option;
                            if (translatedJobs[option]){
                                this.translatedOptions[option] = translatedJobs[option];
                            }
                        });
                        this.wait(false);
                    }.bind(this)
                });
            }

            if (this.model.isNew()) {
                this.on('change', function () {
                    var job = this.model.get('job');
                    if (job) {
                        var label = this.getLanguage().translateOption(job, 'job', 'ScheduledJob');
                        var scheduling = this.getMetadata().get('entityDefs.ScheduledJob.jobSchedulingMap.' + job) || '*/10 * * * *';
                        this.model.set('name', label);
                        this.model.set('scheduling', scheduling);
                    } else {
                        this.model.set('name', '');
                        this.model.set('scheduling', '');
                    }
                }, this);
            }
        }

    });

});
