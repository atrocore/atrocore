    {{#if isEnabled}}
    <div class="list-row-buttons pull-right right">
        {{#if acl.edit}}
        <div class="btn-group">
        <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
            <i class="ph ph-caret-down"></i>
        </button>
        <ul class="dropdown-menu pull-right">
            {{#if isEditable}}
            <li><a href="javascript:" class="action" data-action="quickEdit" data-id="{{model.id}}" data-no-full-form="true">{{translate 'Edit'}}</a></li>
            {{/if}}
            {{#if isRemovable}}
            <li><a href="javascript:" class="action" data-action="quickRemove" data-id="{{model.id}}">{{translate 'Remove'}}</a></li>
            {{/if}}
        </ul>
        </div>
        {{/if}}
    </div>
{{/if}}