<div class="composite-container">
    {{#each childrenFields}}
    <div class="cell form-group col-xs-12" data-name="{{name}}">
        <div class="pull-right inline-actions"></div>
        <label class="control-label" data-name="{{name}}"><span class="label-text">{{label}}</span></label>
        <div class="field" data-name="{{name}}">
            {{{var name ../this}}}
        </div>
    </div>
    {{/each}}
</div>