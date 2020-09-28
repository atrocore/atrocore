
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
