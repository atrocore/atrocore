{{#if showLayoutEditor}}
    <a class="btn btn-link dropdown-toggle" data-toggle="dropdown">
        <span class="fas fa-cog cursor-pointer" style="font-size: 1em;"></span>
    </a>
    <ul class="dropdown-menu pull-right">
        {{#each storedProfiles}}
            <li><a href="javascript:" class="layout-profile-item" data-id="{{id}}">{{ name }} {{#if isSelected }}
                <span class="fas fa-check pull-right" style="font-size: 1em;"></span> {{/if}}</a></li>
        {{/each}}
        {{#if canConfigure }}
            {{#if storedProfiles.length }}
                <li class="divider"></li> {{/if}}
            <li><a href="javascript:" class="layout-editor">Configure</a></li>
        {{/if}}
    </ul>
{{/if}}