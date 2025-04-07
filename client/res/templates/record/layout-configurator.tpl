{{#if showLayoutEditor}}
    <a class="dropdown-toggle btn-link {{ linkClass }}" style="cursor: pointer" data-toggle="dropdown">
        <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="#000"><path d="M440-120v-240h80v80h320v80H520v80h-80Zm-320-80v-80h240v80H120Zm160-160v-80H120v-80h160v-80h80v240h-80Zm160-80v-80h400v80H440Zm160-160v-240h80v80h160v80H680v80h-80Zm-480-80v-80h400v80H120Z"></path></svg>
    </a>
    <ul class="dropdown-menu pull-right">
        {{#each storedProfiles}}
            <li><a href="javascript:" class="layout-profile-item" data-id="{{id}}">{{ name }} {{#if isSelected }}
                <span class="fas fa-check pull-right" style="font-size: 1em;"></span> {{/if}}</a></li>
        {{/each}}
        {{#if canConfigure }}
            {{#if storedProfiles.length }}
                <li class="divider"></li> {{/if}}
            <li><a href="javascript:" class="layout-editor">{{translate 'configure' category='labels' scope='LayoutManager'}}</a></li>
        {{/if}}
    </ul>
{{/if}}