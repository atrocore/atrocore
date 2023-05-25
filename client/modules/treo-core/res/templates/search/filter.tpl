<div class="form-group">
    <a href="javascript:" class="remove-filter pull-right" data-name="{{name}}">{{#unless notRemovable}}<i class="fas fa-times"></i>{{/unless}}</a>
    {{#if isPinEnabled}}
        <a href="javascript:" class="pull-right pin-filter {{#if pinned}}pinned{{/if}}" data-action="pinFilter"><i class="fas fa-thumbtack"></i></a>
    {{/if}}
    <label class="control-label small" data-name="{{name}}">{{translate generalName category='fields' scope=scope}}</label>
    <div class="field" data-name="{{generalName}}">{{{field}}}</div>
</div>