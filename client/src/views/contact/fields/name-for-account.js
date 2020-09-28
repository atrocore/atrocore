
Espo.define('views/contact/fields/name-for-account', 'views/fields/person-name', function (Dep) {

    return Dep.extend({

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.mode === 'listLink') {
                if (this.model.get('accountIsInactive')) {
                    this.$el.find('a').css('text-decoration', 'line-through');
                };
            }
        }
    });

});
