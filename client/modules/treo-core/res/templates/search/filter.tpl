<div class="form-group">
    {{#if isPinEnabled}}
        <a href="javascript:" class="pull-right pin-filter {{#if pinned}}pinned{{/if}}" data-action="pinFilter"><i class="fas fa-thumbtack" style="margin-left: 5px"></i></a>
    {{/if}}
    <a href="javascript:" class="remove-filter pull-right" data-name="{{name}}">{{#unless notRemovable}}<i class="fas fa-times"></i>{{/unless}}</a>
    <label class="control-label small" data-name="{{name}}">{{translate generalName category='fields' scope=scope}}</label>
    <div class="field" data-name="{{generalName}}">{{{field}}}</div>
</div>