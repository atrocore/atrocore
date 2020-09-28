

Espo.define('views/email/record/edit-quick', 'views/email/record/edit', function (Dep, Detail) {

    return Dep.extend({

    	isWide: true,

        sideView: false,

        init: function () {
            Dep.prototype.init.call(this);
            this.columnCount = 2;
        },

        setup: function () {
        	Dep.prototype.setup.call(this);
        },

    });
});

