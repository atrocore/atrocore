<div class="composite-container">
    {{#each childrenRows}}
        <div class="row">
            {{#each this}}
                <div class="cell form-group col-xs-12 {{#if fullWidth}}col-sm-12{{else}}col-sm-6{{/if}}" data-name="{{name}}">
                    <label class="control-label" data-name="{{name}}"><span class="label-text">{{label}}</span></label>
                    <div class="field" data-name="{{name}}">
                        {{{var name ../../this}}}
                    </div>
                </div>
            {{/each}}
        </div>
    {{/each}}
</div>