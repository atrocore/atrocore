
Espo.define('views/record/list-pagination', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/list-pagination',

        data: function () {
            var previous = this.collection.offset > 0;
            var next = this.collection.total - this.collection.offset > this.collection.maxSize;

            return {
                total: this.collection.total,
                from: this.collection.offset + 1 ,
                to: this.collection.offset + this.collection.length,
                previous: previous,
                next: next,
            };
        }

    });
});
