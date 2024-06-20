<div class="panel panel-default">
    <div class="panel-body">
        {{#each layoutDataList}}
        <div style="{{cssStyle}}">
            {{#if isFirst}}
            {{#unless rowActionsDisabled}}
            <div class="pull-right item-menu-container">{{{../../../itemMenu}}}</div>
            {{/unless}}
            {{/if}}
            <div class="form-group horizontal">
                <div class="field{{#if isAlignRight}} field-right-align{{/if}}{{#if isLarge}} field-large{{/if}}" data-name="{{name}}">{{{var key ../this}}}</div>
                {{#if showLabel }}
                <div class="label-text"> {{label}}</div>
                {{/if}}
            </div>
        </div>
        {{/each}}

        {{#if assignedUserId}}
        <div class="kanban-avatar">
            <a href="/#User/view/{{assignedUserId}}" target="_blank" title="{{assignedUserName}}"><img src="/?entryPoint=avatar&size=small&id={{assignedUserId}}" alt="{{assignedUserName}}" title="{{assignedUserName}}"></a>
        </div>
        {{/if}}
    </div>
</div>