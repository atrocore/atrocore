
Espo.define('views/scheduled-job/list', 'views/list', function (Dep) {

    return Dep.extend({

        searchPanel: false,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.menu.buttons.push({
                link: '#Admin/jobs',
                html: this.translate('Jobs', 'labels', 'Admin')
            });

            this.createView('search', 'Base', {
                el: '#main > .search-container',
                template: 'scheduled-job.cronjob'
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            $.ajax({
                type: 'GET',
                url: 'Admin/action/cronMessage',
                error: function (x) {
                }.bind(this)
            }).done(function (data) {
                this.$el.find('.cronjob .message').html(data.message);
                this.$el.find('.cronjob .command').html('<strong>' + data.command + '</strong>');
            }.bind(this));
        },

    });

});
