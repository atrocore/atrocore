

Espo.define('views/clear-cache', 'view', function (Dep) {

    return Dep.extend({

        template: 'clear-cache',

        el: '> body',

        events: {
            'click .action[data-action="clearLocalCache"]': function () {
                this.clearLocalCache();
            },
            'click .action[data-action="returnToApplication"]': function () {
                this.returnToApplication();
            }
        },

        data: function () {
            return {
                cacheIsEnabled: !!this.options.cache
            };
        },

        clearLocalCache: function () {
            this.options.cache.clear();
            this.$el.find('.action[data-action="clearLocalCache"]').remove();
            this.$el.find('.message-container').removeClass('hidden');
            this.$el.find('.message-container span').html(this.translate('Cache has been cleared'));
            this.$el.find('.action[data-action="returnToApplication"]').removeClass('hidden');
        },

        returnToApplication: function () {
            this.getRouter().navigate('', {trigger: true});
        }

    });
});

