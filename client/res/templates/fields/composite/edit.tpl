<div class="composite-container">
    {{#each childrenFields}}
        <div class="row">
            <div class="cell form-group col-xs-12" data-name="{{name}}">
                <label class="control-label" data-name="{{name}}"><span class="label-text">{{label}}</span></label>
                <div class="field" data-name="{{name}}">
                    {{{var name ../this}}}
                </div>
            </div>
        </div>
    {{/each}}
</div>