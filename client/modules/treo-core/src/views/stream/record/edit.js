

Espo.define('treo-core:views/stream/record/edit', 'class-replace!treo-core:views/stream/record/edit', function (Dep) {

    return Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            delete this.events['focus textarea[name="post"]'];

            this.events['click textarea[name="post"]'] = e => {
                this.enablePostingMode();
            }
        },
    })
});