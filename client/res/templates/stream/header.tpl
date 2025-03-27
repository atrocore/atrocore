<div class="header">
    <div class="filter">
        {{#each filterList}}
        <a href="javascript:" class="filter-item {{#if isActive}} active {{/if}}" data-action="{{action}}" data-name="{{name}}">{{label}}</a>
        {{/each}}
    </div>
    <a href="#" class="change-history" data-action="changeHistory" >Change History</a>
</div>
