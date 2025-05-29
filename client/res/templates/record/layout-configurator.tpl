{{#if showLayoutEditor}}
    <a class="dropdown-toggle btn-link {{ linkClass }}" style="cursor: pointer" data-toggle="dropdown">
        <i class="ph ph-gear-six"></i>
    </a>
    <ul class="dropdown-menu {{#if alignRight}}pull-right{{/if}}">
        {{#each storedProfiles}}
            <li><a href="javascript:" class="layout-profile-item" data-id="{{id}}">{{ name }} {{#if isSelected }}
                <i class="ph ph-check pull-right"></i>{{/if}}</a></li>
        {{/each}}
        {{#if canConfigure }}
            {{#if storedProfiles.length }}
                <li class="divider"></li> {{/if}}
            <li><a href="javascript:" class="layout-editor">{{translate 'configure' category='labels' scope='LayoutManager'}}</a></li>
        {{/if}}
    </ul>
{{/if}}